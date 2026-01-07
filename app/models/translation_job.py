"""SQLAlchemy model for translation jobs.

Tracks ongoing and completed translation jobs for progress monitoring.
"""

import enum
from sqlalchemy import Column, Integer, String, DateTime, Enum, Text
from sqlalchemy.sql import func

from app.database import Base


class TranslationJobStatus(enum.Enum):
    """Translation job status enum."""

    pending = "pending"
    running = "running"
    completed = "completed"
    failed = "failed"


class TranslationJob(Base):
    """Translation job record for tracking progress.

    Tracks translation jobs for a company/language pair, including
    progress information and task IDs for Celery integration.

    Attributes:
        id: Primary key
        company_id: Company being translated
        language_code: Target language code
        status: Job status (pending, running, completed, failed)
        celery_task_id: Celery task ID for tracking
        total_fields: Total number of fields to translate
        translated_fields: Number of fields translated so far
        error_message: Error message if job failed
        created_at: Job creation timestamp
        started_at: Job start timestamp
        completed_at: Job completion timestamp
    """

    __tablename__ = "translation_jobs"

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(Integer, nullable=False, index=True)
    language_code = Column(String(5), nullable=False)
    status = Column(
        Enum(
            TranslationJobStatus,
            values_callable=lambda obj: [e.value for e in obj],
            name="translation_job_status_enum",
        ),
        nullable=False,
        default=TranslationJobStatus.pending,
    )
    celery_task_id = Column(String(255), nullable=True, index=True)
    total_fields = Column(Integer, nullable=False, default=0)
    translated_fields = Column(Integer, nullable=False, default=0)
    error_message = Column(Text, nullable=True)

    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False,
    )
    started_at = Column(DateTime(timezone=True), nullable=True)
    completed_at = Column(DateTime(timezone=True), nullable=True)

    @property
    def progress_percentage(self) -> float:
        """Calculate progress percentage."""
        if self.total_fields == 0:
            return 0.0
        return round((self.translated_fields / self.total_fields) * 100, 1)

    def __repr__(self) -> str:
        return (
            f"<TranslationJob(id={self.id}, company_id={self.company_id}, "
            f"lang={self.language_code}, status={self.status.value}, "
            f"progress={self.translated_fields}/{self.total_fields})>"
        )
