"""Add is_orphaned column to folders table

This migration adds the is_orphaned boolean column to the folders table.
When a folder owner leaves the organization or is disabled, this flag
is set to true to indicate the folder requires admin action to claim
or reassign ownership.

Revision ID: 009
Revises: 008
Create Date: 2025-12-16

"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic.
revision = "009"
down_revision = "008"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Add is_orphaned boolean column to folders table."""

    op.add_column("folders", sa.Column("is_orphaned", sa.Boolean(), server_default=sa.text("false"), nullable=False))


def downgrade() -> None:
    """Remove is_orphaned column from folders table."""

    op.drop_column("folders", "is_orphaned")
