"""Add context column to company_financial_metric

Revision ID: 036
Revises: 035
Create Date: 2026-04-15

Adds:
- context (TEXT, nullable) to company_financial_metric table

Used to store extra context from the LLM about a metric, or to preserve
the original metric name when the backend normalization renames it to a
canonical key (revenue, ebitda, netIncome, freeCashFlow).
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic
revision = "036"
down_revision = "035"
branch_labels = None
depends_on = None

SCREEN_SCHEMA = "screen_schema"


def upgrade() -> None:
    """Add context column to company_financial_metric."""
    op.add_column(
        "company_financial_metric",
        sa.Column("context", sa.Text(), nullable=True),
        schema=SCREEN_SCHEMA,
    )


def downgrade() -> None:
    """Remove context column from company_financial_metric."""
    op.drop_column(
        "company_financial_metric",
        "context",
        schema=SCREEN_SCHEMA,
    )
