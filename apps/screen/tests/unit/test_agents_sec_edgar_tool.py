"""Tests for SEC EDGAR tool — fetch_sec_edgar_data."""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest

# ---------------------------------------------------------------------------
# Fixtures / Helpers
# ---------------------------------------------------------------------------


def _make_company_tickers_response() -> dict:
    """Minimal company_tickers.json structure."""
    return {
        "0": {"cik_str": 320193, "ticker": "AAPL", "title": "Apple Inc."},
        "1": {"cik_str": 789019, "ticker": "MSFT", "title": "Microsoft Corp"},
        "2": {"cik_str": 1018724, "ticker": "AMZN", "title": "Amazon.com Inc"},
    }


def _make_company_facts(cik: str = "0000320193") -> dict:
    """Minimal XBRL company facts response for Apple."""
    return {
        "cik": int(cik),
        "entityName": "Apple Inc.",
        "facts": {
            "us-gaap": {
                "Revenues": {
                    "units": {
                        "USD": [
                            {"end": "2022-09-24", "val": 394_300_000_000, "form": "10-K", "filed": "2022-10-27"},
                            {"end": "2023-09-30", "val": 383_285_000_000, "form": "10-K", "filed": "2023-11-02"},
                        ]
                    }
                },
                "CostOfGoodsSold": {
                    "units": {
                        "USD": [
                            {"end": "2023-09-30", "val": 214_137_000_000, "form": "10-K", "filed": "2023-11-02"},
                        ]
                    }
                },
                "GrossProfit": {
                    "units": {
                        "USD": [
                            {"end": "2023-09-30", "val": 169_148_000_000, "form": "10-K", "filed": "2023-11-02"},
                        ]
                    }
                },
                "OperatingIncomeLoss": {
                    "units": {
                        "USD": [
                            {"end": "2023-09-30", "val": 114_301_000_000, "form": "10-K", "filed": "2023-11-02"},
                        ]
                    }
                },
                "NetIncomeLoss": {
                    "units": {
                        "USD": [
                            {"end": "2023-09-30", "val": 96_995_000_000, "form": "10-K", "filed": "2023-11-02"},
                        ]
                    }
                },
            }
        },
    }


def _make_mock_response(json_data: dict, status_code: int = 200) -> MagicMock:
    """Build a mock httpx response."""
    mock_resp = MagicMock()
    mock_resp.status_code = status_code
    mock_resp.json.return_value = json_data
    mock_resp.raise_for_status = MagicMock()
    return mock_resp


# ---------------------------------------------------------------------------
# fetch_sec_edgar_data — CIK lookup
# ---------------------------------------------------------------------------


class TestFetchSecEdgarDataCikLookup:
    """Tests for CIK resolution logic."""

    @pytest.mark.asyncio
    async def test_finds_cik_by_ticker(self):
        """Ticker lookup succeeds via company_tickers.json."""
        from app.agents.tools.sec_edgar_tool import fetch_sec_edgar_data

        tickers_resp = _make_mock_response(_make_company_tickers_response())
        facts_resp = _make_mock_response(_make_company_facts("0000320193"))

        mock_client = AsyncMock()
        mock_client.get.side_effect = [tickers_resp, facts_resp]
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("app.agents.tools.sec_edgar_tool.httpx.AsyncClient", return_value=mock_client):
            result = await fetch_sec_edgar_data("Apple Inc.", "AAPL")

        assert isinstance(result, dict)
        assert "source" in result
        assert "revenue" in result
        assert isinstance(result["revenue"], dict)
        assert "value" in result["revenue"]

    @pytest.mark.asyncio
    async def test_returns_empty_when_ticker_not_found(self):
        """Returns {} when ticker is not in company_tickers.json and fallback fails."""
        from app.agents.tools.sec_edgar_tool import fetch_sec_edgar_data

        tickers_resp = _make_mock_response(_make_company_tickers_response())
        # Fallback search returns empty
        empty_search = _make_mock_response({"hits": {"hits": []}})

        mock_client = AsyncMock()
        mock_client.get.side_effect = [tickers_resp, empty_search]
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("app.agents.tools.sec_edgar_tool.httpx.AsyncClient", return_value=mock_client):
            result = await fetch_sec_edgar_data("Unknown Corp", "UNKNWN")

        assert result == {}

    @pytest.mark.asyncio
    async def test_returns_empty_on_network_error(self):
        """Returns {} when any HTTP request raises an exception."""
        from app.agents.tools.sec_edgar_tool import fetch_sec_edgar_data

        mock_client = AsyncMock()
        mock_client.get.side_effect = Exception("Connection refused")
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("app.agents.tools.sec_edgar_tool.httpx.AsyncClient", return_value=mock_client):
            result = await fetch_sec_edgar_data("Apple Inc.", "AAPL")

        assert result == {}

    @pytest.mark.asyncio
    async def test_returns_empty_on_facts_http_404(self):
        """Returns {} when EDGAR facts endpoint returns 404."""
        from app.agents.tools.sec_edgar_tool import fetch_sec_edgar_data

        tickers_resp = _make_mock_response(_make_company_tickers_response())
        facts_resp = _make_mock_response({}, status_code=404)

        mock_client = AsyncMock()
        mock_client.get.side_effect = [tickers_resp, facts_resp]
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("app.agents.tools.sec_edgar_tool.httpx.AsyncClient", return_value=mock_client):
            result = await fetch_sec_edgar_data("Apple Inc.", "AAPL")

        assert result == {}

    @pytest.mark.asyncio
    async def test_returns_empty_without_ticker_and_no_results(self):
        """Returns {} when no ticker provided and name search returns nothing."""
        from app.agents.tools.sec_edgar_tool import fetch_sec_edgar_data

        tickers_resp = _make_mock_response(_make_company_tickers_response())
        empty_search = _make_mock_response({"hits": {"hits": []}})

        mock_client = AsyncMock()
        mock_client.get.side_effect = [tickers_resp, empty_search]
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("app.agents.tools.sec_edgar_tool.httpx.AsyncClient", return_value=mock_client):
            result = await fetch_sec_edgar_data("Private Corp", None)

        assert result == {}


# ---------------------------------------------------------------------------
# fetch_sec_edgar_data — financial extraction
# ---------------------------------------------------------------------------


class TestFetchSecEdgarDataExtraction:
    """Tests for financial data extraction from XBRL company facts."""

    @pytest.mark.asyncio
    async def test_extracts_revenue(self):
        tickers_resp = _make_mock_response(_make_company_tickers_response())
        facts_resp = _make_mock_response(_make_company_facts())

        mock_client = AsyncMock()
        mock_client.get.side_effect = [tickers_resp, facts_resp]
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        from app.agents.tools.sec_edgar_tool import fetch_sec_edgar_data

        with patch("app.agents.tools.sec_edgar_tool.httpx.AsyncClient", return_value=mock_client):
            result = await fetch_sec_edgar_data("Apple Inc.", "AAPL")

        assert "revenue" in result
        # Revenue is returned as a dict with value, period, form, filed
        revenue = result["revenue"]
        assert isinstance(revenue, dict)
        assert "value" in revenue
        assert "$" in revenue["value"]

    @pytest.mark.asyncio
    async def test_includes_source_url(self):
        tickers_resp = _make_mock_response(_make_company_tickers_response())
        facts_resp = _make_mock_response(_make_company_facts())

        mock_client = AsyncMock()
        mock_client.get.side_effect = [tickers_resp, facts_resp]
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        from app.agents.tools.sec_edgar_tool import fetch_sec_edgar_data

        with patch("app.agents.tools.sec_edgar_tool.httpx.AsyncClient", return_value=mock_client):
            result = await fetch_sec_edgar_data("Apple Inc.", "AAPL")

        assert "source" in result
        assert "sec.gov" in result["source"] or "edgar" in result["source"].lower()

    @pytest.mark.asyncio
    async def test_extracts_net_income(self):
        tickers_resp = _make_mock_response(_make_company_tickers_response())
        facts_resp = _make_mock_response(_make_company_facts())

        mock_client = AsyncMock()
        mock_client.get.side_effect = [tickers_resp, facts_resp]
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        from app.agents.tools.sec_edgar_tool import fetch_sec_edgar_data

        with patch("app.agents.tools.sec_edgar_tool.httpx.AsyncClient", return_value=mock_client):
            result = await fetch_sec_edgar_data("Apple Inc.", "AAPL")

        assert "netIncome" in result
        assert isinstance(result["netIncome"], dict)
        assert "value" in result["netIncome"]

    @pytest.mark.asyncio
    async def test_handles_missing_facts_gracefully(self):
        """Returns {} when company facts are empty or missing expected fields."""
        tickers_resp = _make_mock_response(_make_company_tickers_response())
        # Facts with no us-gaap data
        empty_facts = {"cik": 320193, "entityName": "Apple Inc.", "facts": {}}
        facts_resp = _make_mock_response(empty_facts)

        mock_client = AsyncMock()
        mock_client.get.side_effect = [tickers_resp, facts_resp]
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        from app.agents.tools.sec_edgar_tool import fetch_sec_edgar_data

        with patch("app.agents.tools.sec_edgar_tool.httpx.AsyncClient", return_value=mock_client):
            result = await fetch_sec_edgar_data("Apple Inc.", "AAPL")

        # Should have no financial keys when facts are empty
        assert isinstance(result, dict)
        financial_keys = {"revenue", "netIncome", "grossProfit", "operatingIncome", "costOfRevenue"}
        assert not financial_keys.intersection(result.keys()), (
            f"Financial keys should not be present with empty facts: {financial_keys.intersection(result.keys())}"
        )
