"""Translation worker tasks for async translation processing.

This module provides Celery tasks for translating company fields
using the SYSTRAN Translation API with progress tracking.
"""

import asyncio
from datetime import datetime, timezone
from celery import Task

from app.core.celery_app import celery_app
from app.core.logging_config import get_logger
from app.database import SessionLocal
from app.models import TranslationJob, TranslationJobStatus
from app.services.systran import SystranClient, SystranError
from app.services.translation import TranslationService

logger = get_logger(__name__)


class TranslationTask(Task):
    """Base task class for translation execution."""

    autoretry_for = (SystranError,)
    max_retries = 3
    default_retry_delay = 30  # 30 seconds between retries

    def on_failure(self, exc, task_id, args, kwargs, einfo):
        """Handle task failure - update job status."""
        company_id = kwargs.get("company_id")
        language_code = kwargs.get("language_code")
        job_id = kwargs.get("job_id")

        logger.error(
            f"Translation task failed for company {company_id} ({language_code}): {exc}",
            extra={
                "celery_task_id": task_id,
                "company_id": company_id,
                "language_code": language_code,
                "job_id": job_id,
                "error": str(exc),
            },
        )

        # Update job status to failed
        if job_id:
            with SessionLocal() as db:
                job = db.query(TranslationJob).filter(TranslationJob.id == job_id).first()
                if job:
                    job.status = TranslationJobStatus.failed
                    job.error_message = str(exc)
                    job.completed_at = datetime.now(timezone.utc)
                    db.commit()


def run_async(coro):
    """Helper to run async code in sync Celery task context."""
    loop = None
    try:
        loop = asyncio.get_event_loop()
        if loop.is_running():
            loop = asyncio.new_event_loop()
            asyncio.set_event_loop(loop)
            return loop.run_until_complete(coro)
        else:
            return loop.run_until_complete(coro)
    except RuntimeError:
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        return loop.run_until_complete(coro)
    finally:
        if loop and not loop.is_running():
            loop.close()


@celery_app.task(
    bind=True,
    base=TranslationTask,
    name="translate_company_fields",
    queue="translations",
)
def translate_company_fields(
    self,
    company_id: int,
    language_code: str,
    job_id: int | None = None,
) -> dict:
    """Translate all untranslated fields for a company to a specific language.

    This task fetches all fields that need translation, translates them
    in batches using SYSTRAN API, and saves the results to the database.
    Progress is tracked via TranslationJob if job_id is provided.

    Args:
        company_id: Company ID to translate
        language_code: Target language code (e.g., 'es', 'de', 'pt')
        job_id: Optional TranslationJob ID for progress tracking

    Returns:
        Dictionary with translation results summary
    """
    logger.info(
        f"Starting translation task for company {company_id} to {language_code}",
        extra={
            "company_id": company_id,
            "language_code": language_code,
            "job_id": job_id,
            "celery_task_id": self.request.id,
        },
    )

    with SessionLocal() as db:
        # Update job status to running
        job = None
        if job_id:
            job = db.query(TranslationJob).filter(TranslationJob.id == job_id).first()
            if job:
                job.status = TranslationJobStatus.running
                job.celery_task_id = self.request.id
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
            if job:
                job.status = TranslationJobStatus.completed
                job.completed_at = datetime.now(timezone.utc)
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
        if job:
            job.total_fields = total_fields
            db.commit()

        # Update Celery task state for Flower monitoring
        self.update_state(
            state="PROGRESS",
            meta={
                "current": 0,
                "total": total_fields,
                "percent": 0,
                "company_id": company_id,
                "language_code": language_code,
            },
        )

        # Initialize SYSTRAN client
        try:
            systran_client = SystranClient()
        except SystranError as e:
            logger.error(f"Failed to initialize SYSTRAN client: {e}")
            if job:
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
        BATCH_SIZE = 50
        translated_count = 0
        error_count = 0

        for i in range(0, total_fields, BATCH_SIZE):
            batch = fields_to_translate[i : i + BATCH_SIZE]
            texts = [f.source_value for f in batch]

            try:
                # Translate batch
                results = run_async(
                    systran_client.translate_batch(
                        texts=texts,
                        target_language=language_code,
                        source_language="en",
                    )
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

                # Update job progress
                if job:
                    job.translated_fields = translated_count
                    db.commit()

                # Update Celery task state
                self.update_state(
                    state="PROGRESS",
                    meta={
                        "current": translated_count,
                        "total": total_fields,
                        "percent": progress_percent,
                        "company_id": company_id,
                        "language_code": language_code,
                    },
                )

                logger.info(
                    f"Translation progress: {translated_count}/{total_fields} ({progress_percent}%) "
                    f"for company {company_id}"
                )

            except SystranError as e:
                logger.error(f"Batch translation failed for company {company_id}: {e}")
                error_count += len(batch)
                # Re-raise for retry mechanism
                if self.request.retries < self.max_retries:
                    raise

        # Mark job as completed
        if job:
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


@celery_app.task(
    bind=True,
    base=TranslationTask,
    name="translate_single_field",
    queue="translations",
)
def translate_single_field(
    self,
    company_id: int,
    table_name: str,
    record_id: int,
    field_name: str,
    source_value: str,
    language_code: str,
) -> dict:
    """Translate a single field.

    Useful for on-demand translation of specific fields.

    Args:
        company_id: Company ID
        table_name: Source table name
        record_id: Source record ID
        field_name: Field name to translate
        source_value: Text to translate
        language_code: Target language code

    Returns:
        Dictionary with translation result
    """
    logger.info(
        f"Translating single field: {table_name}.{field_name} for company {company_id}",
        extra={
            "company_id": company_id,
            "table_name": table_name,
            "field_name": field_name,
            "language_code": language_code,
        },
    )

    try:
        systran_client = SystranClient()
        result = run_async(
            systran_client.translate_text(
                text=source_value,
                target_language=language_code,
                source_language="en",
            )
        )

        # Save to database
        with SessionLocal() as db:
            import hashlib

            translation_service = TranslationService(db)
            source_hash = hashlib.sha256(source_value.encode()).hexdigest()[:64]

            translation_service.save_translation(
                company_id=company_id,
                table_name=table_name,
                record_id=record_id,
                field_name=field_name,
                language_code=language_code,
                value=result.translated_text,
                source_value_hash=source_hash,
            )

        logger.info(f"Single field translation complete: {table_name}.{field_name}")

        return {
            "status": "success",
            "company_id": company_id,
            "table_name": table_name,
            "field_name": field_name,
            "language_code": language_code,
            "translated_text": result.translated_text,
        }

    except SystranError as e:
        logger.error(f"Single field translation failed: {e}")
        return {
            "status": "error",
            "company_id": company_id,
            "table_name": table_name,
            "field_name": field_name,
            "error": str(e),
        }
