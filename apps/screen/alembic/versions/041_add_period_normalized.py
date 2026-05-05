"""Add period_normalized and date_normalized columns for chronological sorting

Revision ID: 041
Revises: 040
Create Date: 2026-04-27

Adds:
- period_normalized (DATE, nullable) to company_financial_metric
- date_normalized (DATE, nullable) to company_funding_round

Both columns store an ISO date anchoring the START of the reporting period /
funding event. The LLM infers the date from the free-form period/date label
(e.g. "FY2023" → "2023-01-01" for a US company, "Q3 2024" → "2024-07-01").
null when the period cannot be anchored (TTM, LTM, undateable labels).

Used for ORDER BY period_normalized ASC NULLS LAST in API responses so that
historical metrics are returned in chronological order.
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic
revision = "041"
down_revision = "040"
branch_labels = None
depends_on = None

SCREEN_SCHEMA = "screen_schema"


def upgrade() -> None:
    """Add period_normalized to company_financial_metric and date_normalized to company_funding_round."""
    op.add_column(
        "company_financial_metric",
        sa.Column("period_normalized", sa.Date(), nullable=True),
        schema=SCREEN_SCHEMA,
    )
    op.add_column(
        "company_funding_round",
        sa.Column("date_normalized", sa.Date(), nullable=True),
        schema=SCREEN_SCHEMA,
    )


def downgrade() -> None:
    """Remove period_normalized and date_normalized columns."""
    op.drop_column("company_financial_metric", "period_normalized", schema=SCREEN_SCHEMA)
    op.drop_column("company_funding_round", "date_normalized", schema=SCREEN_SCHEMA)
