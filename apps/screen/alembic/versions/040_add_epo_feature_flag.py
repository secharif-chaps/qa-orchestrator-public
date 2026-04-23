"""Add epo to featureflag enum

Revision ID: 040
Revises: 039
Create Date: 2026-04-20

This migration adds the 'epo' value to the featureflag PostgreSQL enum type.
The EPO feature flag enables the European Patent Office OPS API integration
for organizations, providing patent search and bibliographic data.
"""

from alembic import op

revision = "040"
down_revision = "039"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Add 'epo' value to the featureflag enum type."""
    op.execute("ALTER TYPE featureflag ADD VALUE IF NOT EXISTS 'epo'")


def downgrade() -> None:
    """Downgrade is a no-op - PostgreSQL enum values cannot be removed."""
    pass
