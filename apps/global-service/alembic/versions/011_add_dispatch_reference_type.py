"""011 add dispatch to reference_type enum

Revision ID: 0e864470a6b4
Revises: f6a7b8c9d0e1
Create Date: 2026-03-31 10:00:00.000000

Adds 'dispatch' to the reference_type enum in global_schema
to support Stream delivery credit tracking.
"""

from typing import Sequence, Union

from alembic import op

# revision identifiers, used by Alembic.
revision: str = "0e864470a6b4"
down_revision: Union[str, Sequence[str], None] = "f6a7b8c9d0e1"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

SCHEMA = "global_schema"


def upgrade() -> None:
    """Add 'dispatch' to reference_type enum."""
    op.execute(f"ALTER TYPE {SCHEMA}.reference_type ADD VALUE IF NOT EXISTS 'dispatch'")


def downgrade() -> None:
    """PostgreSQL doesn't support removing enum values directly."""
    pass