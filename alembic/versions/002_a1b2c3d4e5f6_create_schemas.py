"""002 Create global_schema and screen_schema

Revision ID: a1b2c3d4e5f6
Revises: ea866042deeb
Create Date: 2026-01-22 10:00:00.000000

This migration creates the two PostgreSQL schemas:
- global_schema: For organization-scoped resources (future tables)
- screen_schema: For screen-specific data (migrated from monolith)
"""
from typing import Sequence, Union

from alembic import op


# revision identifiers, used by Alembic.
revision: str = 'a1b2c3d4e5f6'
down_revision: Union[str, Sequence[str], None] = 'ea866042deeb'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Create global_schema and screen_schema."""
    # Create schemas using raw SQL
    op.execute("CREATE SCHEMA IF NOT EXISTS global_schema")
    op.execute("CREATE SCHEMA IF NOT EXISTS screen_schema")


def downgrade() -> None:
    """Drop global_schema and screen_schema."""
    # Drop schemas (CASCADE will drop all objects within)
    op.execute("DROP SCHEMA IF EXISTS screen_schema CASCADE")
    op.execute("DROP SCHEMA IF EXISTS global_schema CASCADE")
