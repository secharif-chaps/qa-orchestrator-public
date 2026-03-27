"""Shared agent execution logic."""

import asyncio
from urllib.parse import urlparse

from app.agents.config import AGENT_PROMPTS, AGENT_TIMEOUT_SECONDS
from app.agents.prompts.domains import AGENT_ALLOWED_DOMAINS
from app.agents.schemas import AGENT_OUTPUT_SCHEMAS
from app.agents.state import AgentResult
from app.agents.tools.enrichment_lookup import build_enrichment_tool
from app.agents.tools.web_search import web_search_query
from app.core.logging_config import get_logger

logger = get_logger(__name__)

# TLD → country code mapping
_TLD_COUNTRY_MAP: dict[str, str] = {
    "fr": "FR",
    "de": "DE",
    "uk": "GB",
    "co.uk": "GB",
    "es": "ES",
    "it": "IT",
    "nl": "NL",
    "be": "BE",
    "ch": "CH",
    "at": "AT",
    "pt": "PT",
    "se": "SE",
    "no": "NO",
    "dk": "DK",
    "fi": "FI",
    "pl": "PL",
    "cz": "CZ",
    "ie": "IE",
    "lu": "LU",
    "ro": "RO",
    "bg": "BG",
    "hr": "HR",
    "gr": "GR",
    "hu": "HU",
    "sk": "SK",
    "jp": "JP",
    "cn": "CN",
    "kr": "KR",
    "in": "IN",
    "au": "AU",
    "br": "BR",
    "ca": "CA",
    "mx": "MX",
}


def _extract_domain(website: str) -> str:
    """Extract the base domain from a website URL, stripping www."""
    parsed = urlparse(website if "://" in website else f"https://{website}")
    domain = (parsed.netloc or parsed.path).lower()
    if domain.startswith("www."):
        domain = domain[4:]
    return domain.split("/")[0]


def _infer_country_code(website: str) -> str | None:
    """Infer country code from website TLD."""
    domain = _extract_domain(website)
    parts = domain.rsplit(".", 2)

    if len(parts) >= 3:
        # Check two-part TLD first (e.g., co.uk)
        two_part = f"{parts[-2]}.{parts[-1]}"
        if two_part in _TLD_COUNTRY_MAP:
            return _TLD_COUNTRY_MAP[two_part]

    if len(parts) >= 2:
        tld = parts[-1]
        if tld in _TLD_COUNTRY_MAP:
            return _TLD_COUNTRY_MAP[tld]

    return None


def _build_allowed_domains(agent_name: str, company_domain: str) -> list[str] | None:
    """Build allowed domains list with company_domain placeholder replaced."""
    domains = AGENT_ALLOWED_DOMAINS.get(agent_name, [])
    if not domains:
        return None
    return [d.replace("{company_domain}", company_domain) for d in domains]


async def run_agent(
    agent_name: str,
    company_name: str,
    website: str,
    company_brief: str | None = None,
    country_code: str | None = None,
    enrichment_sources: list[str] | None = None,
    company_id: int | None = None,
) -> AgentResult:
    """Execute a single research agent.

    Args:
        agent_name: Name of the agent (matches keys in AGENT_PROMPTS)
        company_name: Company name for the search query
        website: Company website URL
        company_brief: Optional brief from planner node
        country_code: Optional country code for search localization
        enrichment_sources: Optional list of available enrichment sources (e.g., ["pappers", "worldcheck"])
        company_id: Optional company ID for enrichment DB lookups (required if enrichment_sources is set)

    Returns:
        AgentResult with data, sources, tokens, and timing
    """
    system_prompt = AGENT_PROMPTS.get(agent_name, "")
    company_domain = _extract_domain(website)
    output_schema = AGENT_OUTPUT_SCHEMAS.get(agent_name)

    if not country_code:
        country_code = _infer_country_code(website)

    # Build the user query
    query_parts = [
        f"Research the following company: {company_name}",
        f"Website: {website}",
    ]
    if company_brief:
        query_parts.append(f"\n=== COMPANY BRIEF (from website exploration) ===\n{company_brief}")

    user_query = "\n".join(query_parts)
    allowed_domains = _build_allowed_domains(agent_name, company_domain)

    # Build enrichment function tool if sources are available
    function_tools = None
    function_handler = None
    if enrichment_sources and company_id:
        tool_def, handler = build_enrichment_tool(company_id, enrichment_sources)
        function_tools = [tool_def]
        function_handler = handler

    try:
        result = await asyncio.wait_for(
            web_search_query(
                system_prompt=system_prompt,
                user_query=user_query,
                agent_name=agent_name,
                country_code=country_code,
                allowed_domains=allowed_domains,
                function_tools=function_tools,
                function_handler=function_handler,
                output_schema=output_schema,
            ),
            timeout=AGENT_TIMEOUT_SECONDS,
        )

        return AgentResult(
            agent_name=agent_name,
            status="success" if result["data"] else "error",
            data=result["data"],
            sources=result["sources"],
            error=None if result["data"] else "Empty response from API",
            input_tokens=result["input_tokens"],
            output_tokens=result["output_tokens"],
            duration_ms=result["duration_ms"],
        )

    except TimeoutError:
        logger.error(f"Agent {agent_name} timed out after {AGENT_TIMEOUT_SECONDS}s")
        return AgentResult(
            agent_name=agent_name,
            status="error",
            data={},
            sources=[],
            error=f"Agent timed out after {AGENT_TIMEOUT_SECONDS} seconds",
            input_tokens=0,
            output_tokens=0,
            duration_ms=AGENT_TIMEOUT_SECONDS * 1000,
        )

    except Exception as e:
        logger.error(
            f"Agent {agent_name} failed: {e}",
            exc_info=True,
            extra={"agent_name": agent_name, "company_name": company_name},
        )
        return AgentResult(
            agent_name=agent_name,
            status="error",
            data={},
            sources=[],
            error=str(e),
            input_tokens=0,
            output_tokens=0,
            duration_ms=0,
        )
