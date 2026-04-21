"""Tests for the financial agent node — run_financial_agent and pipeline steps."""

from unittest.mock import AsyncMock, patch

import pytest

from app.agents.state import AgentResult

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def _make_state(**overrides) -> dict:
    base = {
        "company_id": 1,
        "company_name": "Apple Inc.",
        "website": "https://apple.com",
        "organization_id": "org-1",
        "owner_id": "user-1",
        "country_code": "US",
        "company_brief": "Consumer electronics company",
        "agents_to_run": [],
        "agent_results": [],
        "quality_issues": [],
        "agents_to_retry": [],
        "total_tokens": 0,
        "total_cost": 0.0,
    }
    base.update(overrides)
    return base


def _make_web_search_result(data: dict, sources: list[str] | None = None) -> dict:
    return {
        "data": data,
        "sources": sources or ["https://example.com"],
        "input_tokens": 100,
        "output_tokens": 50,
    }


_CLASSIFICATION_PUBLIC_US = {
    "companyType": "public",
    "tickerSymbol": "AAPL",
    "stockExchange": "NASDAQ",
    "isUSListed": True,
    "country": "US",
}

_CLASSIFICATION_PRIVATE = {
    "companyType": "private",
    "tickerSymbol": None,
    "stockExchange": None,
    "isUSListed": False,
    "country": "FR",
}

_SYNTHESIS_OUTPUT = {
    "insights": "Strong financial performer.",
    "companyType": {"value": "public", "source": "https://nasdaq.com"},
    "tickerSymbol": {"value": "AAPL", "source": "https://finance.yahoo.com/quote/AAPL"},
    "revenue": {"value": "$394.3B", "source": "https://finance.yahoo.com/quote/AAPL"},
}


# ---------------------------------------------------------------------------
# run_financial_agent — top-level
# ---------------------------------------------------------------------------


class TestRunFinancialAgent:
    """Tests for the public entry point."""

    @pytest.mark.asyncio
    async def test_returns_agent_results_key(self):
        from app.agents.nodes.financial import run_financial_agent

        with (
            patch("app.agents.nodes.financial.web_search_query", new_callable=AsyncMock) as mock_search,
            patch("app.agents.nodes.financial.fetch_yfinance_data", new_callable=AsyncMock) as mock_yf,
            patch("app.agents.nodes.financial.fetch_sec_edgar_data", new_callable=AsyncMock) as mock_edgar,
        ):
            mock_search.side_effect = [
                _make_web_search_result(_CLASSIFICATION_PUBLIC_US),  # Step 1: classify
                _make_web_search_result({"recentEarnings": "Q4 beat"}),  # Step 2: news
                _make_web_search_result(_SYNTHESIS_OUTPUT),  # Step 3: synthesize
            ]
            mock_yf.return_value = {"currentPrice": "$182.52", "marketCap": "$2.8T"}
            mock_edgar.return_value = {
                "revenue": "$394.3B",
                "source": "https://sec.gov/cgi-bin/browse-edgar?action=getcompany&CIK=320193",
            }

            result = await run_financial_agent(_make_state())

        assert "agent_results" in result
        assert isinstance(result["agent_results"], list)
        assert len(result["agent_results"]) == 1

    @pytest.mark.asyncio
    async def test_success_status_when_data_returned(self):
        from app.agents.nodes.financial import run_financial_agent

        with (
            patch("app.agents.nodes.financial.web_search_query", new_callable=AsyncMock) as mock_search,
            patch("app.agents.nodes.financial.fetch_yfinance_data", new_callable=AsyncMock) as mock_yf,
            patch("app.agents.nodes.financial.fetch_sec_edgar_data", new_callable=AsyncMock) as mock_edgar,
        ):
            mock_search.side_effect = [
                _make_web_search_result(_CLASSIFICATION_PUBLIC_US),
                _make_web_search_result({}),
                _make_web_search_result(_SYNTHESIS_OUTPUT),
            ]
            mock_yf.return_value = {}
            mock_edgar.return_value = {}

            result = await run_financial_agent(_make_state())

        agent_result: AgentResult = result["agent_results"][0]
        assert agent_result["agent_name"] == "financial"
        assert agent_result["status"] == "success"
        assert agent_result["error"] is None

    @pytest.mark.asyncio
    async def test_error_status_when_empty_synthesis(self):
        from app.agents.nodes.financial import run_financial_agent

        with (
            patch("app.agents.nodes.financial.web_search_query", new_callable=AsyncMock) as mock_search,
            patch("app.agents.nodes.financial.fetch_yfinance_data", new_callable=AsyncMock) as mock_yf,
            patch("app.agents.nodes.financial.fetch_sec_edgar_data", new_callable=AsyncMock) as mock_edgar,
        ):
            mock_search.side_effect = [
                _make_web_search_result(_CLASSIFICATION_PUBLIC_US),
                _make_web_search_result({}),
                _make_web_search_result({}),  # Empty synthesis
            ]
            mock_yf.return_value = {}
            mock_edgar.return_value = {}

            result = await run_financial_agent(_make_state())

        agent_result: AgentResult = result["agent_results"][0]
        assert agent_result["status"] == "error"
        assert agent_result["error"] == "Empty financial data"

    @pytest.mark.asyncio
    async def test_timeout_returns_error_result(self):
        from app.agents.nodes.financial import run_financial_agent

        with patch("app.agents.nodes.financial.asyncio.wait_for", new_callable=AsyncMock) as mock_wait:
            mock_wait.side_effect = TimeoutError()
            result = await run_financial_agent(_make_state())

        agent_result: AgentResult = result["agent_results"][0]
        assert agent_result["status"] == "error"
        assert "timed out" in agent_result["error"].lower()
        assert agent_result["data"] == {}

    @pytest.mark.asyncio
    async def test_unexpected_exception_returns_error_result(self):
        from app.agents.nodes.financial import run_financial_agent

        with patch("app.agents.nodes.financial.asyncio.wait_for", new_callable=AsyncMock) as mock_wait:
            mock_wait.side_effect = RuntimeError("Unexpected failure")
            result = await run_financial_agent(_make_state())

        agent_result: AgentResult = result["agent_results"][0]
        assert agent_result["status"] == "error"
        assert "Unexpected failure" in agent_result["error"]

    @pytest.mark.asyncio
    async def test_accumulates_tokens(self):
        from app.agents.nodes.financial import run_financial_agent

        with (
            patch("app.agents.nodes.financial.web_search_query", new_callable=AsyncMock) as mock_search,
            patch("app.agents.nodes.financial.fetch_yfinance_data", new_callable=AsyncMock) as mock_yf,
            patch("app.agents.nodes.financial.fetch_sec_edgar_data", new_callable=AsyncMock) as mock_edgar,
        ):
            mock_search.side_effect = [
                {**_make_web_search_result(_CLASSIFICATION_PUBLIC_US), "input_tokens": 200, "output_tokens": 80},
                {**_make_web_search_result({}), "input_tokens": 150, "output_tokens": 60},
                {**_make_web_search_result(_SYNTHESIS_OUTPUT), "input_tokens": 300, "output_tokens": 120},
            ]
            mock_yf.return_value = {}
            mock_edgar.return_value = {}

            result = await run_financial_agent(_make_state())

        agent_result: AgentResult = result["agent_results"][0]
        assert agent_result["input_tokens"] == 650
        assert agent_result["output_tokens"] == 260


# ---------------------------------------------------------------------------
# Public vs Private dispatch
# ---------------------------------------------------------------------------


class TestPipelineDispatch:
    """Tests for tool dispatch strategy based on company type."""

    @pytest.mark.asyncio
    async def test_public_us_calls_yfinance_and_edgar(self):
        from app.agents.nodes.financial import run_financial_agent

        with (
            patch("app.agents.nodes.financial.web_search_query", new_callable=AsyncMock) as mock_search,
            patch("app.agents.nodes.financial.fetch_yfinance_data", new_callable=AsyncMock) as mock_yf,
            patch("app.agents.nodes.financial.fetch_sec_edgar_data", new_callable=AsyncMock) as mock_edgar,
        ):
            mock_search.side_effect = [
                _make_web_search_result(_CLASSIFICATION_PUBLIC_US),
                _make_web_search_result({}),
                _make_web_search_result(_SYNTHESIS_OUTPUT),
            ]
            mock_yf.return_value = {}
            mock_edgar.return_value = {}

            await run_financial_agent(_make_state())

        mock_yf.assert_called_once_with("AAPL")
        mock_edgar.assert_called_once()

    @pytest.mark.asyncio
    async def test_private_company_skips_yfinance_and_edgar(self):
        from app.agents.nodes.financial import run_financial_agent

        with (
            patch("app.agents.nodes.financial.web_search_query", new_callable=AsyncMock) as mock_search,
            patch("app.agents.nodes.financial.fetch_yfinance_data", new_callable=AsyncMock) as mock_yf,
            patch("app.agents.nodes.financial.fetch_sec_edgar_data", new_callable=AsyncMock) as mock_edgar,
        ):
            mock_search.side_effect = [
                _make_web_search_result(_CLASSIFICATION_PRIVATE),
                _make_web_search_result({"totalFunding": "$50M"}),  # private search
                _make_web_search_result({}),  # news
                _make_web_search_result(
                    {"companyType": {"value": "private", "source": "https://crunchbase.com"}}
                ),  # synthesize
            ]

            await run_financial_agent(_make_state(country_code="FR"))

        mock_yf.assert_not_called()
        mock_edgar.assert_not_called()

    @pytest.mark.asyncio
    async def test_public_non_us_calls_yfinance_but_not_edgar(self):
        from app.agents.nodes.financial import run_financial_agent

        classification_non_us = {
            "companyType": "public",
            "tickerSymbol": "MC.PA",
            "stockExchange": "EURONEXT",
            "isUSListed": False,
            "country": "FR",
        }

        with (
            patch("app.agents.nodes.financial.web_search_query", new_callable=AsyncMock) as mock_search,
            patch("app.agents.nodes.financial.fetch_yfinance_data", new_callable=AsyncMock) as mock_yf,
            patch("app.agents.nodes.financial.fetch_sec_edgar_data", new_callable=AsyncMock) as mock_edgar,
        ):
            mock_search.side_effect = [
                _make_web_search_result(classification_non_us),
                _make_web_search_result({}),
                _make_web_search_result({"companyType": {"value": "public", "source": "https://euronext.com"}}),
            ]
            mock_yf.return_value = {}

            await run_financial_agent(_make_state(country_code="FR"))

        mock_yf.assert_called_once_with("MC.PA")
        mock_edgar.assert_not_called()


# ---------------------------------------------------------------------------
# Classification fallback
# ---------------------------------------------------------------------------


class TestClassificationFallback:
    """Tests for graceful degradation when classification fails."""

    @pytest.mark.asyncio
    async def test_defaults_to_private_on_classification_failure(self):
        """When classification web search returns non-dict, defaults to private path."""
        from app.agents.nodes.financial import run_financial_agent

        with (
            patch("app.agents.nodes.financial.web_search_query", new_callable=AsyncMock) as mock_search,
            patch("app.agents.nodes.financial.fetch_yfinance_data", new_callable=AsyncMock) as mock_yf,
            patch("app.agents.nodes.financial.fetch_sec_edgar_data", new_callable=AsyncMock) as mock_edgar,
        ):
            mock_search.side_effect = [
                _make_web_search_result("not a dict"),  # Bad classification response
                _make_web_search_result({}),  # private search
                _make_web_search_result({}),  # news
                _make_web_search_result({}),  # synthesize
            ]

            result = await run_financial_agent(_make_state())

        # Should not call yfinance/edgar since it fell back to private
        mock_yf.assert_not_called()
        mock_edgar.assert_not_called()
        # Should still return a valid structure
        assert "agent_results" in result


# ---------------------------------------------------------------------------
# _TokenTracker
# ---------------------------------------------------------------------------


class TestTokenTracker:
    """Tests for the internal token accumulator."""

    def test_initial_state(self):
        from app.agents.nodes.financial import _TokenTracker

        tracker = _TokenTracker()
        assert tracker.input_tokens == 0
        assert tracker.output_tokens == 0
        assert tracker.sources == set()

    def test_absorb_accumulates_tokens(self):
        from app.agents.nodes.financial import _TokenTracker

        tracker = _TokenTracker()
        tracker.absorb({"input_tokens": 100, "output_tokens": 50, "sources": ["https://a.com"]})
        tracker.absorb({"input_tokens": 200, "output_tokens": 75, "sources": ["https://b.com"]})

        assert tracker.input_tokens == 300
        assert tracker.output_tokens == 125
        assert tracker.sources == {"https://a.com", "https://b.com"}

    def test_absorb_deduplicates_sources(self):
        from app.agents.nodes.financial import _TokenTracker

        tracker = _TokenTracker()
        tracker.absorb({"input_tokens": 10, "output_tokens": 5, "sources": ["https://same.com"]})
        tracker.absorb({"input_tokens": 10, "output_tokens": 5, "sources": ["https://same.com"]})

        assert len(tracker.sources) == 1

    def test_absorb_ignores_missing_fields(self):
        from app.agents.nodes.financial import _TokenTracker

        tracker = _TokenTracker()
        tracker.absorb({})  # No tokens or sources

        assert tracker.input_tokens == 0
        assert tracker.output_tokens == 0
        assert tracker.sources == set()

    def test_absorb_skips_empty_source_urls(self):
        from app.agents.nodes.financial import _TokenTracker

        tracker = _TokenTracker()
        tracker.absorb({"input_tokens": 0, "output_tokens": 0, "sources": ["", None, "https://valid.com"]})

        # Only non-empty URLs should be added
        assert "https://valid.com" in tracker.sources
        assert "" not in tracker.sources
