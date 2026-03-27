"""Add company_enrichments table for external API data

Revision ID: 032
Revises: 031
Create Date: 2026-03-26

Stores pre-fetched structured data from external APIs (Pappers, WorldCheck)
for use by LangGraph agents via function tools.
"""

import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import JSONB

from alembic import op

# revision identifiers, used by Alembic.
revision = "032"
down_revision = "031"
branch_labels = None
depends_on = None

SCHEMA = "screen_schema"
TABLE = "company_enrichments"


def upgrade() -> None:
    """Create company_enrichments table."""
    op.create_table(
        TABLE,
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column(
            "company_id",
            sa.Integer,
            sa.ForeignKey(f"{SCHEMA}.companies.id", ondelete="CASCADE"),
            nullable=False,
        ),
        sa.Column("source", sa.String, nullable=False),
        sa.Column("data", JSONB, nullable=True),
        sa.Column("status", sa.String, nullable=False, server_default="pending"),
        sa.Column("error", sa.String, nullable=True),
        sa.Column("fetched_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            nullable=False,
        ),
        sa.UniqueConstraint("company_id", "source", name=f"uq_{TABLE}_company_source"),
        schema=SCHEMA,
    )
    op.create_index(
        f"idx_{TABLE}_company_id",
        TABLE,
        ["company_id"],
        schema=SCHEMA,
    )


def downgrade() -> None:
    """Drop company_enrichments table."""
    op.drop_index(f"idx_{TABLE}_company_id", table_name=TABLE, schema=SCHEMA)
    op.drop_table(TABLE, schema=SCHEMA)
