"""Add title and date columns to press items and CSR initiatives

Revision ID: 043
Revises: 042
Create Date: 2026-04-15

Adds:
- title (VARCHAR(500), nullable) to company_press_items table
- date (DATE, nullable) to company_press_items table
- title (VARCHAR(500), nullable) to company_csr_initiatives table
- date (DATE, nullable) to company_csr_initiatives table

These fields allow the LangGraph extraction agents to store a short title
and the publication/initiative date for each press item and CSR initiative.
Existing rows will have NULL values for both columns (no regression).
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic
revision = "043"
down_revision = "042"
branch_labels = None
depends_on = None

SCREEN_SCHEMA = "screen_schema"


def upgrade() -> None:
    """Add title and date columns to press items and CSR initiatives."""
    # Press items
    op.add_column(
        "company_press_items",
        sa.Column("title", sa.String(500), nullable=True),
        schema=SCREEN_SCHEMA,
    )
    op.add_column(
        "company_press_items",
        sa.Column("date", sa.Date(), nullable=True),
        schema=SCREEN_SCHEMA,
    )

    # CSR initiatives
    op.add_column(
        "company_csr_initiatives",
        sa.Column("title", sa.String(500), nullable=True),
        schema=SCREEN_SCHEMA,
    )
    op.add_column(
        "company_csr_initiatives",
        sa.Column("date", sa.Date(), nullable=True),
        schema=SCREEN_SCHEMA,
    )


def downgrade() -> None:
    """Remove title and date columns from press items and CSR initiatives."""
    op.drop_column("company_csr_initiatives", "date", schema=SCREEN_SCHEMA)
    op.drop_column("company_csr_initiatives", "title", schema=SCREEN_SCHEMA)
    op.drop_column("company_press_items", "date", schema=SCREEN_SCHEMA)
    op.drop_column("company_press_items", "title", schema=SCREEN_SCHEMA)
