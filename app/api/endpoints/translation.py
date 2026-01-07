"""
Translation endpoints for internationalization features.

Provides endpoints for:
- Listing supported languages
- Getting translation status for a company
- Requesting translations for a company/language
- Checking translation job progress
"""

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.core.organization import get_user_organization, OrganizationContext
from app.core.security import verify_company_organization_access
from app.database import get_db
from app.models import Company, TranslationJob, TranslationJobStatus
from app.schemas.translation import (
    LanguageResponse,
    LanguageTranslationStatus,
    CompanyTranslationStatusResponse,
    TranslateRequest,
    TranslateResponse,
    TranslationJobResponse,
)
from app.services.translation import TranslationService, SUPPORTED_LANGUAGES

router = APIRouter(
    prefix="/translation",
    tags=["translation"]
)

logger = get_logger(__name__)


def _job_to_response(job: TranslationJob) -> TranslationJobResponse:
    """Convert TranslationJob model to response schema."""
    return TranslationJobResponse(
        id=job.id,
        company_id=job.company_id,
        language_code=job.language_code,
        status=job.status.value,
        total_fields=job.total_fields,
        translated_fields=job.translated_fields,
        progress_percentage=job.progress_percentage,
        error_message=job.error_message,
        created_at=job.created_at,
        started_at=job.started_at,
        completed_at=job.completed_at,
    )


@router.get("/list", response_model=list[LanguageResponse])
async def list_languages() -> list[LanguageResponse]:
    """Get the list of supported languages for translation.

    Returns:
        List of language objects with code and name.
    """
    return [LanguageResponse(**lang) for lang in SUPPORTED_LANGUAGES]


@router.get(
    "/status/{company_id}",
    response_model=CompanyTranslationStatusResponse,
)
async def get_translation_status(
    company_id: int,
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
) -> CompanyTranslationStatusResponse:
    """Get translation status for a company across all languages.

    Includes active job information for languages currently being translated.

    Requires organization.read role for access.

    Args:
        company_id: Company ID to check translation status for

    Returns:
        Translation status per language including completion percentage and active jobs.
    """
    # Get company and verify organization access
    company = db.query(Company).filter(Company.id == company_id).first()
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Company not found",
        )

    verify_company_organization_access(company, org_context)

    # Get translation status
    service = TranslationService(db)
    status_data = service.get_company_translation_status(company_id)

    # Get active jobs for this company
    active_jobs = (
        db.query(TranslationJob)
        .filter(
            TranslationJob.company_id == company_id,
            TranslationJob.status.in_([
                TranslationJobStatus.pending,
                TranslationJobStatus.running,
            ]),
        )
        .all()
    )

    # Build response with active job info
    translations: dict[str, LanguageTranslationStatus] = {}
    for lang_code, lang_status in status_data["translations"].items():
        # Find active job for this language
        active_job = next(
            (j for j in active_jobs if j.language_code == lang_code),
            None
        )

        translations[lang_code] = LanguageTranslationStatus(
            language_name=lang_status["language_name"],
            status=lang_status["status"],
            fields_translated=lang_status["fields_translated"],
            fields_total=lang_status["fields_total"],
            percentage=lang_status["percentage"],
            active_job=_job_to_response(active_job) if active_job else None,
        )

    return CompanyTranslationStatusResponse(
        company_id=company_id,
        translations=translations,
    )


@router.get(
    "/job/{job_id}",
    response_model=TranslationJobResponse,
)
async def get_translation_job(
    job_id: int,
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
) -> TranslationJobResponse:
    """Get translation job progress.

    Args:
        job_id: Translation job ID

    Returns:
        Translation job details with progress.
    """
    job = db.query(TranslationJob).filter(TranslationJob.id == job_id).first()
    if not job:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Translation job not found",
        )

    # Verify organization access via company
    company = db.query(Company).filter(Company.id == job.company_id).first()
    if company:
        verify_company_organization_access(company, org_context)

    return _job_to_response(job)


@router.post(
    "/translate/{company_id}",
    response_model=TranslateResponse,
)
async def request_translation(
    company_id: int,
    request: TranslateRequest,
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
) -> TranslateResponse:
    """Request translation of company fields to a specific language.

    This endpoint creates a translation job and queues it for processing.
    If a job is already running for this company/language, returns the existing job.

    Requires organization.read role for access.

    Args:
        company_id: Company ID to translate
        request: Translation request with target language

    Returns:
        Translation job details.
    """
    # Get company and verify organization access
    company = db.query(Company).filter(Company.id == company_id).first()
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Company not found",
        )

    verify_company_organization_access(company, org_context)

    # Validate language code
    valid_codes = {lang["code"] for lang in SUPPORTED_LANGUAGES}
    if request.language_code not in valid_codes:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Unsupported language code: {request.language_code}. "
                   f"Supported: {', '.join(valid_codes)}",
        )

    # Check for existing active job
    existing_job = (
        db.query(TranslationJob)
        .filter(
            TranslationJob.company_id == company_id,
            TranslationJob.language_code == request.language_code,
            TranslationJob.status.in_([
                TranslationJobStatus.pending,
                TranslationJobStatus.running,
            ]),
        )
        .first()
    )

    if existing_job:
        return TranslateResponse(
            company_id=company_id,
            language_code=request.language_code,
            fields_queued=existing_job.total_fields,
            message="Translation already in progress.",
            job=_job_to_response(existing_job),
        )

    # Get fields to translate
    service = TranslationService(db)
    fields_to_translate = service.get_fields_to_translate(
        company_id, request.language_code
    )

    if not fields_to_translate:
        return TranslateResponse(
            company_id=company_id,
            language_code=request.language_code,
            fields_queued=0,
            message="All fields are already translated or no translatable content found.",
            job=None,
        )

    # Create translation job
    job = TranslationJob(
        company_id=company_id,
        language_code=request.language_code,
        status=TranslationJobStatus.pending,
        total_fields=len(fields_to_translate),
    )
    db.add(job)
    db.commit()
    db.refresh(job)

    # Queue translation task via Celery
    from app.workers.translation_tasks import translate_company_fields

    task = translate_company_fields.delay(
        company_id=company_id,
        language_code=request.language_code,
        job_id=job.id,
    )

    # Update job with celery task ID
    job.celery_task_id = task.id
    db.commit()

    logger.info(
        f"Translation job created for company {company_id} to {request.language_code}",
        extra={
            "company_id": company_id,
            "language_code": request.language_code,
            "fields_count": len(fields_to_translate),
            "job_id": job.id,
            "celery_task_id": task.id,
            "username": org_context.username,
        },
    )

    return TranslateResponse(
        company_id=company_id,
        language_code=request.language_code,
        fields_queued=len(fields_to_translate),
        message=f"Translation started for {len(fields_to_translate)} fields.",
        job=_job_to_response(job),
    )
