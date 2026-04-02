"""Sanctions and compliance agent node.

Extracts sanctions, regulatory enforcement actions, and compliance issues
from WorldCheck screening results using an LLM to analyze and classify them.

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

SYSTEM_PROMPT = """You are a compliance and sanctions analyst. You receive WorldCheck screening results
for a company and must identify sanctions, regulatory enforcement actions, and compliance issues.

WORLDCHECK SOURCE TYPES TO LOOK FOR:
- Regulatory Enforcement: Actions by regulatory bodies (competition authorities, data protection agencies, etc.)
- Law Enforcement: Criminal investigations and prosecutions
- Sanctions Lists: ONU, EU, OFAC, and other international sanctions
- Adverse Media: Negative news related to compliance issues
- PEP (Politically Exposed Persons): If entity is linked to PEP concerns

SANCTION TYPE CLASSIFICATION:
- unfair_competition: Anti-competitive practices, price fixing, market abuse
- data_protection: GDPR violations, data breaches, privacy issues
- consumer_protection: Consumer rights violations, misleading practices
- ip_rights_infringement: Patent, trademark, copyright violations
- regulatory_enforcement: General regulatory actions not fitting other categories
- financial_crime: Securities fraud, market manipulation, insider trading
- corruption: Bribery, corruption charges
- money_laundering: AML violations, suspicious transactions
- terrorism_financing: Financing of terrorism
- tax_evasion: Tax fraud, tax evasion schemes
- sanctions_violation: Violation of international sanctions regimes
- environmental: Environmental law violations, pollution
- other: Any other compliance issue

RISK LEVEL ASSESSMENT:
- low: Minor regulatory issues, resolved matters, low-impact fines
- medium: Significant regulatory actions, moderate fines, ongoing investigations
- high: Major enforcement actions, large fines, criminal proceedings
- critical: Sanctions list entries (ONU/EU/OFAC), terrorism financing, active criminal prosecution

ONU/EU/OFAC FLAG:
Set is_onu_eu_ofac=true ONLY if the entity appears on:
- United Nations sanctions lists
- European Union sanctions lists
- US OFAC (Office of Foreign Assets Control) SDN list
- Other major international sanctions lists

RULES:
- Extract ALL sanctions/enforcement items from the WorldCheck data
- For each item, classify the sanction_type and assess risk_level
- Extract weblinks from the weblinks array when available - preserve the full objects with uri, caption, and date
- IMPORTANT: Always include ALL weblinks from the WorldCheck data for each item. Do NOT omit any weblinks.
- Use the "categories" field to help classify items
- Use countryLinks to determine the country
- Provide a brief description for each item
- Compute an overall_risk_level based on the most severe item
- Write insights summarizing the compliance picture

OUTPUT FORMAT (strict JSON):
{
  "overall_risk_level": "medium",
  "overall_risk_justification": "Brief explanation of overall risk assessment",
  "insights": "Summary paragraph about the company's sanctions and compliance profile",
  "items": [
    {
      "entity_name": "COMPANY NAME",
      "country": "France",
      "sanction_nature": "Regulatory Enforcement - Competition Authority",
      "description": "Brief description of the sanction or enforcement action",
      "source_code": "FRAC",
      "sanction_type": "unfair_competition",
      "date": "2024-12-06",
      "weblinks": [{"uri": "https://...", "caption": "Article title", "date": "2024-01-15"}],
      "is_onu_eu_ofac": false,
      "risk_level": "medium",
      "risk_justification": "Brief explanation of risk assessment for this item"
    }
  ]
}

If no sanctions or enforcement items are found, return:
{
  "overall_risk_level": "low",
  "overall_risk_justification": "No sanctions or enforcement actions found in WorldCheck screening",
  "insights": "No sanctions, regulatory enforcement actions, or compliance issues were identified.",
  "items": []
}

Do NOT include corporate structure entities - only sanctions and compliance items."""


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
