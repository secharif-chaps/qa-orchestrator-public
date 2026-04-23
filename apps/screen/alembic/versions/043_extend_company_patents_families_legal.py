"""Extend company_patents / company_patent_item with families + legal fields

Revision ID: 043
Revises: 042
Create Date: 2026-04-23

TAR-1579 extension of the patents agent — adds the columns required to
persist the families + legal status analysis on top of the tables
introduced in TAR-1578:

- ``company_patents``: ``geographic_coverage`` (JSONB), ``status_breakdown``
  (JSONB), ``portfolio_strength`` (TEXT)
- ``company_patent_item``: ``family_size`` (INTEGER), ``family_countries``
  (TEXT[]), ``legal_status`` (ENUM patent_legal_status_enum)

The enum mirrors ``SimplifiedLegalStatus`` from the EPO infra
(``active`` / ``expired`` / ``pending`` / ``unknown``).
"""

import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

from alembic import op

revision = "043"
down_revision = "042"
branch_labels = None
depends_on = None

SCHEMA = "screen_schema"


def upgrade() -> None:
    """Create the legal-status enum and add six columns to the patents tables."""

    # ------------------------------------------------------------------
    # 1. Create patent_legal_status_enum (idempotent).
    # ------------------------------------------------------------------
    op.execute(
        f"DO $$ BEGIN "
        f"CREATE TYPE {SCHEMA}.patent_legal_status_enum AS ENUM "
        f"('active', 'expired', 'pending', 'unknown'); "
        f"EXCEPTION WHEN duplicate_object THEN NULL; END $$"
    )

    # ------------------------------------------------------------------
    # 2. Extend company_patents with families + legal aggregates.
    # ------------------------------------------------------------------
    op.add_column(
        "company_patents",
        sa.Column(
            "geographic_coverage",
            postgresql.JSONB(astext_type=sa.Text()),
            nullable=False,
            server_default="{}",
        ),
        schema=SCHEMA,
    )
    op.add_column(
        "company_patents",
        sa.Column(
            "status_breakdown",
            postgresql.JSONB(astext_type=sa.Text()),
            nullable=False,
            server_default="{}",
        ),
        schema=SCHEMA,
    )
    op.add_column(
        "company_patents",
        sa.Column("portfolio_strength", sa.Text(), nullable=True),
        schema=SCHEMA,
    )

    # ------------------------------------------------------------------
    # 3. Extend company_patent_item with per-item families + legal fields.
    # ------------------------------------------------------------------
    op.add_column(
        "company_patent_item",
        sa.Column("family_size", sa.Integer(), nullable=False, server_default="0"),
        schema=SCHEMA,
    )
    op.add_column(
        "company_patent_item",
        sa.Column(
            "family_countries",
            postgresql.ARRAY(sa.Text()),
            nullable=False,
            server_default="{}",
        ),
        schema=SCHEMA,
    )
    op.add_column(
        "company_patent_item",
        sa.Column(
            "legal_status",
            postgresql.ENUM(
                "active",
                "expired",
                "pending",
                "unknown",
                name="patent_legal_status_enum",
                schema=SCHEMA,
                create_type=False,
            ),
            nullable=True,
        ),
        schema=SCHEMA,
    )


def downgrade() -> None:
    """Drop the six columns and the legal-status enum."""
    op.drop_column("company_patent_item", "legal_status", schema=SCHEMA)
    op.drop_column("company_patent_item", "family_countries", schema=SCHEMA)
    op.drop_column("company_patent_item", "family_size", schema=SCHEMA)

    op.drop_column("company_patents", "portfolio_strength", schema=SCHEMA)
    op.drop_column("company_patents", "status_breakdown", schema=SCHEMA)
    op.drop_column("company_patents", "geographic_coverage", schema=SCHEMA)

    op.execute(f"DROP TYPE IF EXISTS {SCHEMA}.patent_legal_status_enum")
