"""Enrichment lookup function tool for LangGraph agents.

Provides a function tool definition and handler that agents (profile, team)
can use to retrieve pre-fetched structured data from the company_enrichments
table during their reasoning process.
"""

import copy
import json
from collections.abc import Callable

from app.core.logging_config import get_logger
from app.database import SessionLocal
from app.models.company_enrichment import CompanyEnrichment

logger = get_logger(__name__)

ENRICHMENT_TOOL_DEFINITION: dict = {
    "type": "function",
    "name": "get_enrichment_data",
    "description": (
        "Retrieve pre-fetched structured data for this company from an external API. "
        "Available sources: pappers (French business registry: SIREN, legal form, capital, "
        "officers, financials), worldcheck (due diligence screening: sanctions, PEP matches)."
    ),
    "parameters": {
        "type": "object",
        "properties": {
            "source": {
                "type": "string",
                "enum": ["pappers", "worldcheck"],
                "description": "The data source to query",
            }
        },
        "required": ["source"],
    },
}


def build_enrichment_tool(
    company_id: int,
    available_sources: list[str],
) -> tuple[dict, Callable[[dict], str]]:
    """Build tool definition + handler for a specific company.

    The tool definition is customized to only list available sources in the enum.
    The handler reads from the company_enrichments table.

    Args:
        company_id: Company primary key for DB lookups
        available_sources: List of sources that have data (e.g., ["pappers", "worldcheck"])

    Returns:
        Tuple of (tool_config_dict, handler_function)
    """

    def handle_call(arguments: dict) -> str:
        """Handle a get_enrichment_data function call from the LLM."""
        source = arguments.get("source", "")

        if source not in available_sources:
            return json.dumps({"error": f"No {source} data available for this company"})

        db = SessionLocal()
        try:
            enrichment = (
                db.query(CompanyEnrichment)
                .filter(
                    CompanyEnrichment.company_id == company_id,
                    CompanyEnrichment.source == source,
                    CompanyEnrichment.status == "success",
                )
                .first()
            )

            if enrichment and enrichment.data:
                logger.info(
                    "Enrichment data retrieved by agent",
                    extra={"company_id": company_id, "source": source},
                )
                return json.dumps(enrichment.data)

            return json.dumps({"error": f"No {source} data found for this company"})

        except Exception:
            logger.error(
                "Failed to retrieve enrichment data",
                exc_info=True,
                extra={"company_id": company_id, "source": source},
            )
            return json.dumps({"error": "Enrichment data temporarily unavailable"})

        finally:
            db.close()

    # Customize tool definition to only show available sources
    tool_def = copy.deepcopy(ENRICHMENT_TOOL_DEFINITION)
    tool_def["parameters"]["properties"]["source"]["enum"] = available_sources

    return tool_def, handle_call
