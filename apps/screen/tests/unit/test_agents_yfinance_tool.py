"""Tests for yfinance tool — fetch_yfinance_data."""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.agents.tools.yfinance_tool import _format_value, fetch_yfinance_data

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def _make_yf_info(**overrides) -> dict:
    """Build a minimal yfinance info dict that passes the validity check."""
    base = {
        "currentPrice": 182.52,
        "marketCap": 2_800_000_000_000,
        "trailingPE": 29.5,
        "beta": 1.2,
        "enterpriseValue": 2_900_000_000_000,
        "totalRevenue": 394_300_000_000,
        "revenueGrowth": 0.085,
        "grossMargins": 0.441,
        "ebitdaMargins": 0.312,
        "profitMargins": 0.246,
        "freeCashflow": 90_000_000_000,
        "debtToEquity": 150.2,
        "fullTimeEmployees": 160_000,
        "sector": "Technology",
        "industry": "Consumer Electronics",
        "exchange": "NMS",
        "currency": "USD",
    }
    base.update(overrides)
    return base


# ---------------------------------------------------------------------------
# fetch_yfinance_data
# ---------------------------------------------------------------------------


class TestFetchYfinanceData:
    """Tests for the public async entry point."""

    @pytest.mark.asyncio
    async def test_returns_dict_on_success(self):
        with patch("app.agents.tools.yfinance_tool.asyncio.to_thread", new_callable=AsyncMock) as mock_thread:
            mock_thread.return_value = {"currentPrice": "$182.52", "sector": "Technology"}
            result = await fetch_yfinance_data("AAPL")

        assert isinstance(result, dict)
        assert result["currentPrice"] == "$182.52"

    @pytest.mark.asyncio
    async def test_strips_and_uppercases_ticker(self):
        with patch("app.agents.tools.yfinance_tool.asyncio.to_thread", new_callable=AsyncMock) as mock_thread:
            mock_thread.return_value = {}
            await fetch_yfinance_data("  aapl  ")
            # The call to to_thread passes the _fetch_sync function and the ticker
            args = mock_thread.call_args[0]
            assert args[1] == "AAPL"

    @pytest.mark.asyncio
    async def test_returns_empty_dict_for_blank_ticker(self):
        with patch("app.agents.tools.yfinance_tool.asyncio.to_thread", new_callable=AsyncMock) as mock_thread:
            result = await fetch_yfinance_data("   ")

        assert result == {}
        mock_thread.assert_not_called()

    @pytest.mark.asyncio
    async def test_returns_empty_dict_on_exception(self):
        with patch("app.agents.tools.yfinance_tool.asyncio.to_thread", new_callable=AsyncMock) as mock_thread:
            mock_thread.side_effect = Exception("Network error")
            result = await fetch_yfinance_data("INVALID")

        assert result == {}


# ---------------------------------------------------------------------------
# _fetch_sync (via mocked yfinance)
# ---------------------------------------------------------------------------


class TestFetchSync:
    """Tests for the sync helper that calls yfinance library."""

    def _call_fetch_sync(self, info: dict) -> dict:
        """Helper to call _fetch_sync with a mocked yfinance Ticker."""
        from app.agents.tools.yfinance_tool import _fetch_sync

        mock_ticker = MagicMock()
        mock_ticker.info = info

        with patch.dict("sys.modules", {"yfinance": MagicMock(Ticker=MagicMock(return_value=mock_ticker))}):
            return _fetch_sync("AAPL")

    def test_maps_current_price(self):
        result = self._call_fetch_sync(_make_yf_info(currentPrice=182.52))
        # currentPrice is not in _LARGE_MONEY_FIELDS or _PERCENTAGE_FIELDS, returned as str(value)
        assert result.get("currentPrice") == "182.52"

    def test_maps_market_cap_trillions(self):
        result = self._call_fetch_sync(_make_yf_info(marketCap=2_800_000_000_000))
        # Large money fields use T/B/M suffix, no $ prefix
        assert result.get("marketCap") == "2.80T"

    def test_maps_sector_and_industry(self):
        result = self._call_fetch_sync(_make_yf_info())
        assert result.get("sector") == "Technology"
        assert result.get("industry") == "Consumer Electronics"

    def test_maps_percentage_fields(self):
        result = self._call_fetch_sync(
            _make_yf_info(revenueGrowth=0.085, grossMargins=0.441, ebitdaMargins=0.312, profitMargins=0.246)
        )
        assert "%" in result.get("revenueGrowth", ""), "revenueGrowth should be a percentage string"
        assert "%" in result.get("grossMargin", ""), "grossMargin should be a percentage string"
        assert "%" in result.get("ebitdaMargin", ""), "ebitdaMargin should be a percentage string"
        assert "%" in result.get("netMargin", ""), "netMargin should be a percentage string"

    def test_skips_none_values(self):
        info = _make_yf_info()
        info["beta"] = None
        result = self._call_fetch_sync(info)
        assert "beta" not in result

    def test_returns_empty_for_invalid_ticker(self):
        from app.agents.tools.yfinance_tool import _fetch_sync

        mock_ticker = MagicMock()
        mock_ticker.info = {}  # Empty info = invalid ticker

        with patch.dict("sys.modules", {"yfinance": MagicMock(Ticker=MagicMock(return_value=mock_ticker))}):
            result = _fetch_sync("INVALID")

        assert result == {}

    def test_returns_empty_when_no_price_or_pe(self):
        from app.agents.tools.yfinance_tool import _fetch_sync

        # Info has data but none of the validity-check fields
        mock_ticker = MagicMock()
        mock_ticker.info = {"sector": "Technology", "industry": "Software"}

        with patch.dict("sys.modules", {"yfinance": MagicMock(Ticker=MagicMock(return_value=mock_ticker))}):
            result = _fetch_sync("NOPRICE")

        assert result == {}


# ---------------------------------------------------------------------------
# _format_value
# ---------------------------------------------------------------------------


class TestFormatValue:
    """Tests for the value formatting helper."""

    def test_formats_percentage_gross_margin(self):
        result = _format_value("grossMargin", 0.441)
        assert result == "44.1%"

    def test_formats_percentage_revenue_growth(self):
        result = _format_value("revenueGrowth", 0.085)
        assert result == "8.5%"

    def test_formats_large_number_trillions(self):
        result = _format_value("marketCap", 2_800_000_000_000)
        # Large money fields: T/B/M suffix, no $ prefix
        assert result == "2.80T"

    def test_formats_large_number_billions(self):
        result = _format_value("marketCap", 1_500_000_000)
        assert result == "1.50B"

    def test_formats_large_number_millions(self):
        result = _format_value("revenue", 500_000_000)
        assert result == "500.00M"

    def test_formats_price_as_string(self):
        # currentPrice not in special fields, returned as str(value)
        result = _format_value("currentPrice", 182.52)
        assert result == "182.52"

    def test_string_passthrough(self):
        result = _format_value("sector", "Technology")
        assert result == "Technology"

    def test_integer_passthrough(self):
        result = _format_value("employeeCount", 160_000)
        assert result == "160000"

    def test_formats_debt_to_equity(self):
        result = _format_value("debtToEquity", 150.2)
        assert result == "150.20"

    def test_float_non_percentage(self):
        # beta uses 2 decimal format
        result = _format_value("beta", 1.2)
        assert result == "1.20"
