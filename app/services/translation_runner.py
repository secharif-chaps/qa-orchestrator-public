"""Background translation runner for FastAPI BackgroundTasks.

This module provides a simple background translation function that runs
within the FastAPI process, avoiding the complexity of Celery for
low-volume translation workloads.

Architecture Decision:
- Translation typically completes in 2-3 seconds
- Low concurrent translation volume expected
- Celery adds unnecessary complexity (RabbitMQ, separate worker, K8s deployment)
- FastAPI BackgroundTasks provide sufficient async capability for V1

Future Considerations:
- If translation volume increases significantly, migrate to Celery
- If translations need retry logic with backoff, consider Celery
- When API Gateway multi-module architecture is implemented, reassess
"""

import asyncio
from datetime import datetime, timezone

from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.database import SessionLocal
from app.models import TranslationJob, TranslationJobStatus
from app.services.systran import SystranClient, SystranError
from app.services.translation import TranslationService

logger = get_logger(__name__)

# Batch size for translation API calls
TRANSLATION_BATCH_SIZE = 50


async def run_translation_async(
    job_id: int,
    company_id: int,
    language_code: str,
) -> dict:
    """Run translation for a company in the background.

    This function is designed to be called from FastAPI BackgroundTasks.
    It manages its own database session since BackgroundTasks run after
    the request completes.

    Args:
        job_id: TranslationJob ID for progress tracking
        company_id: Company ID to translate
        language_code: Target language code (e.g., 'fr', 'es')

    Returns:
        Dictionary with translation results summary
    """
    logger.info(
        f"Starting background translation for company {company_id} to {language_code}",
        extra={
            "company_id": company_id,
            "language_code": language_code,
            "job_id": job_id,
        },
    )

    # Create a new database session for this background task
    db: Session = SessionLocal()

    try:
        # Update job status to running
        job = db.query(TranslationJob).filter(TranslationJob.id == job_id).first()
        if not job:
            logger.error(f"Translation job {job_id} not found")
            return {"status": "error", "error": "Job not found"}

        job.status = TranslationJobStatus.running
        job.started_at = datetime.now(timezone.utc)
        db.commit()

        # Get fields that need translation
        translation_service = TranslationService(db)
        fields_to_translate = translation_service.get_fields_to_translate(
            company_id, language_code
        )

        if not fields_to_translate:
            logger.info(
                f"No fields to translate for company {company_id} ({language_code})"
            )
            job.status = TranslationJobStatus.completed
            job.completed_at = datetime.now(timezone.utc)
            job.total_fields = 0
            job.translated_fields = 0
            db.commit()
            return {
                "status": "success",
                "company_id": company_id,
                "language_code": language_code,
                "translated_count": 0,
                "message": "No fields needed translation",
            }

        total_fields = len(fields_to_translate)
        logger.info(f"Found {total_fields} fields to translate for company {company_id}")

        # Update job with total fields
        job.total_fields = total_fields
        db.commit()

        # Initialize SYSTRAN client
        try:
            systran_client = SystranClient()
        except SystranError as e:
            logger.error(f"Failed to initialize SYSTRAN client: {e}")
            job.status = TranslationJobStatus.failed
            job.error_message = str(e)
            job.completed_at = datetime.now(timezone.utc)
            db.commit()
            return {
                "status": "error",
                "company_id": company_id,
                "language_code": language_code,
                "error": str(e),
            }

        # Translate in batches
        translated_count = 0
        error_count = 0

        for i in range(0, total_fields, TRANSLATION_BATCH_SIZE):
            batch = fields_to_translate[i : i + TRANSLATION_BATCH_SIZE]
            texts = [f.source_value for f in batch]

            try:
                # Translate batch (async call)
                results = await systran_client.translate_batch(
                    texts=texts,
                    target_language=language_code,
                    source_language="en",
                )

                # Save translations
                for field, result in zip(batch, results):
                    try:
                        translation_service.save_translation(
                            company_id=company_id,
                            table_name=field.table_name,
                            record_id=field.record_id,
                            field_name=field.field_name,
                            language_code=language_code,
                            value=result.translated_text,
                            source_value_hash=field.source_value_hash,
                        )
                        translated_count += 1
                    except Exception as e:
                        logger.error(
                            f"Failed to save translation for {field.table_name}.{field.field_name}: {e}"
                        )
                        error_count += 1

                # Update progress
                progress_percent = round((translated_count / total_fields) * 100, 1)
                job.translated_fields = translated_count
                db.commit()

                logger.info(
                    f"Translation progress: {translated_count}/{total_fields} ({progress_percent}%) "
                    f"for company {company_id}"
                )

            except SystranError as e:
                logger.error(f"Batch translation failed for company {company_id}: {e}")
                error_count += len(batch)
                # Continue with next batch instead of failing entirely
                continue

        # Mark job as completed
        job.status = TranslationJobStatus.completed
        job.translated_fields = translated_count
        job.completed_at = datetime.now(timezone.utc)
        if error_count > 0:
            job.error_message = f"{error_count} fields failed to translate"
        db.commit()

        logger.info(
            f"Translation complete for company {company_id} ({language_code}): "
            f"{translated_count} translated, {error_count} errors"
        )

        return {
            "status": "success" if error_count == 0 else "partial",
            "company_id": company_id,
            "language_code": language_code,
            "translated_count": translated_count,
            "error_count": error_count,
            "total_fields": total_fields,
        }

    except Exception as e:
        logger.error(
            f"Unexpected error in translation for company {company_id}: {e}",
            exc_info=True,
        )
        # Try to update job status
        try:
            job = db.query(TranslationJob).filter(TranslationJob.id == job_id).first()
            if job:
                job.status = TranslationJobStatus.failed
                job.error_message = str(e)
                job.completed_at = datetime.now(timezone.utc)
                db.commit()
        except Exception:
            pass

        return {
            "status": "error",
            "company_id": company_id,
            "language_code": language_code,
            "error": str(e),
        }

    finally:
        db.close()


def run_translation_background(
    job_id: int,
    company_id: int,
    language_code: str,
) -> None:
    """Synchronous wrapper for background translation.

    FastAPI BackgroundTasks expects a regular function, but our translation
    logic is async. This wrapper creates an event loop to run the async code.

    Args:
        job_id: TranslationJob ID for progress tracking
        company_id: Company ID to translate
        language_code: Target language code
    """
    asyncio.run(
        run_translation_async(
            job_id=job_id,
            company_id=company_id,
            language_code=language_code,
        )
    )
