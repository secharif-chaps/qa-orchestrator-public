"""Enrichment orchestrator service.

Coordinates data collection from all enabled external APIs (Pappers, WorldCheck)
in parallel. Results are stored in the company_enrichments table for later
access by agents via the get_enrichment_data function tool.
"""

import asyncio
from datetime import UTC, datetime
from typing import Any

from sqlalchemy.dialects.postgresql import insert as pg_insert
from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.infrastructure.pappers.exceptions import PappersError
from app.infrastructure.worldcheck.exceptions import WorldCheckError
from app.models.company_enrichment import CompanyEnrichment
from app.models.organization import FeatureFlag
from app.services.feature_flags import has_feature
from app.services.pappers import PappersService
from app.services.worldcheck import WorldCheckService

logger = get_logger(__name__)

MAX_ERROR_LENGTH = 500


class EnrichmentService:
    """Orchestrates data collection from external APIs.

    Runs enabled enrichments in parallel and persists results to
    company_enrichments table via upsert.
    """

    @staticmethod
    def _upsert_enrichment(
        db: Session,
        company_id: int,
        source: str,
        *,
        data: dict[str, Any] | None = None,
        status: str = "success",
        error: str | None = None,
        commit: bool = True,
    ) -> None:
        """Create or update an enrichment record.

        Uses PostgreSQL INSERT ... ON CONFLICT DO UPDATE for safe upserts.
        """
        now = datetime.now(UTC)

        if error:
            error = error[:MAX_ERROR_LENGTH]

        stmt = pg_insert(CompanyEnrichment).values(
            company_id=company_id,
            source=source,
            data=data,
            status=status,
            error=error,
            fetched_at=now,
        )
        stmt = stmt.on_conflict_do_update(
            constraint="uq_company_enrichments_company_source",
            set_={
                "data": stmt.excluded.data,
                "status": stmt.excluded.status,
                "error": stmt.excluded.error,
                "fetched_at": stmt.excluded.fetched_at,
                "updated_at": now,
            },
        )
        db.execute(stmt)

        if commit:
            db.commit()

    @staticmethod
    async def _collect_pappers(
        db: Session,
        company_id: int,
        company_name: str,
        organization_id: str,
        country_code: str | None,
    ) -> dict[str, Any] | None:
        """Collect Pappers data if applicable (French companies only)."""
        # Only relevant for French companies
        if country_code and country_code.upper() != "FR":
            logger.info(
                "Skipping Pappers: not a French company",
                extra={"company_id": company_id, "country_code": country_code},
            )
            await asyncio.to_thread(
                EnrichmentService._upsert_enrichment,
                db,
                company_id,
                "pappers",
                status="skipped",
                error="Non-French company",
                commit=False,
            )
            return None

        if not has_feature(db, organization_id, FeatureFlag.PAPPERS):
            logger.info(
                "Skipping Pappers: feature not enabled",
                extra={"company_id": company_id, "organization_id": organization_id},
            )
            return None

        try:
            result = await PappersService.enrich_company(db, organization_id, company_name)
            if result:
                data = result.model_dump(mode="json")
                await asyncio.to_thread(
                    EnrichmentService._upsert_enrichment,
                    db,
                    company_id,
                    "pappers",
                    data=data,
                    commit=False,
                )
                return data
            else:
                await asyncio.to_thread(
                    EnrichmentService._upsert_enrichment,
                    db,
                    company_id,
                    "pappers",
                    status="skipped",
                    error="No match found",
                    commit=False,
                )
                return None
        except PappersError as e:
            logger.error(
                "Pappers collection failed",
                extra={"company_id": company_id, "error": str(e)},
            )
            await asyncio.to_thread(
                EnrichmentService._upsert_enrichment,
                db,
                company_id,
                "pappers",
                status="error",
                error=str(e),
                commit=False,
            )
            return None

    @staticmethod
    async def _collect_worldcheck(
        db: Session,
        company_id: int,
        company_name: str,
        organization_id: str,
    ) -> dict[str, Any] | None:
        """Collect WorldCheck screening data."""
        if not has_feature(db, organization_id, FeatureFlag.WORLDCHECK):
            logger.info(
                "Skipping WorldCheck: feature not enabled",
                extra={"company_id": company_id, "organization_id": organization_id},
            )
            return None

        try:
            response = await WorldCheckService.screen_company(db, organization_id, company_name)
            data = response.model_dump(mode="json")
            await asyncio.to_thread(
                EnrichmentService._upsert_enrichment,
                db,
                company_id,
                "worldcheck",
                data=data,
                commit=False,
            )
            return data
        except WorldCheckError as e:
            logger.error(
                "WorldCheck collection failed",
                extra={"company_id": company_id, "error": str(e)},
            )
            await asyncio.to_thread(
                EnrichmentService._upsert_enrichment,
                db,
                company_id,
                "worldcheck",
                status="error",
                error=str(e),
                commit=False,
            )
            return None
        except Exception as e:
            logger.error(
                "WorldCheck collection failed (unexpected)",
                extra={"company_id": company_id, "error": str(e)},
            )
            await asyncio.to_thread(
                EnrichmentService._upsert_enrichment,
                db,
                company_id,
                "worldcheck",
                status="error",
                error=str(e),
                commit=False,
            )
            return None

    @staticmethod
    async def collect(
        db: Session,
        company_id: int,
        company_name: str,
        organization_id: str,
        country_code: str | None = None,
    ) -> dict[str, dict[str, Any]]:
        """Execute all enabled enrichments in parallel.

        - Pappers: only if country_code == "FR" (or unknown) and flag enabled
        - WorldCheck: if flag enabled

        Individual failures are caught and logged - one failure does not
        block the others.

        Args:
            db: Database session
            company_id: Company primary key
            company_name: Company name for API searches
            organization_id: Keycloak organization UUID
            country_code: Optional country code (e.g., "FR", "DE")

        Returns:
            Dict mapping source name to API data, e.g., {"pappers": {...}, "worldcheck": {...}}
            Only includes sources that returned data successfully.
        """
        logger.info(
            "Starting data collection",
            extra={
                "company_id": company_id,
                "company_name": company_name,
                "country_code": country_code,
            },
        )

        # Run all collectors in parallel — each handles its own exceptions
        pappers_task = EnrichmentService._collect_pappers(db, company_id, company_name, organization_id, country_code)
        worldcheck_task = EnrichmentService._collect_worldcheck(db, company_id, company_name, organization_id)

        pappers_data, worldcheck_data = await asyncio.gather(pappers_task, worldcheck_task)

        # Single commit for all upserts in one transaction
        await asyncio.to_thread(db.commit)

        # Build result dict with only successful collections
        result: dict[str, dict[str, Any]] = {}

        if isinstance(pappers_data, dict):
            result["pappers"] = pappers_data

        if isinstance(worldcheck_data, dict):
            result["worldcheck"] = worldcheck_data

        logger.info(
            "Data collection completed",
            extra={
                "company_id": company_id,
                "sources_collected": list(result.keys()),
            },
        )

        return result
