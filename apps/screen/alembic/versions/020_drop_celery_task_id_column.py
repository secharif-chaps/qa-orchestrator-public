"""Drop celery_task_id column from translation_jobs.

Translation processing moved from Celery to FastAPI BackgroundTasks.
See docs/architecture/adr-001-translation-background-tasks.md

Revision ID: 020
Revises: 019
Create Date: 2026-01-16
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic.
revision = "020"
down_revision = "019"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Drop celery_task_id column and index."""
    # Drop the index first
    op.drop_index("ix_translation_jobs_celery_task_id", table_name="translation_jobs")

    # Drop the column
    op.drop_column("translation_jobs", "celery_task_id")


def downgrade() -> None:
    """Restore celery_task_id column and index."""
    # Add the column back
    op.add_column(
        "translation_jobs",
        sa.Column("celery_task_id", sa.String(255), nullable=True),
    )

    # Recreate the index
    op.create_index(
        "ix_translation_jobs_celery_task_id",
        "translation_jobs",
        ["celery_task_id"],
    )
