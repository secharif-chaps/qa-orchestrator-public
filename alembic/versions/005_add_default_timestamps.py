"""add default timestamps to datetime columns

Revision ID: 005
Revises: 004
Create Date: 2025-11-24

"""
from alembic import op
import sqlalchemy as sa

# revision identifiers, used by Alembic.
revision = '005'
down_revision = '004'
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Add DEFAULT NOW() to datetime columns that were converted to timezone-aware.

    The previous migration (004) converted columns to TIMESTAMPTZ but didn't
    set the DEFAULT values, which caused SQLAlchemy to insert NULL values.
    """

    # Add defaults to companies table
    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN created_at SET DEFAULT NOW()
    """)

    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN updated_at SET DEFAULT NOW()
    """)

    # Add defaults to tasks table
    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN created_at SET DEFAULT NOW()
    """)

    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN updated_at SET DEFAULT NOW()
    """)

    # Add defaults to task_dependencies table
    op.execute("""
        ALTER TABLE task_dependencies
        ALTER COLUMN created_at SET DEFAULT NOW()
    """)


def downgrade() -> None:
    """Remove DEFAULT values from datetime columns."""

    # Remove defaults from companies table
    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN created_at DROP DEFAULT
    """)

    op.execute("""
        ALTER TABLE companies
        ALTER COLUMN updated_at DROP DEFAULT
    """)

    # Remove defaults from tasks table
    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN created_at DROP DEFAULT
    """)

    op.execute("""
        ALTER TABLE tasks
        ALTER COLUMN updated_at DROP DEFAULT
    """)

    # Remove defaults from task_dependencies table
    op.execute("""
        ALTER TABLE task_dependencies
        ALTER COLUMN created_at DROP DEFAULT
    """)
