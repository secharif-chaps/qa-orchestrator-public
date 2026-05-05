"""Add company_patents section + company_patent_item items

Revision ID: 041
Revises: 040
Create Date: 2026-04-22

Creates the two tables backing the patents agent produced by TAR-1578:
- ``company_patents`` (1:1 with companies): insights + aggregate indicators
- ``company_patent_item`` (1:N): individual patent publications

Also extends ``task_type_enum`` with the ``patents`` value so a patents
task can be persisted and surfaced in the task progress UI.
"""

import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

from alembic import op

revision = "041"
down_revision = "040"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Create patents tables and extend task_type_enum."""

    # ------------------------------------------------------------------
    # 1. Extend task_type_enum with the new 'patents' value.
    # ------------------------------------------------------------------
    op.execute("ALTER TYPE screen_schema.task_type_enum ADD VALUE IF NOT EXISTS 'patents'")

    # ------------------------------------------------------------------
    # 2. Create company_patents (1:1 section).
    # ------------------------------------------------------------------
    op.create_table(
        "company_patents",
        sa.Column("company_id", sa.Integer(), nullable=False),
        sa.Column("insights", sa.Text(), nullable=True),
        sa.Column("total_patents_count", sa.Integer(), nullable=False, server_default="0"),
        sa.Column("top_cpc_domains", postgresql.JSONB(astext_type=sa.Text()), nullable=False, server_default="[]"),
        sa.Column("filing_trend", postgresql.JSONB(astext_type=sa.Text()), nullable=False, server_default="{}"),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.PrimaryKeyConstraint("company_id"),
        sa.ForeignKeyConstraint(["company_id"], ["screen_schema.companies.id"], ondelete="CASCADE"),
        schema="screen_schema",
    )

    # ------------------------------------------------------------------
    # 3. Create company_patent_item (1:N items).
    # ------------------------------------------------------------------
    op.create_table(
        "company_patent_item",
        sa.Column("id", sa.Integer(), nullable=False, autoincrement=True),
        sa.Column("company_id", sa.Integer(), nullable=False),
        sa.Column("patent_number", sa.Text(), nullable=False),
        sa.Column("title", sa.Text(), nullable=True),
        sa.Column("abstract", sa.Text(), nullable=True),
        sa.Column("inventors", postgresql.JSONB(astext_type=sa.Text()), nullable=False, server_default="[]"),
        sa.Column("applicants", postgresql.JSONB(astext_type=sa.Text()), nullable=False, server_default="[]"),
        sa.Column("publication_date", sa.Date(), nullable=True),
        sa.Column("cpc_codes", postgresql.JSONB(astext_type=sa.Text()), nullable=False, server_default="[]"),
        sa.Column("is_key_patent", sa.Boolean(), nullable=False, server_default=sa.false()),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.PrimaryKeyConstraint("id"),
        sa.ForeignKeyConstraint(["company_id"], ["screen_schema.companies.id"], ondelete="CASCADE"),
        sa.UniqueConstraint("company_id", "patent_number", name="uq_company_patent_item_doc"),
        schema="screen_schema",
    )

    op.create_index(
        "idx_company_patent_item_company_id",
        "company_patent_item",
        ["company_id"],
        schema="screen_schema",
    )


def downgrade() -> None:
    """Drop patent tables.

    Note: PostgreSQL cannot cheaply remove values from an enum, so the
    ``'patents'`` value added to ``task_type_enum`` is left in place.
    Any lingering rows of ``tasks.type = 'patents'`` must be deleted
    before a clean rollback (CASCADE is intentionally not used).
    """
    op.drop_index("idx_company_patent_item_company_id", table_name="company_patent_item", schema="screen_schema")
    op.drop_table("company_patent_item", schema="screen_schema")
    op.drop_table("company_patents", schema="screen_schema")
