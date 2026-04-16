"""Add outbox table for transactional event production

Revision ID: 036
Revises: 035
Create Date: 2026-03-31

Stores domain events (company created, deleted, refreshed, analysis completed)
to be relayed asynchronously to the Stream service.
"""

import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import JSONB

from alembic import op

# revision identifiers, used by Alembic.
revision = "036"
down_revision = "035"
branch_labels = None
depends_on = None

SCHEMA = "screen_schema"
TABLE = "outbox"


def upgrade() -> None:
    """Create outbox table with partial index on unpublished events."""
    op.create_table(
        TABLE,
        sa.Column("id", sa.Integer, primary_key=True, autoincrement=True),
        sa.Column("event_type", sa.String(100), nullable=False),
        sa.Column("aggregate_type", sa.String(50), nullable=False),
        sa.Column("aggregate_id", sa.String(100), nullable=False),
        sa.Column("folder_id", sa.String(100), nullable=True),
        sa.Column("organization_id", sa.String(100), nullable=False),
        sa.Column("payload", JSONB, nullable=False, server_default="{}"),
        sa.Column("summary", sa.String(500), nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column("published_at", sa.DateTime(timezone=True), nullable=True),
        schema=SCHEMA,
    )

    # Partial index: only unpublished events (relay query path)
    op.execute(f"CREATE INDEX idx_{TABLE}_unpublished ON {SCHEMA}.{TABLE} (created_at) WHERE published_at IS NULL")

    op.create_index(
        f"idx_{TABLE}_organization_id",
        TABLE,
        ["organization_id"],
        schema=SCHEMA,
    )


def downgrade() -> None:
    """Drop outbox table."""
    op.drop_index(f"idx_{TABLE}_organization_id", table_name=TABLE, schema=SCHEMA)
    op.execute(f"DROP INDEX IF EXISTS {SCHEMA}.idx_{TABLE}_unpublished")
    op.drop_table(TABLE, schema=SCHEMA)
