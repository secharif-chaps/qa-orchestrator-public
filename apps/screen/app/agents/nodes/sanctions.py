"""Sanctions and compliance agent node.

Extracts sanctions, regulatory enforcement actions, and compliance issues
from WorldCheck screening results using an LLM to analyze and classify them.

Unlike other agents that do web search, this agent reads raw_worldcheck_knowledge
from the database and uses the LLM to analyze the structured WC data.
"""

import asyncio
import json
import time

from app.agents.config import LLM_MODEL, PROMPTS_REGISTRY
from app.agents.state import AgentResult, CompanyAnalysisState
from app.core.llm import get_chat_client
from app.core.logging_config import get_logger
from app.database import SessionLocal
from app.models.company import Company

logger = get_logger(__name__)


async def run_sanctions_agent(state: CompanyAnalysisState) -> dict:
    """Extract sanctions and compliance data from WorldCheck screening results.

    Reads raw_worldcheck_knowledge from the database, sends it to the LLM
    for analysis, and returns structured sanctions data.
    """
    company_id = state["company_id"]
    company_name = state["company_name"]
    start_time = time.time()

    logger.info(
        "Starting sanctions extraction from WorldCheck",
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
                    agent_name="sanctions",
                    status="success",
                    data={
                        "overall_risk_level": "low",
                        "overall_risk_justification": "No WorldCheck data available",
                        "insights": "No WorldCheck screening data available for sanctions analysis.",
                        "items": [],
                    },
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
                    agent_name="sanctions",
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

    # Build a detailed summary of WC matches for the LLM, including sources and categories
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

        # Extract source information for sanctions classification
        sources = profile.get("sources", []) or []
        entry["sources"] = [
            {
                "name": s.get("name"),
                "abbreviation": s.get("abbreviation"),
                "type": s.get("type", {}).get("name") if isinstance(s.get("type"), dict) else s.get("type"),
            }
            for s in sources
            if s.get("name")
        ]

        # Extract weblinks from profile-level weblinks field
        profile_weblinks = profile.get("weblinks", []) or []
        weblinks = []
        for wl in profile_weblinks:
            uri = wl.get("uri")
            if uri:
                weblink_entry = {"uri": uri}
                if wl.get("caption"):
                    weblink_entry["caption"] = wl["caption"]
                if wl.get("date"):
                    weblink_entry["date"] = wl["date"]
                weblinks.append(weblink_entry)

        # Also extract URLs from sources details as fallback
        for s in sources:
            for detail in s.get("details", []) or []:
                for prop in detail.get("properties", []) or []:
                    if prop.get("name") == "URL" and prop.get("value"):
                        uri = prop["value"]
                        if not any(w["uri"] == uri for w in weblinks):
                            weblinks.append({"uri": uri})
        if weblinks:
            entry["weblinks"] = weblinks

        wc_summary.append(entry)

    user_message = (
        f"Company being screened: {company_name}\n\n"
        f"WorldCheck screening results ({len(wc_summary)} matches):\n"
        f"{json.dumps(wc_summary, indent=2)}\n\n"
        f"Analyze these results and extract all sanctions, regulatory enforcement actions, "
        f"and compliance issues. Classify each item and assess the risk level."
    )

    # Call LLM via Chat Completions
    try:
        client = get_chat_client()

        response = await client.chat.completions.create(
            model=LLM_MODEL,
            messages=[
                {"role": "system", "content": PROMPTS_REGISTRY["sanctions"]},
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

        duration_ms = int((time.time() - start_time) * 1000)

        logger.info(
            "Sanctions extraction complete",
            extra={
                "company_id": company_id,
                "items_found": len(parsed.get("items", [])),
                "overall_risk_level": parsed.get("overall_risk_level"),
                "input_tokens": input_tokens,
                "output_tokens": output_tokens,
                "duration_ms": duration_ms,
            },
        )

        return {
            "agent_results": [
                AgentResult(
                    agent_name="sanctions",
                    status="success",
                    data=parsed,
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
            "Sanctions LLM call failed",
            exc_info=True,
            extra={"company_id": company_id, "error": str(e)},
        )
        return {
            "agent_results": [
                AgentResult(
                    agent_name="sanctions",
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
