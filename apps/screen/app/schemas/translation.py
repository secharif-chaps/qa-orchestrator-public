"""Pydantic schemas for translation endpoints."""

from datetime import datetime

from pydantic import BaseModel, ConfigDict, Field


class LanguageResponse(BaseModel):
    """Language information response."""

    code: str = Field(..., description="ISO language code (e.g., 'fr', 'es')")
    name: str = Field(..., description="Language name in English")

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {"code": "fr", "name": "French"},
            ],
        },
    )


class LanguageTranslationStatus(BaseModel):
    """Translation status for a single language."""

    language_name: str = Field(..., description="Language name in English")
    status: str = Field(..., description="Translation status: 'complete', 'partial', or 'none'")
    fields_translated: int = Field(..., description="Number of translated fields")
    fields_total: int = Field(..., description="Total translatable fields")
    percentage: float = Field(..., description="Translation completion percentage")
    active_job: "TranslationJobResponse | None" = Field(None, description="Active translation job if any")


class CompanyTranslationStatusResponse(BaseModel):
    """Translation status response for a company."""

    company_id: int = Field(..., description="Company ID")
    translations: dict[str, LanguageTranslationStatus] = Field(..., description="Translation status per language code")

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "company_id": 42,
                    "translations": {
                        "fr": {
                            "language_name": "French",
                            "status": "complete",
                            "fields_translated": 15,
                            "fields_total": 15,
                            "percentage": 100.0,
                            "active_job": None,
                        },
                        "es": {
                            "language_name": "Spanish",
                            "status": "none",
                            "fields_translated": 0,
                            "fields_total": 15,
                            "percentage": 0.0,
                            "active_job": None,
                        },
                    },
                },
            ],
        },
    )


class TranslateRequest(BaseModel):
    """Request to translate company fields to a language."""

    language_code: str = Field(
        ...,
        description="Target language code (e.g., 'es', 'de', 'pt')",
        min_length=2,
        max_length=5,
    )

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {"language_code": "fr"},
            ],
        },
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

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "id": 7,
                    "company_id": 42,
                    "language_code": "fr",
                    "status": "completed",
                    "total_fields": 15,
                    "translated_fields": 15,
                    "progress_percentage": 100.0,
                    "error_message": None,
                    "created_at": "2025-06-15T10:30:00Z",
                    "started_at": "2025-06-15T10:30:01Z",
                    "completed_at": "2025-06-15T10:30:03Z",
                },
            ],
        },
    )


class TranslateResponse(BaseModel):
    """Response after requesting translation."""

    company_id: int = Field(..., description="Company ID")
    language_code: str = Field(..., description="Target language code")
    fields_queued: int = Field(..., description="Number of fields queued for translation")
    message: str = Field(..., description="Status message")
    job: TranslationJobResponse | None = Field(None, description="Translation job details")

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "company_id": 42,
                    "language_code": "fr",
                    "fields_queued": 15,
                    "message": "Translation started for 15 fields.",
                    "job": {
                        "id": 7,
                        "company_id": 42,
                        "language_code": "fr",
                        "status": "pending",
                        "total_fields": 15,
                        "translated_fields": 0,
                        "progress_percentage": 0.0,
                        "error_message": None,
                        "created_at": "2025-06-15T10:30:00Z",
                        "started_at": None,
                        "completed_at": None,
                    },
                },
            ],
        },
    )


# Update forward reference
LanguageTranslationStatus.model_rebuild()
