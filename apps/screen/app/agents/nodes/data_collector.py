"""Data collector node for the company analysis graph.

Fetches structured data from enabled external APIs (Pappers, WorldCheck)
and stores results in the company_enrichments table. Runs after the planner
node (which provides country_code) and before agent fan-out.
"""

import asyncio

from app.agents.state import CompanyAnalysisState
from app.core.logging_config import get_logger
from app.database import SessionLocal
from app.services.enrichment_service import EnrichmentService

logger = get_logger(__name__)


async def data_collector_node(state: CompanyAnalysisState) -> dict:
    """Fetch structured data from enabled external APIs.

    Checks organization feature flags and calls APIs in parallel.
    Results are persisted to company_enrichments table and passed
    to downstream agents via state.

    Args:
        state: Current graph state (needs company_id, organization_id, country_code)

    Returns:
        Dict with enrichment_data to merge into state
    """
    company_id = state["company_id"]
    company_name = state["company_name"]
    organization_id = state["organization_id"]
    country_code = state.get("country_code")

    logger.info(
        "Data collector starting",
        extra={
            "company_id": company_id,
            "company_name": company_name,
            "country_code": country_code,
        },
    )

    db = await asyncio.to_thread(SessionLocal)
    try:
        enrichment_data = await EnrichmentService.collect(
            db=db,
            company_id=company_id,
            company_name=company_name,
            organization_id=organization_id,
            country_code=country_code,
        )

        logger.info(
            "Data collector completed",
            extra={
                "company_id": company_id,
                "sources_collected": list(enrichment_data.keys()),
            },
        )

        return {"enrichment_data": enrichment_data}

    except Exception:
        logger.error(
            "Data collector failed",
            exc_info=True,
            extra={"company_id": company_id},
        )
        # Return empty enrichment_data so the pipeline continues
        return {"enrichment_data": {}}

    finally:
        await asyncio.to_thread(db.close)
