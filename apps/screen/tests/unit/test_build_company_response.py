"""Unit tests for _build_company_response financial field wiring.

Verifies that financial data from read_all_section_data is included
in the CompanyResponse returned by _build_company_response.
"""

from unittest.mock import MagicMock, patch

import pytest
from sqlalchemy.orm import Session

from app.models.company import Company
from app.services.company import _build_company_response


@pytest.fixture
def mock_db():
    return MagicMock(spec=Session)


@pytest.fixture
def mock_company():
    company = MagicMock(spec=Company)
    company.id = 1
    company.name = "Acme Corp"
    company.website = "https://acme.com"
    company.owner_id = "user-uuid-123"
    company.owner_username = "alice"
    company.error = None
    company.is_deleted = False
    company.created_at = None
    company.updated_at = None
    return company


SAMPLE_FINANCIAL_DATA = {
    "companyType": {"value": "public", "source": "https://example.com"},
    "tickerSymbol": {"value": "ACME", "source": "https://example.com"},
    "revenue": {"value": "500000000", "source": "https://sec.gov"},
    "metrics": [
        # snake_case keys match what get_financial_data() actually returns
        {"metric_name": "revenue", "period": "2023", "value": "500000000", "unit": "USD"}
    ],
    "fundingRounds": [],
}

FULL_SECTION_DATA = {
    "profile": {"companyName": {"value": "Acme Corp", "source": "https://acme.com"}},
    "digital": {},
    "timeline": {},
    "products": {},
    "jobs": {},
    "csr": {},
    "press": {},
    "financial": SAMPLE_FINANCIAL_DATA,
    "team": [],
    "corporate_structure": {},
    "sanctions": {},
}


class TestBuildCompanyResponseFinancial:
    """Tests that financial data flows through _build_company_response."""

    def test_financial_data_included_in_response(self, mock_db, mock_company):
        """Financial section from read_all_section_data appears in CompanyResponse."""
        with patch(
            "app.services.company.read_all_section_data", return_value=FULL_SECTION_DATA
        ):
            response = _build_company_response(mock_db, mock_company)

        assert response.financial == SAMPLE_FINANCIAL_DATA

    def test_financial_defaults_to_empty_dict_when_absent(self, mock_db, mock_company):
        """If financial key is missing from section data, financial field defaults to {}."""
        section_data_without_financial = {k: v for k, v in FULL_SECTION_DATA.items() if k != "financial"}
        with patch(
            "app.services.company.read_all_section_data",
            return_value=section_data_without_financial,
        ):
            response = _build_company_response(mock_db, mock_company)

        assert response.financial == {}

    def test_financial_defaults_to_empty_dict_when_none(self, mock_db, mock_company):
        """If financial key is present but explicitly None (e.g. agent failed), financial field defaults to {}."""
        section_data_with_none = {**FULL_SECTION_DATA, "financial": None}
        with patch(
            "app.services.company.read_all_section_data",
            return_value=section_data_with_none,
        ):
            response = _build_company_response(mock_db, mock_company)

        assert response.financial == {}

    def test_other_sections_unaffected(self, mock_db, mock_company):
        """Adding financial does not break existing section fields."""
        with patch(
            "app.services.company.read_all_section_data", return_value=FULL_SECTION_DATA
        ):
            response = _build_company_response(mock_db, mock_company)

        assert response.profile == FULL_SECTION_DATA["profile"]
        assert response.team == []
        assert response.financial == SAMPLE_FINANCIAL_DATA
        assert response.corporate_structure == FULL_SECTION_DATA["corporate_structure"]
        assert response.sanctions == FULL_SECTION_DATA["sanctions"]
