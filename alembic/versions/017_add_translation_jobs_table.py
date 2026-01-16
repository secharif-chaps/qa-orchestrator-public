"""Add translation_jobs table for progress tracking

Revision ID: 017
Revises: 016
Create Date: 2025-01-06

Creates the translation_jobs table to track ongoing and completed
translation jobs with progress information.
"""

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql


# revision identifiers, used by Alembic
revision = '017'
down_revision = '016'
branch_labels = None
depends_on = None


def upgrade():
    """Create translation_jobs table."""

    print("Creating translation_jobs table...")

    # Create enum type for job status (IF NOT EXISTS for idempotency)
    op.execute("""
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'translation_job_status_enum') THEN
                CREATE TYPE translation_job_status_enum AS ENUM (
                    'pending', 'running', 'completed', 'failed'
                );
            END IF;
        END
        $$;
    """)

    op.create_table(
        'translation_jobs',
        sa.Column('id', sa.Integer(), autoincrement=True, nullable=False),
        sa.Column('company_id', sa.Integer(), nullable=False),
        sa.Column('language_code', sa.String(5), nullable=False),
        sa.Column(
            'status',
            postgresql.ENUM(
                'pending', 'running', 'completed', 'failed',
                name='translation_job_status_enum',
                create_type=False
            ),
            nullable=False,
            server_default='pending'
        ),
        sa.Column('celery_task_id', sa.String(255), nullable=True),
        sa.Column('total_fields', sa.Integer(), nullable=False, server_default='0'),
        sa.Column('translated_fields', sa.Integer(), nullable=False, server_default='0'),
        sa.Column('error_message', sa.Text(), nullable=True),
        sa.Column(
            'created_at',
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False
        ),
        sa.Column('started_at', sa.DateTime(timezone=True), nullable=True),
        sa.Column('completed_at', sa.DateTime(timezone=True), nullable=True),
        sa.PrimaryKeyConstraint('id'),
    )

    # Create indexes
    print("Creating indexes...")

    op.create_index(
        'ix_translation_jobs_company_id',
        'translation_jobs',
        ['company_id']
    )

    op.create_index(
        'ix_translation_jobs_celery_task_id',
        'translation_jobs',
        ['celery_task_id']
    )

    # Composite index for finding active jobs per company/language
    op.create_index(
        'ix_translation_jobs_company_language_status',
        'translation_jobs',
        ['company_id', 'language_code', 'status']
    )

    print("Translation jobs table created successfully!")


def downgrade():
    """Drop translation_jobs table."""

    print("Dropping translation_jobs table...")

    # Drop indexes
    op.drop_index('ix_translation_jobs_company_language_status', table_name='translation_jobs')
    op.drop_index('ix_translation_jobs_celery_task_id', table_name='translation_jobs')
    op.drop_index('ix_translation_jobs_company_id', table_name='translation_jobs')

    # Drop table
    op.drop_table('translation_jobs')

    # Drop enum type
    op.execute("DROP TYPE IF EXISTS translation_job_status_enum")

    print("Translation jobs table dropped successfully!")
