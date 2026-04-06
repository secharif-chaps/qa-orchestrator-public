"""009 add stream to modulename and featureflag enums

Revision ID: e5f6a7b8c9d0
Revises: d4e5f6a7b8c9
Create Date: 2026-03-30 10:00:00.000000

Adds 'stream' to both the modulename and featureflag enums
in global_schema to support the Stream module (multi-channel
event distribution).
"""

from typing import Sequence, Union

from alembic import op

# revision identifiers, used by Alembic.
revision: str = "e5f6a7b8c9d0"
down_revision: Union[str, Sequence[str], None] = "d4e5f6a7b8c9"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

SCHEMA = "global_schema"


def upgrade() -> None:
    """Add 'stream' to modulename and featureflag enums."""
    op.execute(f"ALTER TYPE {SCHEMA}.modulename ADD VALUE IF NOT EXISTS 'stream'")
    op.execute(f"ALTER TYPE {SCHEMA}.featureflag ADD VALUE IF NOT EXISTS 'stream'")


def downgrade() -> None:
    raise NotImplementedError(
        "PostgreSQL does not support removing enum values. "
        "To rollback manually: create a new type without 'stream', "
        "migrate all affected columns, then drop the old type."
    )
