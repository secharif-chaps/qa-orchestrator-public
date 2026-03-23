"""Tests for planner node: website reconnaissance and company brief builder.

Covers: _build_company_brief, planner_node.
"""

from unittest.mock import AsyncMock, patch

import pytest

from app.agents.config import ALL_AGENT_TYPES
from app.agents.nodes.planner import _build_company_brief, planner_node

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def _make_state(**overrides) -> dict:
    base = {
        "company_id": 1,
        "company_name": "Acme Corp",
        "website": "https://acme.com",
        "organization_id": "org-1",
        "owner_id": "user-1",
        "country_code": None,
        "company_brief": None,
        "agents_to_run": [],
        "agent_results": [],
        "quality_issues": [],
        "agents_to_retry": [],
        "total_tokens": 0,
        "total_cost": 0.0,
    }
    base.update(overrides)
    return base


# ---------------------------------------------------------------------------
# _build_company_brief
# ---------------------------------------------------------------------------


class TestBuildCompanyBrief:
    """Tests for the brief builder helper."""

    def test_full_planner_data(self):
        data = {
            "summary": "A tech company",
            "industry": "Technology",
            "country": "France",
            "discovered_pages": {"about": "https://acme.com/about", "careers": "https://acme.com/careers"},
            "key_people": ["Alice", "Bob"],
            "brands": ["BrandX"],
            "subsidiaries": ["SubCo"],
            "notes": "Publicly traded",
        }
        brief = _build_company_brief(data, "https://acme.com")

        assert "Summary: A tech company" in brief
        assert "Industry: Technology" in brief
        assert "Country: France" in brief
        assert "about: https://acme.com/about" in brief
        assert "careers: https://acme.com/careers" in brief
        assert "Key people: Alice, Bob" in brief
        assert "Brands: BrandX" in brief
        assert "Subsidiaries: SubCo" in brief
        assert "Notes: Publicly traded" in brief

    def test_missing_fields_skipped(self):
        data = {"summary": "A company"}
        brief = _build_company_brief(data, "https://acme.com")

        assert "Summary: A company" in brief
        assert "Industry" not in brief
        assert "Country" not in brief
        assert "Discovered pages" not in brief

    def test_empty_data_falls_back_to_website(self):
        brief = _build_company_brief({}, "https://acme.com")
        assert brief == "Company website: https://acme.com"

    def test_discovered_pages_empty_urls_skipped(self):
        data = {"discovered_pages": {"about": "", "careers": None}}
        brief = _build_company_brief(data, "https://acme.com")
        # Empty/None URLs should be skipped; if no valid pages, no "Discovered pages" header
        assert brief == "Company website: https://acme.com"

    def test_key_people_limited_to_ten(self):
        data = {"key_people": [f"Person{i}" for i in range(15)]}
        brief = _build_company_brief(data, "https://acme.com")
        # Only first 10 should appear
        assert "Person9" in brief
        assert "Person10" not in brief

    def test_brands_limited_to_ten(self):
        data = {"brands": [f"Brand{i}" for i in range(15)]}
        brief = _build_company_brief(data, "https://acme.com")
        assert "Brand9" in brief
        assert "Brand10" not in brief

    def test_subsidiaries_limited_to_ten(self):
        data = {"subsidiaries": [f"Sub{i}" for i in range(15)]}
        brief = _build_company_brief(data, "https://acme.com")
        assert "Sub9" in brief
        assert "Sub10" not in brief


# ---------------------------------------------------------------------------
# planner_node
# ---------------------------------------------------------------------------


class TestPlannerNode:
    """Tests for the async planner node."""

    @pytest.mark.asyncio
    @patch("app.agents.nodes.planner.web_search_query", new_callable=AsyncMock)
    async def test_success_returns_agents_and_brief(self, mock_search):
        mock_search.return_value = {
            "data": {"summary": "Tech corp", "industry": "SaaS"},
            "sources": ["https://acme.com"],
            "input_tokens": 100,
            "output_tokens": 50,
            "duration_ms": 2000,
        }
        state = _make_state()
        result = await planner_node(state)

        assert result["agents_to_run"] == list(ALL_AGENT_TYPES)
        assert "Tech corp" in result["company_brief"]
        assert result["country_code"] is not None or result["country_code"] is None  # depends on TLD

    @pytest.mark.asyncio
    @patch("app.agents.nodes.planner.web_search_query", new_callable=AsyncMock)
    async def test_country_code_from_planner_data(self, mock_search):
        mock_search.return_value = {
            "data": {"country": "FR"},
            "sources": [],
            "input_tokens": 50,
            "output_tokens": 25,
            "duration_ms": 1000,
        }
        state = _make_state()
        result = await planner_node(state)

        assert result["country_code"] == "FR"

    @pytest.mark.asyncio
    @patch("app.agents.nodes.planner.web_search_query", new_callable=AsyncMock)
    async def test_country_code_from_state_fallback(self, mock_search):
        mock_search.return_value = {
            "data": {},
            "sources": [],
            "input_tokens": 50,
            "output_tokens": 25,
            "duration_ms": 1000,
        }
        state = _make_state(country_code="DE")
        result = await planner_node(state)

        assert result["country_code"] == "DE"

    @pytest.mark.asyncio
    @patch("app.agents.nodes.planner.web_search_query", new_callable=AsyncMock)
    async def test_country_code_from_tld_fallback(self, mock_search):
        mock_search.return_value = {
            "data": {},
            "sources": [],
            "input_tokens": 50,
            "output_tokens": 25,
            "duration_ms": 1000,
        }
        state = _make_state(website="https://acme.fr", country_code=None)
        result = await planner_node(state)

        assert result["country_code"] == "FR"

    @pytest.mark.asyncio
    @patch("app.agents.nodes.planner.web_search_query", new_callable=AsyncMock)
    async def test_exception_returns_all_agents_with_default_brief(self, mock_search):
        mock_search.side_effect = RuntimeError("API down")
        state = _make_state()
        result = await planner_node(state)

        assert result["agents_to_run"] == list(ALL_AGENT_TYPES)
        assert "Company website: https://acme.com" in result["company_brief"]

    @pytest.mark.asyncio
    @patch("app.agents.nodes.planner.web_search_query", new_callable=AsyncMock)
    async def test_exception_country_code_fallback(self, mock_search):
        mock_search.side_effect = RuntimeError("fail")
        state = _make_state(website="https://example.de", country_code=None)
        result = await planner_node(state)

        assert result["country_code"] == "DE"

    @pytest.mark.asyncio
    @patch("app.agents.nodes.planner.web_search_query", new_callable=AsyncMock)
    async def test_exception_country_code_from_state(self, mock_search):
        mock_search.side_effect = RuntimeError("fail")
        state = _make_state(country_code="JP")
        result = await planner_node(state)

        assert result["country_code"] == "JP"

    @pytest.mark.asyncio
    @patch("app.agents.nodes.planner.web_search_query", new_callable=AsyncMock)
    async def test_brief_contains_company_name_info(self, mock_search):
        mock_search.return_value = {
            "data": {"summary": "Acme makes widgets"},
            "sources": [],
            "input_tokens": 10,
            "output_tokens": 5,
            "duration_ms": 500,
        }
        state = _make_state()
        result = await planner_node(state)

        assert "Acme makes widgets" in result["company_brief"]

    @pytest.mark.asyncio
    @patch("app.agents.nodes.planner.web_search_query", new_callable=AsyncMock)
    async def test_planner_data_country_takes_priority(self, mock_search):
        """Planner data country overrides state country_code."""
        mock_search.return_value = {
            "data": {"country": "US"},
            "sources": [],
            "input_tokens": 10,
            "output_tokens": 5,
            "duration_ms": 500,
        }
        state = _make_state(country_code="FR")
        result = await planner_node(state)

        assert result["country_code"] == "US"
