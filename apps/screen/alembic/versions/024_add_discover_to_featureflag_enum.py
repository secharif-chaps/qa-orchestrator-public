"""Add discover to featureflag enum

Revision ID: 024
Revises: 023
Create Date: 2026-01-23

This migration adds the 'discover' value to the featureflag PostgreSQL enum type.
The DISCOVER feature flag allows organizations to configure an external Discover
dashboard URL that users can access from the home page.
"""

from alembic import op

# revision identifiers, used by Alembic.
revision = "024"
down_revision = "023"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Add 'discover' value to the featureflag enum type."""
    # PostgreSQL's ALTER TYPE ADD VALUE is idempotent with IF NOT EXISTS
    op.execute("ALTER TYPE featureflag ADD VALUE IF NOT EXISTS 'discover'")


def downgrade() -> None:
    """Downgrade is a no-op - PostgreSQL enum values cannot be removed.

    Note: PostgreSQL does not support removing values from an existing enum type.
    The 'discover' value will remain in the enum but will not be used if this
    migration is rolled back. Future data using this value should be handled
    separately if a full rollback is required.
    """
    pass
