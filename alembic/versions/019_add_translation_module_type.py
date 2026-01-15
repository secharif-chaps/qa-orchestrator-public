"""Add translation value to modulename enum.

Revision ID: 019
Revises: 018
Create Date: 2025-01-15

This migration adds the 'translation' value to the modulename enum type,
enabling the translation module feature for organizations.
"""
from alembic import op


# revision identifiers, used by Alembic.
revision = '019'
down_revision = '018'
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Add 'translation' to the modulename enum type."""
    # PostgreSQL allows adding new values to an enum type
    op.execute("ALTER TYPE modulename ADD VALUE IF NOT EXISTS 'translation'")


def downgrade() -> None:
    """Remove 'translation' from the modulename enum type.

    Note: PostgreSQL doesn't support removing values from an enum directly.
    A full recreation would be needed, but since this would require recreating
    the organization_modules table, we'll leave a warning.
    """
    # Cannot easily remove enum values in PostgreSQL
    # Would need to: create new enum, migrate data, drop old enum, rename new
    # For safety, we just print a warning
    print("WARNING: Cannot remove 'translation' from modulename enum. "
          "Manual intervention required if downgrade is truly needed.")
