"""Add stream to featureflag enum

Revision ID: 037
Revises: 036
Create Date: 2026-04-01

This migration adds the 'stream' value to the featureflag PostgreSQL enum type.
The STREAM feature flag enables multi-channel event distribution (Teams, Slack, Webhook)
for organizations.
"""

from alembic import op

# revision identifiers, used by Alembic.
revision = "037"
down_revision = "036"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Add 'stream' value to the featureflag enum type."""
    op.execute("ALTER TYPE featureflag ADD VALUE IF NOT EXISTS 'stream'")


def downgrade() -> None:
    """Downgrade is a no-op - PostgreSQL enum values cannot be removed."""
    pass
