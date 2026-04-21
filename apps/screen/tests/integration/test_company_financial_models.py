"""Tests for Company financial models (TAR-1404).

Covers:
1. Model creation with all fields (CompanyFinancial, CompanyFinancialMetric, CompanyFundingRound)
2. Bidirectional relationships with Company
3. 1:1 enforcement on CompanyFinancial (uselist=False)
4. 1:N behaviour on metrics and funding rounds
5. Timestamps auto-populate
6. CASCADE delete propagation
"""

from datetime import UTC, datetime

import pytest

from app.models.company import Company
from app.models.company_financial import CompanyFinancial, CompanyFinancialMetric, CompanyFundingRound

pytestmark = pytest.mark.integration


@pytest.fixture
def sample_company(db_session):
    """Create a sample company for financial model tests."""
    company = Company(
        name="Acme Corp",
        website="https://acme.com",
        organization_id="org-uuid-financial",
        owner_id="owner-uuid-financial",
        owner_username="financeuser",
    )
    db_session.add(company)
    db_session.commit()
    db_session.refresh(company)
    return company


# ---------------------------------------------------------------------------
# Model creation
# ---------------------------------------------------------------------------


class TestCompanyFinancialCreation:
    """Test CompanyFinancial creation with SourcedValue fields."""

    def test_create_with_all_fields(self, db_session, sample_company):
        """All 21 SourcedValue pairs persist correctly."""
        financial = CompanyFinancial(
            company_id=sample_company.id,
            insights="Strong revenue growth driven by SaaS expansion",
            insights_source="Chaps-e",
            company_type="public",
            company_type_source="https://sec.gov/acme",
            ticker_symbol="ACME",
            ticker_symbol_source="https://nasdaq.com/acme",
            stock_exchange="NASDAQ",
            stock_exchange_source="https://nasdaq.com/acme",
            currency="USD",
            currency_source="https://acme.com/investors",
            fiscal_year_end="December",
            fiscal_year_end_source="https://acme.com/annual-report",
            revenue="$500M",
            revenue_source="https://acme.com/investors",
            revenue_growth="12%",
            revenue_growth_source="https://acme.com/investors",
            gross_margin="65%",
            gross_margin_source="https://acme.com/investors",
            ebitda_margin="22%",
            ebitda_margin_source="https://acme.com/investors",
            net_margin="15%",
            net_margin_source="https://acme.com/investors",
            market_cap="$4.2B",
            market_cap_source="https://nasdaq.com/acme",
            enterprise_value="$4.0B",
            enterprise_value_source="https://nasdaq.com/acme",
            pe_ratio="28x",
            pe_ratio_source="https://nasdaq.com/acme",
            ev_ebitda="18x",
            ev_ebitda_source="https://acme.com/investors",
            ev_revenue="8x",
            ev_revenue_source="https://acme.com/investors",
            employee_count="3500",
            employee_count_source="https://linkedin.com/company/acme",
            total_funding=None,
            last_valuation=None,
            debt_to_equity="0.4",
            debt_to_equity_source="https://acme.com/investors",
            free_cash_flow="$75M",
            free_cash_flow_source="https://acme.com/investors",
        )
        db_session.add(financial)
        db_session.commit()
        db_session.refresh(financial)

        assert financial.company_id == sample_company.id
        assert financial.insights == "Strong revenue growth driven by SaaS expansion"
        assert financial.company_type == "public"
        assert financial.ticker_symbol == "ACME"
        assert financial.stock_exchange == "NASDAQ"
        assert financial.currency == "USD"
        assert financial.fiscal_year_end == "December"
        assert financial.revenue == "$500M"
        assert financial.revenue_growth == "12%"
        assert financial.gross_margin == "65%"
        assert financial.ebitda_margin == "22%"
        assert financial.net_margin == "15%"
        assert financial.market_cap == "$4.2B"
        assert financial.enterprise_value == "$4.0B"
        assert financial.pe_ratio == "28x"
        assert financial.ev_ebitda == "18x"
        assert financial.ev_revenue == "8x"
        assert financial.employee_count == "3500"
        assert financial.total_funding is None
        assert financial.debt_to_equity == "0.4"
        assert financial.free_cash_flow == "$75M"

    def test_create_with_minimal_fields(self, db_session, sample_company):
        """Only company_id required — all financial fields are nullable."""
        financial = CompanyFinancial(company_id=sample_company.id)
        db_session.add(financial)
        db_session.commit()
        db_session.refresh(financial)

        assert financial.company_id == sample_company.id
        assert financial.revenue is None
        assert financial.market_cap is None
        assert financial.insights is None

    def test_timestamps_auto_populate(self, db_session, sample_company):
        """created_at and updated_at are set automatically on insert."""
        financial = CompanyFinancial(company_id=sample_company.id, revenue="$100M")
        db_session.add(financial)
        db_session.commit()
        db_session.refresh(financial)

        assert financial.created_at is not None
        assert financial.updated_at is not None
        now = datetime.now(UTC)
        assert abs((now - financial.created_at.replace(tzinfo=UTC)).total_seconds()) < 60


class TestCompanyFinancialMetricCreation:
    """Test CompanyFinancialMetric creation (1:N)."""

    def test_create_metric_with_all_fields(self, db_session, sample_company):
        """All fields on CompanyFinancialMetric persist correctly."""
        metric = CompanyFinancialMetric(
            company_id=sample_company.id,
            metric_name="Revenue",
            period="FY2024",
            value="$500M",
            unit="USD",
            source="https://acme.com/investors",
        )
        db_session.add(metric)
        db_session.commit()
        db_session.refresh(metric)

        assert metric.id is not None
        assert metric.company_id == sample_company.id
        assert metric.metric_name == "Revenue"
        assert metric.period == "FY2024"
        assert metric.value == "$500M"
        assert metric.unit == "USD"
        assert metric.source == "https://acme.com/investors"
        assert metric.created_at is not None

    def test_multiple_metrics_per_company(self, db_session, sample_company):
        """A company can have many metrics (1:N relationship)."""
        metrics = [
            CompanyFinancialMetric(company_id=sample_company.id, metric_name="Revenue", period="FY2023", value="$420M"),
            CompanyFinancialMetric(company_id=sample_company.id, metric_name="Revenue", period="FY2024", value="$500M"),
            CompanyFinancialMetric(company_id=sample_company.id, metric_name="EBITDA", period="FY2024", value="$110M"),
        ]
        db_session.add_all(metrics)
        db_session.commit()
        db_session.refresh(sample_company)

        assert len(sample_company.financial_metrics) == 3


class TestCompanyFundingRoundCreation:
    """Test CompanyFundingRound creation (1:N)."""

    def test_create_funding_round_with_all_fields(self, db_session, sample_company):
        """All fields on CompanyFundingRound persist correctly."""
        round_ = CompanyFundingRound(
            company_id=sample_company.id,
            round_type="Series B",
            amount="$80M",
            date="March 2023",
            lead_investor="Accel Partners",
            valuation="$600M",
            source="https://techcrunch.com/acme-series-b",
        )
        db_session.add(round_)
        db_session.commit()
        db_session.refresh(round_)

        assert round_.id is not None
        assert round_.company_id == sample_company.id
        assert round_.round_type == "Series B"
        assert round_.amount == "$80M"
        assert round_.date == "March 2023"
        assert round_.lead_investor == "Accel Partners"
        assert round_.valuation == "$600M"
        assert round_.source == "https://techcrunch.com/acme-series-b"

    def test_multiple_rounds_per_company(self, db_session, sample_company):
        """A company can have many funding rounds (1:N relationship)."""
        rounds = [
            CompanyFundingRound(company_id=sample_company.id, round_type="Seed", amount="$2M"),
            CompanyFundingRound(company_id=sample_company.id, round_type="Series A", amount="$25M"),
            CompanyFundingRound(company_id=sample_company.id, round_type="Series B", amount="$80M"),
        ]
        db_session.add_all(rounds)
        db_session.commit()
        db_session.refresh(sample_company)

        assert len(sample_company.funding_rounds) == 3


# ---------------------------------------------------------------------------
# Relationships
# ---------------------------------------------------------------------------


class TestFinancialRelationships:
    """Test bidirectional relationships between Company and financial models."""

    def test_company_to_financial_data(self, db_session, sample_company):
        """Company.financial_data navigates to CompanyFinancial."""
        financial = CompanyFinancial(company_id=sample_company.id, revenue="$500M")
        db_session.add(financial)
        db_session.commit()
        db_session.refresh(sample_company)

        assert sample_company.financial_data is not None
        assert sample_company.financial_data.revenue == "$500M"

    def test_financial_to_company(self, db_session, sample_company):
        """CompanyFinancial.company navigates back to Company."""
        financial = CompanyFinancial(company_id=sample_company.id, revenue="$500M")
        db_session.add(financial)
        db_session.commit()
        db_session.refresh(financial)

        assert financial.company is not None
        assert financial.company.id == sample_company.id
        assert financial.company.name == "Acme Corp"

    def test_company_to_financial_metrics(self, db_session, sample_company):
        """Company.financial_metrics returns list of CompanyFinancialMetric."""
        m1 = CompanyFinancialMetric(company_id=sample_company.id, metric_name="Revenue", period="FY2024", value="$500M")
        m2 = CompanyFinancialMetric(company_id=sample_company.id, metric_name="EBITDA", period="FY2024", value="$110M")
        db_session.add_all([m1, m2])
        db_session.commit()
        db_session.refresh(sample_company)

        assert len(sample_company.financial_metrics) == 2
        names = {m.metric_name for m in sample_company.financial_metrics}
        assert names == {"Revenue", "EBITDA"}

    def test_metric_to_company(self, db_session, sample_company):
        """CompanyFinancialMetric.company navigates back to Company."""
        metric = CompanyFinancialMetric(company_id=sample_company.id, metric_name="Revenue", period="FY2024")
        db_session.add(metric)
        db_session.commit()
        db_session.refresh(metric)

        assert metric.company.name == "Acme Corp"

    def test_company_to_funding_rounds(self, db_session, sample_company):
        """Company.funding_rounds returns list of CompanyFundingRound."""
        r1 = CompanyFundingRound(company_id=sample_company.id, round_type="Seed", amount="$2M")
        r2 = CompanyFundingRound(company_id=sample_company.id, round_type="Series A", amount="$25M")
        db_session.add_all([r1, r2])
        db_session.commit()
        db_session.refresh(sample_company)

        assert len(sample_company.funding_rounds) == 2
        types = {r.round_type for r in sample_company.funding_rounds}
        assert types == {"Seed", "Series A"}

    def test_funding_round_to_company(self, db_session, sample_company):
        """CompanyFundingRound.company navigates back to Company."""
        round_ = CompanyFundingRound(company_id=sample_company.id, round_type="Series A")
        db_session.add(round_)
        db_session.commit()
        db_session.refresh(round_)

        assert round_.company.name == "Acme Corp"


# ---------------------------------------------------------------------------
# 1:1 enforcement
# ---------------------------------------------------------------------------


class TestOneToOneEnforcement:
    """Test that CompanyFinancial enforces a 1:1 relationship via PK."""

    def test_financial_data_is_single_object_not_list(self, db_session, sample_company):
        """company.financial_data must return an object, not a list."""
        financial = CompanyFinancial(company_id=sample_company.id, revenue="$500M")
        db_session.add(financial)
        db_session.commit()
        db_session.refresh(sample_company)

        assert not isinstance(sample_company.financial_data, list)
        assert isinstance(sample_company.financial_data, CompanyFinancial)

    def test_empty_financial_data_is_none(self, db_session, sample_company):
        """company.financial_data is None when no record exists."""
        db_session.refresh(sample_company)

        assert sample_company.financial_data is None

    def test_empty_financial_metrics_is_empty_list(self, db_session, sample_company):
        """company.financial_metrics is [] when no metrics exist."""
        db_session.refresh(sample_company)

        assert sample_company.financial_metrics == []

    def test_empty_funding_rounds_is_empty_list(self, db_session, sample_company):
        """company.funding_rounds is [] when no rounds exist."""
        db_session.refresh(sample_company)

        assert sample_company.funding_rounds == []


# ---------------------------------------------------------------------------
# CASCADE delete
# ---------------------------------------------------------------------------


class TestCascadeDelete:
    """Test that deleting a Company cascades to all financial tables."""

    def test_delete_company_cascades_to_financial(self, db_session, sample_company):
        """Deleting company removes CompanyFinancial record."""
        financial = CompanyFinancial(company_id=sample_company.id, revenue="$500M")
        db_session.add(financial)
        db_session.commit()

        db_session.delete(sample_company)
        db_session.commit()

        remaining = db_session.query(CompanyFinancial).filter_by(company_id=sample_company.id).first()
        assert remaining is None

    def test_delete_company_cascades_to_metrics(self, db_session, sample_company):
        """Deleting company removes all CompanyFinancialMetric records."""
        db_session.add_all(
            [
                CompanyFinancialMetric(company_id=sample_company.id, metric_name="Revenue", period="FY2024"),
                CompanyFinancialMetric(company_id=sample_company.id, metric_name="EBITDA", period="FY2024"),
            ]
        )
        db_session.commit()

        db_session.delete(sample_company)
        db_session.commit()

        remaining = db_session.query(CompanyFinancialMetric).filter_by(company_id=sample_company.id).all()
        assert remaining == []

    def test_delete_company_cascades_to_funding_rounds(self, db_session, sample_company):
        """Deleting company removes all CompanyFundingRound records."""
        db_session.add_all(
            [
                CompanyFundingRound(company_id=sample_company.id, round_type="Seed"),
                CompanyFundingRound(company_id=sample_company.id, round_type="Series A"),
            ]
        )
        db_session.commit()

        db_session.delete(sample_company)
        db_session.commit()

        remaining = db_session.query(CompanyFundingRound).filter_by(company_id=sample_company.id).all()
        assert remaining == []
