"""Financial agent node — multi-tool orchestration.

Unlike the 8 standard agents that use a single web_search_query call,
the financial agent orchestrates multiple tools across 3 steps:

  Step 1: Classify the company (public/private, ticker, exchange, US-listed)
  Step 2: Gather data in parallel from appropriate tools:
          - Public US:     yfinance + SEC EDGAR + web search news
          - Public non-US: yfinance + web search news
          - Private:       web search (funding) + web search (news)
  Step 3: Synthesize all tool outputs via LLM into FinancialAgentOutput schema

Returns the standard {"agent_results": [AgentResult]} so the rest of
the LangGraph pipeline (synthesizer, runner, task tracking) works unchanged.
"""

import asyncio
import json
import time
from typing import Any

from app.agents.config import AGENT_TIMEOUT_SECONDS
from app.agents.nodes.base import _infer_country_code
from app.agents.schemas import AGENT_OUTPUT_SCHEMAS
from app.agents.state import AgentResult, CompanyAnalysisState
from app.agents.tools.sec_edgar_tool import fetch_sec_edgar_data
from app.agents.tools.web_search import web_search_query
from app.agents.tools.yfinance_tool import fetch_yfinance_data
from app.core.logging_config import get_logger

logger = get_logger(__name__)

AGENT_NAME = "financial"

# ---------------------------------------------------------------------------
# Embedded prompts (step-specific — not in roles.py / search_targets.py)
# ---------------------------------------------------------------------------

_CLASSIFICATION_PROMPT = """You are a financial analyst. Determine if this company is publicly traded.

Search for "{company_name} stock ticker" and "{company_name} investor relations annual report".

Return JSON with exactly these keys:
{{
  "companyType": "public" or "private",
  "tickerSymbol": "AAPL" or null,
  "stockExchange": "NASDAQ" or "NYSE" or "EURONEXT" etc. or null,
  "isUSListed": true or false,
  "country": "US" or two-letter country code
}}

IMPORTANT: Return ONLY valid JSON. No markdown, no explanations."""

_SYNTHESIS_PROMPT = """You are a financial analyst synthesizing data about {company_name} into a structured profile.

You have been provided with data collected from multiple sources. Combine all this information
into a single comprehensive financial profile.

## Collected Source Data

{source_data}

## Rules
- Use the SourcedValue pattern for every field: {{"value": "...", "source": "URL or source name"}}
- For yfinance data: use source "https://finance.yahoo.com/quote/{ticker}"
- For SEC EDGAR data: use the source URL provided in the data
- For web search data: use the actual citation URLs from the data
- If data conflicts between sources, prefer: SEC EDGAR > yfinance > web search
- All monetary values must include currency symbol (e.g., "$1.2B", "€500M", "£300M")
- For private companies, leave public-only fields (marketCap, peRatio, evEbitda, etc.) as null
- The "insights" field should be a 2-3 sentence executive summary
- Populate "metrics" array for historical financial metrics with period information
- Populate "fundingRounds" for private companies' financing history

## Output Schema
{output_schema}

IMPORTANT: Return ONLY valid JSON matching the schema exactly. No markdown, no explanations."""


# ---------------------------------------------------------------------------
# Public entry point
# ---------------------------------------------------------------------------


async def run_financial_agent(state: CompanyAnalysisState) -> dict:
    """Execute the financial agent with multi-tool orchestration.

    Implements the 3-step strategy from the Jira spec:
    1. Classify (web search) → public/private, ticker, US-listed
    2. Gather (parallel tools) → yfinance, SEC EDGAR, web search
    3. Synthesize (LLM) → FinancialAgentOutput JSON
    """
    company_name = state["company_name"]
    website = state["website"]
    company_brief = state.get("company_brief")
    country_code = state.get("country_code") or _infer_country_code(website)

    start_time = time.monotonic()

    try:
        result = await asyncio.wait_for(
            _execute_pipeline(
                company_name=company_name,
                website=website,
                company_brief=company_brief,
                country_code=country_code,
            ),
            timeout=AGENT_TIMEOUT_SECONDS,
        )

        duration_ms = int((time.monotonic() - start_time) * 1000)
        return {
            "agent_results": [
                AgentResult(
                    agent_name=AGENT_NAME,
                    status="success" if result["data"] else "error",
                    data=result["data"],
                    sources=result["sources"],
                    error=None if result["data"] else "Empty financial data",
                    input_tokens=result["input_tokens"],
                    output_tokens=result["output_tokens"],
                    duration_ms=duration_ms,
                )
            ]
        }

    except TimeoutError:
        duration_ms = AGENT_TIMEOUT_SECONDS * 1000
        logger.error(
            "Financial agent timed out",
            extra={"company_name": company_name, "timeout_s": AGENT_TIMEOUT_SECONDS},
        )
        return {
            "agent_results": [
                AgentResult(
                    agent_name=AGENT_NAME,
                    status="error",
                    data={},
                    sources=[],
                    error=f"Agent timed out after {AGENT_TIMEOUT_SECONDS} seconds",
                    input_tokens=0,
                    output_tokens=0,
                    duration_ms=duration_ms,
                )
            ]
        }

    except Exception as e:
        duration_ms = int((time.monotonic() - start_time) * 1000)
        logger.error(
            "Financial agent failed",
            exc_info=True,
            extra={"company_name": company_name, "error": str(e)},
        )
        return {
            "agent_results": [
                AgentResult(
                    agent_name=AGENT_NAME,
                    status="error",
                    data={},
                    sources=[],
                    error=str(e),
                    input_tokens=0,
                    output_tokens=0,
                    duration_ms=duration_ms,
                )
            ]
        }


# ---------------------------------------------------------------------------
# Pipeline steps
# ---------------------------------------------------------------------------


async def _execute_pipeline(
    company_name: str,
    website: str,
    company_brief: str | None,
    country_code: str | None,
) -> dict[str, Any]:
    """Run the 3-step financial pipeline and return aggregated results."""
    tracker = _TokenTracker()

    # ── Step 1: Classify ─────────────────────────────────────────────────────
    classification = await _classify_company(company_name, website, country_code, tracker)

    company_type = classification.get("companyType", "private")
    ticker = classification.get("tickerSymbol")
    is_us_listed = bool(classification.get("isUSListed", False))

    logger.info(
        "Financial agent classification",
        extra={
            "company_name": company_name,
            "company_type": company_type,
            "ticker": ticker,
            "is_us_listed": is_us_listed,
        },
    )

    # ── Step 2: Gather ────────────────────────────────────────────────────────
    tool_results = await _gather_data(
        company_name=company_name,
        website=website,
        company_type=company_type,
        ticker=ticker,
        is_us_listed=is_us_listed,
        country_code=country_code,
        company_brief=company_brief,
        tracker=tracker,
    )

    # ── Step 3: Synthesize ────────────────────────────────────────────────────
    synthesis = await _synthesize(
        company_name=company_name,
        ticker=ticker,
        classification=classification,
        tool_results=tool_results,
        country_code=country_code,
        tracker=tracker,
    )

    return {
        "data": synthesis,
        "sources": sorted(tracker.sources),
        "input_tokens": tracker.input_tokens,
        "output_tokens": tracker.output_tokens,
    }


async def _classify_company(
    company_name: str,
    website: str,
    country_code: str | None,
    tracker: "_TokenTracker",
) -> dict[str, Any]:
    """Step 1: Use web search to determine company type and find ticker."""
    prompt = _CLASSIFICATION_PROMPT.format(company_name=company_name)
    user_query = (
        f"Is '{company_name}' ({website}) a publicly traded company? "
        f"Find its stock ticker symbol and stock exchange."
    )

    result = await web_search_query(
        system_prompt=prompt,
        user_query=user_query,
        agent_name=f"{AGENT_NAME}_classify",
        country_code=country_code,
    )
    tracker.absorb(result)

    data = result.get("data") or {}
    # Graceful degradation: if classification fails default to private
    if not isinstance(data, dict):
        return {"companyType": "private", "isUSListed": False}
    return data


async def _gather_data(
    company_name: str,
    website: str,
    company_type: str,
    ticker: str | None,
    is_us_listed: bool,
    country_code: str | None,
    company_brief: str | None,
    tracker: "_TokenTracker",
) -> dict[str, Any]:
    """Step 2: Dispatch to appropriate tools in parallel based on company type."""
    tasks: dict[str, asyncio.Task] = {}

    if company_type == "public" and ticker:
        # yfinance for all public companies
        tasks["yfinance"] = asyncio.create_task(fetch_yfinance_data(ticker))

        if is_us_listed:
            # SEC EDGAR for US-listed companies only
            tasks["sec_edgar"] = asyncio.create_task(
                fetch_sec_edgar_data(company_name, ticker)
            )

        # Web search: recent earnings, analyst coverage, financial news
        tasks["web_news"] = asyncio.create_task(
            _search_financial_news(company_name, ticker, country_code, tracker)
        )
    else:
        # Private company: web search for funding, revenue estimates, valuation
        tasks["web_private"] = asyncio.create_task(
            _search_private_financials(company_name, website, country_code, tracker)
        )
        # Financial news / press releases about milestones
        tasks["web_news"] = asyncio.create_task(
            _search_financial_news(company_name, None, country_code, tracker)
        )

    results: dict[str, Any] = {}
    for key, task in tasks.items():
        try:
            results[key] = await task
        except Exception as e:
            logger.warning("Financial tool %s failed: %s", key, e)
            results[key] = {}

    # Register structured tool source URLs in tracker
    if ticker and results.get("yfinance"):
        tracker.sources.add(f"https://finance.yahoo.com/quote/{ticker}")
    if results.get("sec_edgar", {}).get("source"):
        tracker.sources.add(results["sec_edgar"]["source"])

    return results


async def _search_financial_news(
    company_name: str,
    ticker: str | None,
    country_code: str | None,
    tracker: "_TokenTracker",
) -> dict[str, Any]:
    """Web search for recent financial news, earnings, and analyst coverage."""
    ticker_part = f" ({ticker})" if ticker else ""
    result = await web_search_query(
        system_prompt=(
            "You are a financial news researcher. Find factual recent financial data with sources. "
            "Return JSON with keys: recentEarnings (latest results), analystRatings, "
            "revenueEstimates, significantEvents (list of recent financial events with dates)."
        ),
        user_query=(
            f"Recent financial results and analyst coverage for {company_name}{ticker_part}. "
            f"Include latest earnings, revenue, profit, and any major financial news from the past 12 months."
        ),
        agent_name=f"{AGENT_NAME}_news",
        country_code=country_code,
    )
    tracker.absorb(result)
    return result.get("data") or {}


async def _search_private_financials(
    company_name: str,
    website: str,
    country_code: str | None,
    tracker: "_TokenTracker",
) -> dict[str, Any]:
    """Web search for private company funding rounds, valuation, and revenue."""
    result = await web_search_query(
        system_prompt=(
            "You are a private company financial researcher. Find funding, valuation, and revenue data. "
            "Return JSON with keys: totalFunding (string with currency), lastValuation, "
            "fundingRounds (list of {roundType, amount, date, leadInvestor, valuation, source}), "
            "revenueEstimate, employeeCount."
        ),
        user_query=(
            f"Financial data for {company_name} ({website}): "
            f"funding rounds, total funding raised, valuation, revenue estimates. "
            f"Check Crunchbase, TechCrunch, press releases, and investor announcements."
        ),
        agent_name=f"{AGENT_NAME}_private",
        country_code=country_code,
    )
    tracker.absorb(result)
    return result.get("data") or {}


async def _synthesize(
    company_name: str,
    ticker: str | None,
    classification: dict[str, Any],
    tool_results: dict[str, Any],
    country_code: str | None,
    tracker: "_TokenTracker",
) -> dict[str, Any]:
    """Step 3: LLM synthesizes all tool outputs into FinancialAgentOutput JSON."""
    output_schema_class = AGENT_OUTPUT_SCHEMAS.get(AGENT_NAME)
    output_schema_json = (
        json.dumps(output_schema_class.model_json_schema(), indent=2)
        if output_schema_class
        else "{}"
    )

    # Build structured source data block for the prompt
    sections: list[str] = []
    sections.append(f"### Company Classification\n{_to_json(classification)}")

    if tool_results.get("yfinance"):
        yf_source = f"https://finance.yahoo.com/quote/{ticker}" if ticker else "Yahoo Finance"
        sections.append(f"### Yahoo Finance Data (source: {yf_source})\n{_to_json(tool_results['yfinance'])}")

    if tool_results.get("sec_edgar"):
        sections.append(f"### SEC EDGAR Filing Data\n{_to_json(tool_results['sec_edgar'])}")

    if tool_results.get("web_news"):
        sections.append(f"### Financial News & Analysis\n{_to_json(tool_results['web_news'])}")

    if tool_results.get("web_private"):
        sections.append(f"### Private Company Financial Data\n{_to_json(tool_results['web_private'])}")

    source_data = "\n\n".join(sections)

    prompt = _SYNTHESIS_PROMPT.format(
        company_name=company_name,
        source_data=source_data,
        ticker=ticker or "N/A",
        output_schema=output_schema_json,
    )

    result = await web_search_query(
        system_prompt=prompt,
        user_query=f"Synthesize all collected financial data into a structured profile for {company_name}.",
        agent_name=f"{AGENT_NAME}_synthesize",
        country_code=country_code,
        output_schema=output_schema_class,
    )
    tracker.absorb(result)

    data = result.get("data") or {}
    return data if isinstance(data, dict) else {}


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


class _TokenTracker:
    """Accumulates token counts and source URLs across all tool calls."""

    def __init__(self) -> None:
        self.input_tokens: int = 0
        self.output_tokens: int = 0
        self.sources: set[str] = set()

    def absorb(self, web_search_result: dict[str, Any]) -> None:
        """Accumulate tokens and sources from a web_search_query result."""
        self.input_tokens += web_search_result.get("input_tokens", 0)
        self.output_tokens += web_search_result.get("output_tokens", 0)
        for url in web_search_result.get("sources", []):
            if url:
                self.sources.add(url)


def _to_json(data: Any) -> str:
    """Safely serialize any value to a compact JSON string."""
    try:
        return json.dumps(data, indent=2, default=str)
    except Exception:
        return str(data)
