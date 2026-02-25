"""Pydantic schemas for translation endpoints."""

from datetime import datetime
from pydantic import BaseModel, Field


class LanguageResponse(BaseModel):
    """Language information response."""

    code: str = Field(..., description="ISO language code (e.g., 'fr', 'es')")
    name: str = Field(..., description="Language name in English")


class LanguageTranslationStatus(BaseModel):
    """Translation status for a single language."""

    language_name: str = Field(..., description="Language name in English")
    status: str = Field(
        ..., description="Translation status: 'complete', 'partial', or 'none'"
    )
    fields_translated: int = Field(..., description="Number of translated fields")
    fields_total: int = Field(..., description="Total translatable fields")
    percentage: float = Field(..., description="Translation completion percentage")
    active_job: "TranslationJobResponse | None" = Field(
        None, description="Active translation job if any"
    )


class CompanyTranslationStatusResponse(BaseModel):
    """Translation status response for a company."""

    company_id: int = Field(..., description="Company ID")
    translations: dict[str, LanguageTranslationStatus] = Field(
        ..., description="Translation status per language code"
    )


class TranslateRequest(BaseModel):
    """Request to translate company fields to a language."""

    language_code: str = Field(
        ...,
        description="Target language code (e.g., 'es', 'de', 'pt')",
        min_length=2,
        max_length=5,
    )


class TranslationJobResponse(BaseModel):
    """Translation job information."""

    id: int = Field(..., description="Job ID")
    company_id: int = Field(..., description="Company ID")
    language_code: str = Field(..., description="Target language code")
    status: str = Field(..., description="Job status: pending, running, completed, failed")
    total_fields: int = Field(..., description="Total fields to translate")
    translated_fields: int = Field(..., description="Fields translated so far")
    progress_percentage: float = Field(..., description="Progress percentage")
    error_message: str | None = Field(None, description="Error message if failed")
    created_at: datetime = Field(..., description="Job creation time")
    started_at: datetime | None = Field(None, description="Job start time")
    completed_at: datetime | None = Field(None, description="Job completion time")

    model_config = {"from_attributes": True}


class TranslateResponse(BaseModel):
    """Response after requesting translation."""

    company_id: int = Field(..., description="Company ID")
    language_code: str = Field(..., description="Target language code")
    fields_queued: int = Field(..., description="Number of fields queued for translation")
    message: str = Field(..., description="Status message")
    job: TranslationJobResponse | None = Field(None, description="Translation job details")


# Update forward reference
LanguageTranslationStatus.model_rebuild()
