"""SQLAlchemy models for company financial data.

This module defines financial models for normalized company financial data storage:
- CompanyFinancial: 1:1 section with financial overview and key metrics
- CompanyFinancialMetric: 1:N child table for individual financial metrics by period
- CompanyFundingRound: 1:N child table for funding round history

CompanyFinancial uses company_id as PK and FK (1:1 relationship).
CompanyFinancialMetric and CompanyFundingRound use auto-increment PKs (1:N).
"""

from sqlalchemy import Column, Date, DateTime, ForeignKey, Integer, Text
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import SCREEN_SCHEMA, Base


class CompanyFinancial(Base):
    """Company financial section with overview data.

    Contains key financial indicators and metadata about the company's
    financial profile. Each field follows the SourcedValue pattern
    (value field + _source field with URL).

    Individual financial metrics by period are stored in CompanyFinancialMetric.
    Funding round history is stored in CompanyFundingRound.

    Attributes:
        company_id: Primary key and foreign key to companies table
        insights: AI-generated financial summary
        insights_source: Source of insights
        company_type: Company type (public, private, etc.)
        company_type_source: Source URL for company type
        ticker_symbol: Stock ticker symbol
        ticker_symbol_source: Source URL for ticker
        stock_exchange: Stock exchange name (NYSE, NASDAQ, etc.)
        stock_exchange_source: Source URL for exchange
        currency: Reporting currency
        currency_source: Source URL for currency
        fiscal_year_end: Fiscal year end date
        fiscal_year_end_source: Source URL for fiscal year end
        revenue: Annual revenue
        revenue_source: Source URL for revenue
        revenue_growth: Revenue growth rate
        revenue_growth_source: Source URL for revenue growth
        gross_margin: Gross margin percentage
        gross_margin_source: Source URL for gross margin
        ebitda_margin: EBITDA margin percentage
        ebitda_margin_source: Source URL for EBITDA margin
        net_margin: Net margin percentage
        net_margin_source: Source URL for net margin
        market_cap: Market capitalization
        market_cap_source: Source URL for market cap
        enterprise_value: Enterprise value
        enterprise_value_source: Source URL for enterprise value
        pe_ratio: Price-to-earnings ratio
        pe_ratio_source: Source URL for P/E ratio
        ev_ebitda: EV/EBITDA multiple
        ev_ebitda_source: Source URL for EV/EBITDA
        ev_revenue: EV/Revenue multiple
        ev_revenue_source: Source URL for EV/Revenue
        employee_count: Number of employees (financial context)
        employee_count_source: Source URL for employee count
        total_funding: Total funding raised
        total_funding_source: Source URL for total funding
        last_valuation: Last known valuation
        last_valuation_source: Source URL for last valuation
        debt_to_equity: Debt-to-equity ratio
        debt_to_equity_source: Source URL for debt-to-equity
        free_cash_flow: Free cash flow
        free_cash_flow_source: Source URL for free cash flow
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_financial"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    # Primary key is also foreign key - enforces 1:1 relationship
    company_id = Column(Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), primary_key=True)

    # Insights (AI-generated summary)
    insights = Column(Text, nullable=True)
    insights_source = Column(Text, nullable=True)

    # Company type (public, private, etc.)
    company_type = Column(Text, nullable=True)
    company_type_source = Column(Text, nullable=True)

    # Stock ticker symbol
    ticker_symbol = Column(Text, nullable=True)
    ticker_symbol_source = Column(Text, nullable=True)

    # Stock exchange
    stock_exchange = Column(Text, nullable=True)
    stock_exchange_source = Column(Text, nullable=True)

    # Reporting currency
    currency = Column(Text, nullable=True)
    currency_source = Column(Text, nullable=True)

    # Fiscal year end
    fiscal_year_end = Column(Text, nullable=True)
    fiscal_year_end_source = Column(Text, nullable=True)

    # Annual revenue
    revenue = Column(Text, nullable=True)
    revenue_source = Column(Text, nullable=True)

    # Revenue growth rate
    revenue_growth = Column(Text, nullable=True)
    revenue_growth_source = Column(Text, nullable=True)

    # Gross margin
    gross_margin = Column(Text, nullable=True)
    gross_margin_source = Column(Text, nullable=True)

    # EBITDA margin
    ebitda_margin = Column(Text, nullable=True)
    ebitda_margin_source = Column(Text, nullable=True)

    # Net margin
    net_margin = Column(Text, nullable=True)
    net_margin_source = Column(Text, nullable=True)

    # Market capitalization
    market_cap = Column(Text, nullable=True)
    market_cap_source = Column(Text, nullable=True)

    # Enterprise value
    enterprise_value = Column(Text, nullable=True)
    enterprise_value_source = Column(Text, nullable=True)

    # Price-to-earnings ratio
    pe_ratio = Column(Text, nullable=True)
    pe_ratio_source = Column(Text, nullable=True)

    # EV/EBITDA multiple
    ev_ebitda = Column(Text, nullable=True)
    ev_ebitda_source = Column(Text, nullable=True)

    # EV/Revenue multiple
    ev_revenue = Column(Text, nullable=True)
    ev_revenue_source = Column(Text, nullable=True)

    # Employee count (financial context)
    employee_count = Column(Text, nullable=True)
    employee_count_source = Column(Text, nullable=True)

    # Total funding raised
    total_funding = Column(Text, nullable=True)
    total_funding_source = Column(Text, nullable=True)

    # Last known valuation
    last_valuation = Column(Text, nullable=True)
    last_valuation_source = Column(Text, nullable=True)

    # Debt-to-equity ratio
    debt_to_equity = Column(Text, nullable=True)
    debt_to_equity_source = Column(Text, nullable=True)

    # Free cash flow
    free_cash_flow = Column(Text, nullable=True)
    free_cash_flow_source = Column(Text, nullable=True)

    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)

    # Relationship to Company (bidirectional with back_populates)
    company = relationship("Company", back_populates="financial_data")


class CompanyFinancialMetric(Base):
    """Individual financial metric for a company by period.

    Represents a specific financial metric (e.g., Revenue, EBITDA, Net Income)
    for a given reporting period. Allows tracking historical performance.

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        metric_name: Name of the metric (e.g., "Revenue", "EBITDA")
        period: Reporting period (e.g., "FY2023", "Q3 2024")
        value: Metric value as text for flexibility
        unit: Unit of measurement (e.g., "EUR", "M", "B")
        source: Source URL for this metric
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_financial_metric"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Canonical metric name after backend normalisation (revenue, ebitda, netIncome, freeCashFlow, or raw LLM name)
    metric_name = Column(Text, nullable=False)

    # Reporting period (e.g., "FY2023", "Q3 2024", "TTM")
    period = Column(Text, nullable=True)

    # ISO date anchoring the START of the reporting period for chronological ordering.
    # null when the period cannot be anchored (TTM, LTM, undateable labels).
    period_normalized = Column(Date, nullable=True)

    # Metric value as text for flexibility
    value = Column(Text, nullable=True)

    # Unit of measurement (e.g., "EUR", "USD", "M", "B")
    unit = Column(Text, nullable=True)

    # Source URL
    source = Column(Text, nullable=True)

    # Optional extra context: LLM description or original name before normalisation
    context = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="financial_metrics")


class CompanyFundingRound(Base):
    """Funding round for a company.

    Represents a single funding event in the company's financing history
    (e.g., Seed, Series A, Series B, IPO). Tracks amount, investors,
    valuation, and date for each round.

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        round_type: Type of funding round (e.g., "Series A", "IPO", "Seed")
        amount: Amount raised
        date: Date of the funding round
        lead_investor: Name of the lead investor
        valuation: Post-money valuation at time of round
        source: Source URL for this funding round
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_funding_round"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Round type (e.g., "Seed", "Series A", "Series B", "IPO")
    round_type = Column(Text, nullable=True)

    # Amount raised (as text for flexibility with different currencies/units)
    amount = Column(Text, nullable=True)

    # Date of funding round (as text for flexibility)
    date = Column(Text, nullable=True)

    # ISO date anchoring the start of the funding event for chronological ordering.
    # null when the date cannot be anchored.
    date_normalized = Column(Date, nullable=True)

    # Lead investor name
    lead_investor = Column(Text, nullable=True)

    # Post-money valuation
    valuation = Column(Text, nullable=True)

    # Source URL
    source = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="funding_rounds")
