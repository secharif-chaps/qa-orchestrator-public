"""Yahoo Finance data fetcher for public company financial data.

Wraps the yfinance library to fetch market data, valuation ratios,
and fundamentals for publicly traded companies.

This tool is a pure data fetch — no LLM involved.
All values are returned as strings to match DB Text column types.
"""

import asyncio
from typing import Any

from app.core.logging_config import get_logger

logger = get_logger(__name__)

# Maps yfinance .info keys to our output field names
_INFO_FIELD_MAP: dict[str, str] = {
    # Market data
    "currentPrice": "currentPrice",
    "marketCap": "marketCap",
    "beta": "beta",
    # Valuation ratios
    "trailingPE": "peRatio",
    "enterpriseToEbitda": "evEbitda",
    "enterpriseToRevenue": "evRevenue",
    "enterpriseValue": "enterpriseValue",
    # Fundamentals
    "totalRevenue": "revenue",
    "revenueGrowth": "revenueGrowth",
    "grossMargins": "grossMargin",
    "ebitdaMargins": "ebitdaMargin",
    "profitMargins": "netMargin",
    "freeCashflow": "freeCashFlow",
    "debtToEquity": "debtToEquity",
    # Metadata
    "sector": "sector",
    "industry": "industry",
    "exchange": "exchange",
    "currency": "currency",
    "fullTimeEmployees": "employeeCount",
}

# Fields where the value is a ratio/percentage (multiply by 100 for %)
_PERCENTAGE_FIELDS = {"grossMargin", "ebitdaMargin", "netMargin", "revenueGrowth"}

# Fields representing large monetary amounts
_LARGE_MONEY_FIELDS = {"marketCap", "revenue", "enterpriseValue", "freeCashFlow"}


async def fetch_yfinance_data(ticker: str) -> dict[str, Any]:
    """Fetch financial data from Yahoo Finance for a given ticker symbol.

    Runs the blocking yfinance call in a thread executor to avoid
    blocking the async event loop.

    Args:
        ticker: Stock ticker symbol (e.g., "AAPL", "MSFT", "MC.PA")

    Returns:
        dict with financial data keyed by our standard field names.
        All values are strings. Returns empty dict on any failure.
    """
    ticker = ticker.strip().upper()
    if not ticker:
        return {}

    try:
        data = await asyncio.to_thread(_fetch_sync, ticker)
        logger.info(
            "yfinance fetch complete",
            extra={"ticker": ticker, "fields_found": len(data)},
        )
        return data
    except Exception as e:
        logger.warning(
            "yfinance fetch failed",
            extra={"ticker": ticker, "error": str(e)},
        )
        return {}


def _fetch_sync(ticker: str) -> dict[str, Any]:
    """Synchronous yfinance fetch — runs in a thread pool worker."""
    import yfinance as yf  # lazy import to avoid slowing module load

    stock = yf.Ticker(ticker)
    info = stock.info or {}

    # Validate we got real data (yfinance returns partial dict for invalid tickers)
    if not info.get("regularMarketPrice") and not info.get("currentPrice") and not info.get("trailingPE"):
        logger.debug("yfinance returned empty info for ticker %s", ticker)
        return {}

    result: dict[str, Any] = {}
    for yf_key, our_key in _INFO_FIELD_MAP.items():
        value = info.get(yf_key)
        if value is not None:
            result[our_key] = _format_value(our_key, value)

    return result


def _format_value(key: str, value: Any) -> str:
    """Format a raw yfinance value to a human-readable string."""
    if isinstance(value, (int, float)):
        if key in _PERCENTAGE_FIELDS:
            return f"{float(value) * 100:.1f}%"
        if key in _LARGE_MONEY_FIELDS:
            return _format_large_number(float(value))
        if key == "debtToEquity":
            return f"{float(value):.2f}"
        if key in ("beta", "peRatio", "evEbitda", "evRevenue"):
            return f"{float(value):.2f}"
    return str(value)


def _format_large_number(value: float) -> str:
    """Format large numbers with T/B/M suffixes."""
    abs_val = abs(value)
    sign = "-" if value < 0 else ""
    if abs_val >= 1_000_000_000_000:
        return f"{sign}{abs_val / 1_000_000_000_000:.2f}T"
    if abs_val >= 1_000_000_000:
        return f"{sign}{abs_val / 1_000_000_000:.2f}B"
    if abs_val >= 1_000_000:
        return f"{sign}{abs_val / 1_000_000:.2f}M"
    return f"{sign}{abs_val:,.0f}"
