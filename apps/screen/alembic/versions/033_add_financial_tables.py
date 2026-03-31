"""Add financial tables

Revision ID: 033
Revises: 032
Create Date: 2026-03-27

Creates 3 new financial tables for normalized company financial data storage:
- company_financial: Financial overview section (1:1 with companies)
- company_financial_metric: Individual financial metrics (1:N)
- company_funding_round: Funding round history (1:N)

Also adds 'financial' value to task_type_enum.
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic
revision = "033"
down_revision = "032"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Create financial tables and update task_type_enum."""

    # ============================================================================
    # 1. ADD 'financial' VALUE TO task_type_enum
    # ============================================================================
    print("Adding 'financial' value to task_type_enum...")
    op.execute("ALTER TYPE screen_schema.task_type_enum ADD VALUE IF NOT EXISTS 'financial'")

    # ============================================================================
    # 2. CREATE COMPANY_FINANCIAL TABLE (1:1 section)
    # ============================================================================
    print("Creating company_financial table...")
    op.create_table(
        "company_financial",
        # Primary key is also foreign key to companies - enforces 1:1 relationship
        sa.Column("company_id", sa.Integer(), nullable=False),
        # Insights with SourcedValue pattern
        sa.Column("insights", sa.Text(), nullable=True),
        sa.Column("insights_source", sa.Text(), nullable=True),
        # Company type (public, private, etc.)
        sa.Column("company_type", sa.Text(), nullable=True),
        sa.Column("company_type_source", sa.Text(), nullable=True),
        # Stock ticker symbol
        sa.Column("ticker_symbol", sa.Text(), nullable=True),
        sa.Column("ticker_symbol_source", sa.Text(), nullable=True),
        # Stock exchange (NYSE, NASDAQ, etc.)
        sa.Column("stock_exchange", sa.Text(), nullable=True),
        sa.Column("stock_exchange_source", sa.Text(), nullable=True),
        # Reporting currency
        sa.Column("currency", sa.Text(), nullable=True),
        sa.Column("currency_source", sa.Text(), nullable=True),
        # Fiscal year end date
        sa.Column("fiscal_year_end", sa.Text(), nullable=True),
        sa.Column("fiscal_year_end_source", sa.Text(), nullable=True),
        # Annual revenue
        sa.Column("revenue", sa.Text(), nullable=True),
        sa.Column("revenue_source", sa.Text(), nullable=True),
        # Revenue growth rate
        sa.Column("revenue_growth", sa.Text(), nullable=True),
        sa.Column("revenue_growth_source", sa.Text(), nullable=True),
        # Gross margin
        sa.Column("gross_margin", sa.Text(), nullable=True),
        sa.Column("gross_margin_source", sa.Text(), nullable=True),
        # EBITDA margin
        sa.Column("ebitda_margin", sa.Text(), nullable=True),
        sa.Column("ebitda_margin_source", sa.Text(), nullable=True),
        # Net margin
        sa.Column("net_margin", sa.Text(), nullable=True),
        sa.Column("net_margin_source", sa.Text(), nullable=True),
        # Market capitalization
        sa.Column("market_cap", sa.Text(), nullable=True),
        sa.Column("market_cap_source", sa.Text(), nullable=True),
        # Enterprise value
        sa.Column("enterprise_value", sa.Text(), nullable=True),
        sa.Column("enterprise_value_source", sa.Text(), nullable=True),
        # Price-to-earnings ratio
        sa.Column("pe_ratio", sa.Text(), nullable=True),
        sa.Column("pe_ratio_source", sa.Text(), nullable=True),
        # EV/EBITDA multiple
        sa.Column("ev_ebitda", sa.Text(), nullable=True),
        sa.Column("ev_ebitda_source", sa.Text(), nullable=True),
        # EV/Revenue multiple
        sa.Column("ev_revenue", sa.Text(), nullable=True),
        sa.Column("ev_revenue_source", sa.Text(), nullable=True),
        # Employee count (financial context)
        sa.Column("employee_count", sa.Text(), nullable=True),
        sa.Column("employee_count_source", sa.Text(), nullable=True),
        # Total funding raised
        sa.Column("total_funding", sa.Text(), nullable=True),
        sa.Column("total_funding_source", sa.Text(), nullable=True),
        # Last known valuation
        sa.Column("last_valuation", sa.Text(), nullable=True),
        sa.Column("last_valuation_source", sa.Text(), nullable=True),
        # Debt-to-equity ratio
        sa.Column("debt_to_equity", sa.Text(), nullable=True),
        sa.Column("debt_to_equity_source", sa.Text(), nullable=True),
        # Free cash flow
        sa.Column("free_cash_flow", sa.Text(), nullable=True),
        sa.Column("free_cash_flow_source", sa.Text(), nullable=True),
        # Timestamps
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.func.now(),
            onupdate=sa.func.now(),
            nullable=False,
        ),
        # Primary key constraint
        sa.PrimaryKeyConstraint("company_id"),
        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(["company_id"], ["screen_schema.companies.id"], ondelete="CASCADE"),
        schema="screen_schema",
    )

    # ============================================================================
    # 3. CREATE COMPANY_FINANCIAL_METRIC TABLE (1:N)
    # ============================================================================
    print("Creating company_financial_metric table...")
    op.create_table(
        "company_financial_metric",
        # Primary key
        sa.Column("id", sa.Integer(), nullable=False, autoincrement=True),
        # Foreign key to companies
        sa.Column("company_id", sa.Integer(), nullable=False),
        # Metric name (e.g., "Revenue", "EBITDA")
        sa.Column("metric_name", sa.Text(), nullable=False),
        # Reporting period (e.g., "FY2023", "Q3 2024")
        sa.Column("period", sa.Text(), nullable=True),
        # Metric value as text for flexibility
        sa.Column("value", sa.Text(), nullable=True),
        # Unit (e.g., "EUR", "M", "B")
        sa.Column("unit", sa.Text(), nullable=True),
        # Source URL
        sa.Column("source", sa.Text(), nullable=True),
        # Timestamp
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        # Primary key constraint
        sa.PrimaryKeyConstraint("id"),
        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(["company_id"], ["screen_schema.companies.id"], ondelete="CASCADE"),
        schema="screen_schema",
    )

    # Create index on company_id for faster lookups
    op.create_index(
        "idx_company_financial_metric_company_id", "company_financial_metric", ["company_id"], schema="screen_schema"
    )

    # ============================================================================
    # 4. CREATE COMPANY_FUNDING_ROUND TABLE (1:N)
    # ============================================================================
    print("Creating company_funding_round table...")
    op.create_table(
        "company_funding_round",
        # Primary key
        sa.Column("id", sa.Integer(), nullable=False, autoincrement=True),
        # Foreign key to companies
        sa.Column("company_id", sa.Integer(), nullable=False),
        # Round type (e.g., "Series A", "IPO", "Seed")
        sa.Column("round_type", sa.Text(), nullable=True),
        # Amount raised
        sa.Column("amount", sa.Text(), nullable=True),
        # Date of funding round
        sa.Column("date", sa.Text(), nullable=True),
        # Lead investor name
        sa.Column("lead_investor", sa.Text(), nullable=True),
        # Post-money valuation
        sa.Column("valuation", sa.Text(), nullable=True),
        # Source URL
        sa.Column("source", sa.Text(), nullable=True),
        # Timestamp
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        # Primary key constraint
        sa.PrimaryKeyConstraint("id"),
        # Foreign key to companies table with CASCADE delete
        sa.ForeignKeyConstraint(["company_id"], ["screen_schema.companies.id"], ondelete="CASCADE"),
        schema="screen_schema",
    )

    # Create index on company_id for faster lookups
    op.create_index(
        "idx_company_funding_round_company_id", "company_funding_round", ["company_id"], schema="screen_schema"
    )

    print("Financial tables created successfully!")


def downgrade() -> None:
    """Drop financial tables.

    Note: Removing values from PostgreSQL enums requires recreating the type.
    The 'financial' value added to task_type_enum cannot be removed easily without
    a full type recreation, which requires migrating existing data. Since this enum
    addition is additive and safe to leave, we skip the enum revert here.
    If manual removal is needed:
      1. Ensure no rows use 'financial' in task_type_enum
      2. Create a new enum without 'financial'
      3. Alter the column to use the new type
      4. Drop the old type
    """

    print("Dropping financial tables...")

    # Drop indexes first
    op.drop_index("idx_company_funding_round_company_id", table_name="company_funding_round", schema="screen_schema")
    op.drop_index("idx_company_financial_metric_company_id", table_name="company_financial_metric", schema="screen_schema")

    # Drop tables in reverse order of creation
    op.drop_table("company_funding_round", schema="screen_schema")
    op.drop_table("company_financial_metric", schema="screen_schema")
    op.drop_table("company_financial", schema="screen_schema")

    print("Financial tables dropped successfully!")
