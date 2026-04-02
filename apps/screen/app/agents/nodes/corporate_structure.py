"""Corporate structure agent node.

Extracts subsidiaries/related entities from WorldCheck screening results
using an LLM to filter false positives and categorize entities.

Unlike other agents that do web search, this agent reads raw_worldcheck_knowledge
from the database and uses the LLM to analyze the structured WC data.
"""

import asyncio
import json
import time

from app.agents.config import LLM_MODEL
from app.agents.state import AgentResult, CompanyAnalysisState
from app.core.llm import get_chat_client
from app.core.logging_config import get_logger
from app.database import SessionLocal
from app.models.company import Company

logger = get_logger(__name__)

SYSTEM_PROMPT = """You are a corporate intelligence analyst. You receive WorldCheck screening results
for a company and must identify related corporate entities (parent companies, subsidiaries,
affiliates, branches, regional entities).

RULES:
- matchedNameType = "AKA" with matchStrength = "EXACT" or "STRONG" → high confidence subsidiary/affiliate
- matchedNameType = "PRIMARY" with matchStrength = "EXACT" → likely the screened entity itself (skip it)
- matchStrength = "MEDIUM" with a name very different from the screened company → likely false positive (skip it)
- matchStrength = "MEDIUM" with a name similar to the screened company → potential subsidiary (include with caution)
- Use countryLinks when available to determine the country
- When countryLinks is empty, INFER the country from the entity name (e.g. "SEPHORA POLSKA SP Z OO" → Poland, "SEPHORA USA INC." → United States, "SEPHORA COSMETICS ROMANIA SA" → Romania)
- Use legal suffixes to help: SP Z OO = Poland, INC = USA, SA = Romania/France, GmbH = Germany, Ltd = UK, SRL = Italy/Romania, BV = Netherlands

RELATIONSHIP TYPES:
- parent: Parent company or holding group (only if you can identify it from the data)
- subsidiary: Owned subsidiary (company with the same brand name in another country)
- affiliate: Related entity (not directly owned but clearly associated)
- branch: Local branch/office of the same company
- regional_entity: Regional division/operation

IMPORTANT: You MUST fill ALL fields for every entity. Never leave country empty.

OUTPUT FORMAT (strict JSON):
{
  "entities": [
    {
      "name": {"value": "Entity Name", "source": "worldcheck"},
      "type": "subsidiary",
      "country": {"value": "Country Name", "source": "worldcheck"},
      "wc_reference_id": "referenceId value from the input data",
      "match_strength": "EXACT or STRONG or MEDIUM",
      "ai_reasoning": "Brief explanation of classification"
    }
  ]
}

If no related entities are found, return: {"entities": []}
Do NOT include the screened company itself in the results."""


async def run_corporate_structure_agent(state: CompanyAnalysisState) -> dict:
    """Extract corporate structure from WorldCheck data.

    Reads raw_worldcheck_knowledge from the database, sends it to the LLM
    for analysis, and returns structured entity data.
    """
    company_id = state["company_id"]
    company_name = state["company_name"]
    start_time = time.time()

    logger.info(
        "Starting corporate structure extraction from WorldCheck",
        extra={"company_id": company_id, "company_name": company_name},
    )

    # Read WorldCheck data from database (wrapped to avoid blocking the event loop)
    def _read_wc_data() -> str | None:
        db = SessionLocal()
        try:
            company = db.query(Company).filter(Company.id == company_id).first()
            return company.raw_worldcheck_knowledge if company else None
        finally:
            db.close()

    raw_wc = await asyncio.to_thread(_read_wc_data)

    if not raw_wc:
        logger.info(
            "No WorldCheck data available, returning empty result",
            extra={"company_id": company_id},
        )
        return {
            "agent_results": [
                AgentResult(
                    agent_name="corporate_structure",
                    status="success",
                    data={"entities": []},
                    sources=["worldcheck"],
                    error=None,
                    input_tokens=0,
                    output_tokens=0,
                    duration_ms=0,
                )
            ]
        }

    # Parse WC data and prepare a summary for the LLM
    try:
        wc_data = json.loads(raw_wc)
        results = wc_data.get("results", [])
    except (json.JSONDecodeError, TypeError):
        logger.warning("Failed to parse WorldCheck data", extra={"company_id": company_id})
        return {
            "agent_results": [
                AgentResult(
                    agent_name="corporate_structure",
                    status="error",
                    data={},
                    sources=[],
                    error="Failed to parse WorldCheck data",
                    input_tokens=0,
                    output_tokens=0,
                    duration_ms=int((time.time() - start_time) * 1000),
                )
            ]
        }

    # Build a concise summary of WC matches for the LLM
    wc_summary = []
    for r in results:
        entry = {
            "referenceId": r.get("referenceId"),
            "primaryName": r.get("primaryName"),
            "matchedNameType": r.get("matchedNameType"),
            "matchStrength": r.get("matchStrength"),
            "categories": [c.get("name") for c in r.get("categories", []) if c.get("name")],
        }

        # Extract country info from profile
        profile = r.get("profile", {}) or {}
        country_links = profile.get("countryLinks", []) or []
        entry["countries"] = [
            {"name": cl.get("country", {}).get("name"), "type": cl.get("type")}
            for cl in country_links
            if cl.get("country", {}).get("name")
        ]

        entry["entityType"] = profile.get("entityType")

        wc_summary.append(entry)

    user_message = (
        f"Company being screened: {company_name}\n\n"
        f"WorldCheck screening results ({len(wc_summary)} matches):\n"
        f"{json.dumps(wc_summary, indent=2)}\n\n"
        f"Analyze these results and extract related corporate entities. "
        f"Filter out false positives and the screened company itself."
    )

    # Call LLM via Chat Completions
    try:
        client = get_chat_client()

        response = await client.chat.completions.create(
            model=LLM_MODEL,
            messages=[
                {"role": "system", "content": SYSTEM_PROMPT},
                {"role": "user", "content": user_message},
            ],
            response_format={"type": "json_object"},
            temperature=0.1,
        )

        content = response.choices[0].message.content
        input_tokens = response.usage.prompt_tokens if response.usage else 0
        output_tokens = response.usage.completion_tokens if response.usage else 0

        # Parse LLM response
        parsed = json.loads(content)
        entities = parsed.get("entities", [])

        duration_ms = int((time.time() - start_time) * 1000)

        logger.info(
            "Corporate structure extraction complete",
            extra={
                "company_id": company_id,
                "entities_found": len(entities),
                "input_tokens": input_tokens,
                "output_tokens": output_tokens,
                "duration_ms": duration_ms,
            },
        )

        return {
            "agent_results": [
                AgentResult(
                    agent_name="corporate_structure",
                    status="success",
                    data={"entities": entities},
                    sources=["worldcheck"],
                    error=None,
                    input_tokens=input_tokens,
                    output_tokens=output_tokens,
                    duration_ms=duration_ms,
                )
            ]
        }

    except Exception as e:
        duration_ms = int((time.time() - start_time) * 1000)
        logger.error(
            "Corporate structure LLM call failed",
            exc_info=True,
            extra={"company_id": company_id, "error": str(e)},
        )
        return {
            "agent_results": [
                AgentResult(
                    agent_name="corporate_structure",
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
