"""convert datetime columns to timezone aware

Revision ID: 004
Revises: 003
Create Date: 2025-11-24

"""
from alembic import op
import sqlalchemy as sa

# revision identifiers, used by Alembic.
revision = '004'
down_revision = '003'
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Convert datetime columns to timezone-aware (TIMESTAMPTZ).

    This migration converts all naive datetime columns to timezone-aware
    columns across companies and tasks tables. Existing naive timestamps
    are assumed to be UTC and converted accordingly.
    """

    # Convert companies table datetime columns
    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN created_at TYPE TIMESTAMP WITH TIME ZONE
        USING created_at AT TIME ZONE 'UTC'
    """)

    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN updated_at TYPE TIMESTAMP WITH TIME ZONE
        USING updated_at AT TIME ZONE 'UTC'
    """)

    # Convert tasks table datetime columns
    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN created_at TYPE TIMESTAMP WITH TIME ZONE
        USING created_at AT TIME ZONE 'UTC'
    """)

    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN updated_at TYPE TIMESTAMP WITH TIME ZONE
        USING updated_at AT TIME ZONE 'UTC'
    """)

    # Convert tasks started_at and completed_at columns if they exist
    op.execute("""
        DO $$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'tasks' AND column_name = 'started_at'
            ) THEN
                ALTER TABLE tasks
                ALTER COLUMN started_at TYPE TIMESTAMP WITH TIME ZONE
                USING started_at AT TIME ZONE 'UTC';
            END IF;
        END $$;
    """)

    op.execute("""
        DO $$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'tasks' AND column_name = 'completed_at'
            ) THEN
                ALTER TABLE tasks
                ALTER COLUMN completed_at TYPE TIMESTAMP WITH TIME ZONE
                USING completed_at AT TIME ZONE 'UTC';
            END IF;
        END $$;
    """)


def downgrade() -> None:
    """Convert timezone-aware datetime columns back to naive timestamps.

    Note: This will lose timezone information. All timestamps will be
    converted to their UTC equivalent as naive timestamps.
    """

    # Convert companies table back to naive timestamps
    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN created_at TYPE TIMESTAMP WITHOUT TIME ZONE
        USING created_at AT TIME ZONE 'UTC'
    """)

    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN updated_at TYPE TIMESTAMP WITHOUT TIME ZONE
        USING updated_at AT TIME ZONE 'UTC'
    """)

    # Convert tasks table back to naive timestamps
    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN created_at TYPE TIMESTAMP WITHOUT TIME ZONE
        USING created_at AT TIME ZONE 'UTC'
    """)

    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN updated_at TYPE TIMESTAMP WITHOUT TIME ZONE
        USING updated_at AT TIME ZONE 'UTC'
    """)

    # Convert tasks started_at and completed_at back if they exist
    op.execute("""
        DO $$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'tasks' AND column_name = 'started_at'
            ) THEN
                ALTER TABLE tasks
                ALTER COLUMN started_at TYPE TIMESTAMP WITHOUT TIME ZONE
                USING started_at AT TIME ZONE 'UTC';
            END IF;
        END $$;
    """)

    op.execute("""
        DO $$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'tasks' AND column_name = 'completed_at'
            ) THEN
                ALTER TABLE tasks
                ALTER COLUMN completed_at TYPE TIMESTAMP WITHOUT TIME ZONE
                USING completed_at AT TIME ZONE 'UTC';
            END IF;
        END $$;
    """)
