"""
Translation endpoints for internationalization features.

Provides endpoints for:
- Listing supported languages
- Getting translation status for a company
- Requesting translations for a company/language
- Checking translation job progress

Architecture Note:
    Translation uses FastAPI BackgroundTasks instead of Celery for simplicity.
    This decision was made because:
    - Translation typically completes in 2-3 seconds
    - Low concurrent translation volume expected
    - Avoids Celery/RabbitMQ infrastructure complexity
    - Simpler Kubernetes deployment (no separate worker)

    See docs/architecture/adr-001-translation-background-tasks.md for details.
"""

from fastapi import APIRouter, BackgroundTasks, Depends, HTTPException, status
from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.core.organization_context import OrganizationContext, get_user_organization, require_feature
from app.core.security import verify_company_organization_access
from app.database import get_db
from app.models import Company, FeatureFlag, TranslationJob, TranslationJobStatus
from app.schemas.translation import (
    CompanyTranslationStatusResponse,
    LanguageResponse,
    LanguageTranslationStatus,
    TranslateRequest,
    TranslateResponse,
    TranslationJobResponse,
)
from app.services.translation import SUPPORTED_LANGUAGES, TranslationService
from app.services.translation_runner import run_translation_background

router = APIRouter(prefix="/translation", tags=["translation"])

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


@router.get(
    "/list",
    response_model=list[LanguageResponse],
    openapi_extra={"x-public": True},
    summary="List supported languages",
    description="Return all languages available for company content translation.",
    responses={
        401: {"description": "Missing or invalid authentication token"},
    },
)
async def list_languages() -> list[LanguageResponse]:
    """Get the list of supported languages for translation.

    Returns:
        List of language objects with code and name.
    """
    return [LanguageResponse(**lang) for lang in SUPPORTED_LANGUAGES]


@router.get(
    "/status/{company_id}",
    response_model=CompanyTranslationStatusResponse,
    openapi_extra={"x-permissions": []},
    summary="Get company translation status",
    description=(
        "Return the translation status for a company across every supported language. "
        "Each language entry includes the number of translated fields, completion percentage, "
        "and any active translation job. Requires the `TRANSLATION` feature flag to be enabled."
    ),
    responses={
        401: {"description": "Missing or invalid authentication token"},
        403: {"description": "Company not in user's organization or TRANSLATION feature disabled"},
        404: {"description": "Company not found"},
    },
)
async def get_translation_status(
    company_id: int,
    org_context: OrganizationContext = Depends(get_user_organization),
    _feature: None = Depends(require_feature(FeatureFlag.TRANSLATION)),
    db: Session = Depends(get_db),
) -> CompanyTranslationStatusResponse:
    """Get translation status for a company across all languages.

    Includes active job information for languages currently being translated.

    Requires organization.read role and translation feature enabled.

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
            TranslationJob.status.in_(
                [
                    TranslationJobStatus.pending,
                    TranslationJobStatus.running,
                ]
            ),
        )
        .all()
    )

    # Build response with active job info
    translations: dict[str, LanguageTranslationStatus] = {}
    for lang_code, lang_status in status_data["translations"].items():
        # Find active job for this language
        active_job = next((j for j in active_jobs if j.language_code == lang_code), None)

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
    openapi_extra={"x-permissions": []},
    summary="Get translation job progress",
    description=(
        "Poll the progress of a translation job. Returns completion percentage, "
        "translated field count, and timing information. "
        "Requires the `TRANSLATION` feature flag to be enabled."
    ),
    responses={
        401: {"description": "Missing or invalid authentication token"},
        403: {"description": "TRANSLATION feature disabled or company not in user's organization"},
        404: {"description": "Translation job not found"},
    },
)
async def get_translation_job(
    job_id: int,
    org_context: OrganizationContext = Depends(get_user_organization),
    _feature: None = Depends(require_feature(FeatureFlag.TRANSLATION)),
    db: Session = Depends(get_db),
) -> TranslationJobResponse:
    """Get translation job progress.

    Requires translation feature enabled.

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
    openapi_extra={"x-permissions": []},
    summary="Request company translation",
    description=(
        "Start translating a company's content fields into the specified language. "
        "A background job is created and typically completes in 2-3 seconds. "
        "If a job is already running for the same company/language pair, "
        "the existing job is returned instead of creating a duplicate. "
        "Requires the `TRANSLATION` feature flag to be enabled."
    ),
    responses={
        400: {"description": "Unsupported language code"},
        401: {"description": "Missing or invalid authentication token"},
        403: {"description": "Company not in user's organization or TRANSLATION feature disabled"},
        404: {"description": "Company not found"},
    },
)
async def request_translation(
    company_id: int,
    request: TranslateRequest,
    background_tasks: BackgroundTasks,
    org_context: OrganizationContext = Depends(get_user_organization),
    _feature: None = Depends(require_feature(FeatureFlag.TRANSLATION)),
    db: Session = Depends(get_db),
) -> TranslateResponse:
    """Request translation of company fields to a specific language.

    This endpoint creates a translation job and runs it in the background.
    If a job is already running for this company/language, returns the existing job.

    Translation runs via FastAPI BackgroundTasks (not Celery) for simplicity.
    Typical translation time is 2-3 seconds for ~15 fields.

    Requires organization.read role and translation feature enabled.

    Args:
        company_id: Company ID to translate
        request: Translation request with target language
        background_tasks: FastAPI background tasks handler

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
            detail=f"Unsupported language code: {request.language_code}. Supported: {', '.join(valid_codes)}",
        )

    # Check for existing active job
    existing_job = (
        db.query(TranslationJob)
        .filter(
            TranslationJob.company_id == company_id,
            TranslationJob.language_code == request.language_code,
            TranslationJob.status.in_(
                [
                    TranslationJobStatus.pending,
                    TranslationJobStatus.running,
                ]
            ),
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
    fields_to_translate = service.get_fields_to_translate(company_id, request.language_code)

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

    # Run translation in background (non-blocking)
    background_tasks.add_task(
        run_translation_background,
        job_id=job.id,
        company_id=company_id,
        language_code=request.language_code,
    )

    logger.info(
        f"Translation job created for company {company_id} to {request.language_code}",
        extra={
            "company_id": company_id,
            "language_code": request.language_code,
            "fields_count": len(fields_to_translate),
            "job_id": job.id,
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
