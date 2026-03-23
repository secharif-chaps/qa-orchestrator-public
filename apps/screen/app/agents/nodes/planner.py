"""Planner node: website reconnaissance and company brief builder."""

import asyncio

from app.agents.config import AGENT_TIMEOUT_SECONDS, ALL_AGENT_TYPES
from app.agents.nodes.base import _extract_domain, _infer_country_code
from app.agents.prompts.planner import PLANNER_SYSTEM_PROMPT
from app.agents.state import CompanyAnalysisState
from app.agents.tools.web_search import web_search_query
from app.core.logging_config import get_logger

logger = get_logger(__name__)


def _build_company_brief(planner_data: dict, website: str) -> str:
    """Build a multi-line company brief from planner output."""
    lines = []

    if planner_data.get("summary"):
        lines.append(f"Summary: {planner_data['summary']}")

    if planner_data.get("industry"):
        lines.append(f"Industry: {planner_data['industry']}")

    if planner_data.get("country"):
        lines.append(f"Country: {planner_data['country']}")

    # Discovered pages
    pages = planner_data.get("discovered_pages", {})
    if pages:
        page_lines = []
        for page_type, url in pages.items():
            if url:
                page_lines.append(f"  - {page_type}: {url}")
        if page_lines:
            lines.append("Discovered pages:")
            lines.extend(page_lines)

    # Key people
    people = planner_data.get("key_people", [])
    if people:
        lines.append(f"Key people: {', '.join(people[:10])}")

    # Brands
    brands = planner_data.get("brands", [])
    if brands:
        lines.append(f"Brands: {', '.join(brands[:10])}")

    # Subsidiaries
    subs = planner_data.get("subsidiaries", [])
    if subs:
        lines.append(f"Subsidiaries: {', '.join(subs[:10])}")

    if planner_data.get("notes"):
        lines.append(f"Notes: {planner_data['notes']}")

    return "\n".join(lines) if lines else f"Company website: {website}"


async def planner_node(state: CompanyAnalysisState) -> dict:
    """Execute website reconnaissance and build company brief.

    Returns agents_to_run (all agents), company_brief, and country_code.
    """
    company_name = state["company_name"]
    website = state["website"]

    logger.info(
        "Planner starting",
        extra={"company_name": company_name, "website": website},
    )

    user_query = f"Explore the website of {company_name}: {website}"
    company_domain = _extract_domain(website)

    try:
        result = await asyncio.wait_for(
            web_search_query(
                system_prompt=PLANNER_SYSTEM_PROMPT,
                user_query=user_query,
                agent_name="planner",
                country_code=state.get("country_code"),
                allowed_domains=[company_domain],
                search_context_size="high",
            ),
            timeout=AGENT_TIMEOUT_SECONDS,
        )

        planner_data = result.get("data", {})
        company_brief = _build_company_brief(planner_data, website)

        # Infer country code from planner data or website TLD
        country_code = planner_data.get("country") or state.get("country_code") or _infer_country_code(website)

        logger.info(
            "Planner completed",
            extra={
                "company_name": company_name,
                "country_code": country_code,
                "input_tokens": result["input_tokens"],
                "output_tokens": result["output_tokens"],
            },
        )

    except Exception as e:
        logger.error(f"Planner failed for {company_name}: {e}", exc_info=True)
        company_brief = f"Company website: {website}"
        country_code = state.get("country_code") or _infer_country_code(website)

    return {
        "agents_to_run": list(ALL_AGENT_TYPES),
        "company_brief": company_brief,
        "country_code": country_code,
    }
