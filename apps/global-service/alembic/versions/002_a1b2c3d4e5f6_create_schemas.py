"""002 Create global_schema

Revision ID: a1b2c3d4e5f6
Revises: ea866042deeb
Create Date: 2026-01-22 10:00:00.000000

This migration creates the global_schema for organization-scoped resources.
"""
from typing import Sequence, Union

from alembic import op


# revision identifiers, used by Alembic.
revision: str = 'a1b2c3d4e5f6'
down_revision: Union[str, Sequence[str], None] = 'ea866042deeb'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Create global_schema."""
    op.execute("CREATE SCHEMA IF NOT EXISTS global_schema")


def downgrade() -> None:
    """Drop global_schema."""
    op.execute("DROP SCHEMA IF EXISTS global_schema CASCADE")
