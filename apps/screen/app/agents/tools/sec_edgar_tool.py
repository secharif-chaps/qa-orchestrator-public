"""SEC EDGAR filing data fetcher for US-listed companies.

Uses the free SEC EDGAR XBRL API (no authentication required).
Fetches structured financial statement data from 10-K annual filings.

This tool is a pure data fetch — no LLM involved.
Only useful for US-listed companies (those with SEC filings).
"""

from typing import Any

import httpx

from app.core.logging_config import get_logger

logger = get_logger(__name__)

# SEC EDGAR API endpoints
_TICKERS_URL = "https://www.sec.gov/files/company_tickers.json"
_COMPANY_FACTS_URL = "https://data.sec.gov/api/xbrl/companyfacts/CIK{cik}.json"
_EDGAR_COMPANY_PAGE = "https://www.sec.gov/cgi-bin/browse-edgar?action=getcompany&CIK={cik}&type=10-K"

# SEC requires a descriptive User-Agent with contact info
_HEADERS = {
    "User-Agent": "ChapsMind/1.0 (contact@chapsvision.com)",
    "Accept": "application/json",
}

# XBRL us-gaap taxonomy concepts to extract, in priority order (first match wins)
_CONCEPT_MAP: dict[str, list[str]] = {
    "revenue": [
        "Revenues",
        "RevenueFromContractWithCustomerExcludingAssessedTax",
        "RevenueFromContractWithCustomerIncludingAssessedTax",
        "SalesRevenueNet",
        "SalesRevenueGoodsNet",
    ],
    "costOfRevenue": [
        "CostOfRevenue",
        "CostOfGoodsAndServicesSold",
        "CostOfGoodsSold",
    ],
    "grossProfit": [
        "GrossProfit",
    ],
    "operatingIncome": [
        "OperatingIncomeLoss",
    ],
    "netIncome": [
        "NetIncomeLoss",
        "NetIncomeLossAvailableToCommonStockholdersBasic",
    ],
}


async def fetch_sec_edgar_data(company_name: str, ticker: str | None = None) -> dict[str, Any]:
    """Fetch financial data from SEC EDGAR for a US-listed company.

    Strategy:
    1. Find the company's CIK number (via ticker match or name search)
    2. Fetch XBRL company facts (structured financial data)
    3. Extract most recent 10-K annual financial statement data

    Args:
        company_name: Company name for search
        ticker: Optional ticker symbol (improves CIK lookup accuracy)

    Returns:
        dict with financial statement data + source URL.
        Returns empty dict if company not found or on any error.
    """
    try:
        async with httpx.AsyncClient(headers=_HEADERS, timeout=30.0) as client:
            cik = await _find_cik(client, company_name, ticker)
            if not cik:
                logger.info(
                    "No SEC EDGAR CIK found",
                    extra={"company_name": company_name, "ticker": ticker},
                )
                return {}

            facts = await _fetch_company_facts(client, cik)
            if not facts:
                return {}

            financials = _extract_annual_financials(facts)
            financials["source"] = _EDGAR_COMPANY_PAGE.format(cik=cik.lstrip("0"))
            financials["cik"] = cik

            logger.info(
                "SEC EDGAR fetch complete",
                extra={"company_name": company_name, "cik": cik, "fields_found": len(financials)},
            )
            return financials

    except Exception as e:
        logger.warning(
            "SEC EDGAR fetch failed",
            extra={"company_name": company_name, "error": str(e)},
        )
        return {}


async def _find_cik(
    client: httpx.AsyncClient,
    company_name: str,
    ticker: str | None,
) -> str | None:
    """Look up a company's CIK number from SEC EDGAR."""
    # Strategy 1: Use the company_tickers.json file (fast, covers ~10k companies)
    try:
        resp = await client.get(_TICKERS_URL)
        if resp.status_code == 200:
            tickers_data = resp.json()
            for entry in tickers_data.values():
                if ticker and entry.get("ticker", "").upper() == ticker.upper():
                    return str(entry["cik_str"]).zfill(10)
            # Fallback: name match
            company_lower = company_name.lower()
            for entry in tickers_data.values():
                if company_lower in entry.get("title", "").lower():
                    return str(entry["cik_str"]).zfill(10)
    except Exception as e:
        logger.debug("company_tickers.json lookup failed: %s", e)

    # Strategy 2: EDGAR full-text search
    try:
        query = ticker or company_name
        resp = await client.get(
            "https://efts.sec.gov/LATEST/search-index",
            params={"q": f'"{query}"', "category": "form-type", "forms": "10-K"},
        )
        if resp.status_code == 200:
            data = resp.json()
            hits = data.get("hits", {}).get("hits", [])
            if hits:
                entity_id = hits[0].get("_source", {}).get("entity_id", "")
                if entity_id:
                    return str(entity_id).zfill(10)
    except Exception as e:
        logger.debug("EDGAR full-text search failed: %s", e)

    return None


async def _fetch_company_facts(
    client: httpx.AsyncClient,
    cik: str,
) -> dict[str, Any] | None:
    """Fetch XBRL company facts from SEC EDGAR data API."""
    url = _COMPANY_FACTS_URL.format(cik=cik)
    try:
        resp = await client.get(url)
        if resp.status_code == 200:
            return resp.json()
        logger.debug("Company facts returned %s for CIK %s", resp.status_code, cik)
    except Exception as e:
        logger.debug("Company facts fetch failed: %s", e)
    return None


def _extract_annual_financials(facts: dict[str, Any]) -> dict[str, Any]:
    """Extract most recent 10-K annual financial data from XBRL facts.

    Returns a flat dict with financial line items.
    Values are formatted as human-readable strings.
    """
    result: dict[str, Any] = {}
    us_gaap = facts.get("facts", {}).get("us-gaap", {})

    for our_key, concept_names in _CONCEPT_MAP.items():
        # Companies switch XBRL concepts over time (e.g. Apple uses "Revenues" up to
        # FY2018, then switches to "RevenueFromContractWithCustomerExcludingAssessedTax").
        # Pick the concept whose most-recent 10-K entry has the latest end date.
        best_entry = None
        for concept_name in concept_names:
            concept = us_gaap.get(concept_name, {})
            usd_values = concept.get("units", {}).get("USD", [])
            if not usd_values:
                continue

            annual = [v for v in usd_values if v.get("form") == "10-K"]
            if not annual:
                continue

            annual.sort(key=lambda x: x.get("end", ""), reverse=True)
            latest = annual[0]

            if best_entry is None or latest.get("end", "") > best_entry.get("end", ""):
                best_entry = latest

        if best_entry:
            result[our_key] = {
                "value": _format_currency(best_entry.get("val", 0)),
                "period": best_entry.get("end", ""),
                "form": best_entry.get("form", "10-K"),
                "filed": best_entry.get("filed", ""),
            }

    return result


def _format_currency(value: int | float) -> str:
    """Format a USD value with B/M suffix."""
    abs_val = abs(value)
    sign = "-" if value < 0 else ""
    if abs_val >= 1_000_000_000:
        return f"{sign}${abs_val / 1_000_000_000:.2f}B"
    if abs_val >= 1_000_000:
        return f"{sign}${abs_val / 1_000_000:.2f}M"
    return f"{sign}${abs_val:,.0f}"
