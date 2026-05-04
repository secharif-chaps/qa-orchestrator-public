"""Enrichment orchestrator service.

Coordinates data collection from all enabled external APIs (Pappers, WorldCheck,
EPO) in parallel. Results are stored in the company_enrichments table for later
access by agents via the get_enrichment_data function tool.
"""

import asyncio
from datetime import UTC, datetime
from typing import Any

from sqlalchemy.dialects.postgresql import insert as pg_insert
from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.database import SessionLocal
from app.infrastructure.epo.exceptions import EpoError
from app.infrastructure.pappers.exceptions import PappersError
from app.infrastructure.worldcheck.exceptions import WorldCheckError
from app.models.company_enrichment import CompanyEnrichment
from app.models.organization import FeatureFlag
from app.services.epo import (
    EpoCredentialsMissingError,
    EpoFeatureNotEnabledError,
    EpoService,
)
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
    async def _collect_epo(
        db: Session,
        company_id: int,
        company_name: str,
        organization_id: str,
    ) -> dict[str, dict[str, Any]]:
        """Collect EPO publications, families and legal status for a company.

        Runs on a dedicated SQLAlchemy session so the chain of sync DB
        reads inside ``EpoService`` does not race with the shared session
        used by Pappers/WorldCheck upserts running concurrently in
        asyncio.gather. All three EPO upserts are committed atomically
        through a single commit at the end of the block.

        Flow:
        1. Collect publications (``source="epo_publications"``).
        2. If publications succeed with at least one doc_id, collect
           families and legal status in parallel via ``asyncio.gather``
           with ``return_exceptions=True`` — one source failing does not
           block the other.
        3. Persist each outcome (success or error) through
           ``_persist_or_error``.

        Returns:
            Mapping from source name to successful data payload. Only
            keys for successful (non-error) collections are included;
            possible keys are ``epo_publications``, ``epo_families`` and
            ``epo_legal``. Empty dict when the feature is off or the
            publications call fails.
        """
        collected: dict[str, dict[str, Any]] = {}

        if not has_feature(db, organization_id, FeatureFlag.EPO):
            logger.info(
                "Skipping EPO: feature not enabled",
                extra={"company_id": company_id, "organization_id": organization_id},
            )
            return collected

        local_db: Session = SessionLocal()
        try:
            publications_data = await EnrichmentService._collect_epo_publications(
                local_db, company_id, company_name, organization_id
            )
            if publications_data is not None:
                collected["epo_publications"] = publications_data

                doc_ids = [patent["doc_id"] for patent in publications_data.get("patents", []) if patent.get("doc_id")]
                if doc_ids:
                    families_outcome, legal_outcome = await asyncio.gather(
                        EpoService.get_patent_families(local_db, organization_id, doc_ids),
                        EpoService.get_legal_status(local_db, organization_id, doc_ids),
                        return_exceptions=True,
                    )
                    families_data = await asyncio.to_thread(
                        EnrichmentService._persist_or_error,
                        local_db,
                        company_id,
                        "epo_families",
                        families_outcome,
                    )
                    if families_data is not None:
                        collected["epo_families"] = families_data

                    legal_data = await asyncio.to_thread(
                        EnrichmentService._persist_or_error,
                        local_db,
                        company_id,
                        "epo_legal",
                        legal_outcome,
                    )
                    if legal_data is not None:
                        collected["epo_legal"] = legal_data

            await asyncio.to_thread(local_db.commit)
        finally:
            await asyncio.to_thread(local_db.close)

        return collected

    @staticmethod
    async def _collect_epo_publications(
        local_db: Session,
        company_id: int,
        company_name: str,
        organization_id: str,
    ) -> dict[str, Any] | None:
        """Fetch EPO publications and upsert the record (shared-session helper).

        Returns the data dict on success (even when the patent list is
        empty — agents need to distinguish "not searched" from "searched,
        nothing found"), or ``None`` when the collection failed and an
        error record was persisted instead. The upsert is staged with
        ``commit=False``; the caller commits at the end.
        """
        try:
            data = await EpoService.enrich_company(local_db, organization_id, company_name)
        except (EpoError, EpoFeatureNotEnabledError, EpoCredentialsMissingError) as e:
            logger.error(
                "EPO publications collection failed",
                extra={"company_id": company_id, "error": str(e)},
            )
            await asyncio.to_thread(
                EnrichmentService._upsert_enrichment,
                local_db,
                company_id,
                "epo_publications",
                status="error",
                error=str(e),
                commit=False,
            )
            return None
        except Exception as e:
            logger.error(
                "EPO publications collection failed (unexpected)",
                extra={"company_id": company_id, "error": str(e)},
            )
            await asyncio.to_thread(
                EnrichmentService._upsert_enrichment,
                local_db,
                company_id,
                "epo_publications",
                status="error",
                error=str(e),
                commit=False,
            )
            return None

        await asyncio.to_thread(
            EnrichmentService._upsert_enrichment,
            local_db,
            company_id,
            "epo_publications",
            data=data,
            commit=False,
        )
        return data

    @staticmethod
    def _persist_or_error(
        db: Session,
        company_id: int,
        source: str,
        outcome: object,
    ) -> dict[str, Any] | None:
        """Upsert a gather-outcome: dict → success, Exception → error.

        Runs synchronously so it can be scheduled with ``to_thread``.
        Returns the payload on success or ``None`` when the outcome was
        an exception.
        """
        if isinstance(outcome, dict):
            EnrichmentService._upsert_enrichment(
                db,
                company_id,
                source,
                data=outcome,
                commit=False,
            )
            return outcome

        if isinstance(outcome, Exception):
            logger.error(
                "EPO sub-source collection failed",
                extra={"company_id": company_id, "source": source, "error": str(outcome)},
            )
            EnrichmentService._upsert_enrichment(
                db,
                company_id,
                source,
                status="error",
                error=str(outcome),
                commit=False,
            )
            return None

        # Defensive: gather should only yield dict or Exception given the
        # signatures upstream, but if the contract changes we want a loud failure.
        logger.error(
            "EPO sub-source collection yielded unexpected type",
            extra={"company_id": company_id, "source": source, "type": type(outcome).__name__},
        )
        EnrichmentService._upsert_enrichment(
            db,
            company_id,
            source,
            status="error",
            error=f"Unexpected outcome type: {type(outcome).__name__}",
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
        - EPO: if flag enabled. Persists up to three records:
          ``epo_publications`` (always when EPO succeeds), plus
          ``epo_families`` and ``epo_legal`` when the publication list is
          non-empty.

        Individual failures are caught and logged - one failure does not
        block the others.

        Args:
            db: Database session
            company_id: Company primary key
            company_name: Company name for API searches
            organization_id: Keycloak organization UUID
            country_code: Optional country code (e.g., "FR", "DE")

        Returns:
            Dict mapping source name to API data, e.g.,
            {"pappers": {...}, "worldcheck": {...},
             "epo_publications": {...}, "epo_families": {...},
             "epo_legal": {...}}.
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
        epo_task = EnrichmentService._collect_epo(db, company_id, company_name, organization_id)

        pappers_data, worldcheck_data, epo_data = await asyncio.gather(pappers_task, worldcheck_task, epo_task)

        # Single commit for all upserts in one transaction
        await asyncio.to_thread(db.commit)

        # Build result dict with only successful collections
        result: dict[str, dict[str, Any]] = {}

        if isinstance(pappers_data, dict):
            result["pappers"] = pappers_data

        if isinstance(worldcheck_data, dict):
            result["worldcheck"] = worldcheck_data

        if isinstance(epo_data, dict):
            result.update(epo_data)

        logger.info(
            "Data collection completed",
            extra={
                "company_id": company_id,
                "sources_collected": list(result.keys()),
            },
        )

        return result
