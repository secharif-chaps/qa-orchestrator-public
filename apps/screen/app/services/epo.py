"""EPO patent enrichment service.

Business logic layer for EPO OPS patent collection. Fetches the most
recent patents published under an applicant name, then collects
bibliographic data and abstracts for each.

Credentials (Consumer Key + Consumer Secret) are retrieved from the
organization's `epo` feature flag config and decrypted on the fly.
"""

from __future__ import annotations

import asyncio
from typing import Any

from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.infrastructure.epo.client import EpoClient
from app.infrastructure.epo.exceptions import EpoError, EpoNotFoundError
from app.infrastructure.epo.schemas import (
    PatentFamily,
    PatentLegalStatus,
    PatentSearchEntry,
)
from app.models.organization import FeatureFlag
from app.services.feature_flags import get_feature_config, has_feature

logger = get_logger(__name__)

DEFAULT_MAX_PATENTS = 50
# Cap concurrent biblio/abstract fan-out to stay polite on the EPO quota;
# the client still retries individual 403s with exponential backoff.
DEFAULT_PATENT_CONCURRENCY = 5


class EpoFeatureNotEnabledError(Exception):
    """Raised when the EPO feature flag is not enabled for the organization."""

    def __init__(self, organization_id: str) -> None:
        self.organization_id = organization_id
        super().__init__(f"EPO feature is not enabled for organization {organization_id}")


class EpoCredentialsMissingError(Exception):
    """Raised when EPO Consumer Key or Consumer Secret is missing."""

    def __init__(self, organization_id: str) -> None:
        self.organization_id = organization_id
        super().__init__(f"EPO credentials not configured for organization {organization_id}")


class EpoService:
    """Service for EPO patent enrichment.

    Retrieves Consumer Key / Consumer Secret from the organization's
    feature flag config and delegates to the EpoClient for the actual
    OAuth2 + REST calls.
    """

    @staticmethod
    def _get_client(db: Session, organization_id: str) -> EpoClient:
        """Build an authenticated EpoClient for the organization.

        Raises:
            EpoFeatureNotEnabledError: If ``FeatureFlag.EPO`` is disabled.
            EpoCredentialsMissingError: If ``api_key`` or ``api_secret`` is
                missing or empty in the feature flag config.
        """
        if not has_feature(db, organization_id, FeatureFlag.EPO):
            raise EpoFeatureNotEnabledError(organization_id)

        config = get_feature_config(db, organization_id, FeatureFlag.EPO)
        if not config:
            raise EpoCredentialsMissingError(organization_id)

        consumer_key = config.get("api_key")
        consumer_secret = config.get("api_secret")
        if not consumer_key or not consumer_secret:
            raise EpoCredentialsMissingError(organization_id)

        return EpoClient(consumer_key=consumer_key, consumer_secret=consumer_secret)

    @staticmethod
    def _build_applicant_query(company_name: str) -> str:
        """Turn a raw company name into an EPO applicant-name CQL token.

        Strips whitespace, drops inner quotes that would break the CQL
        expression (``pa="..."``), and appends the ``*`` wildcard so
        variants like "AIRBUS SAS", "AIRBUS DEFENCE AND SPACE" match.
        """
        cleaned = company_name.strip().replace('"', "")
        return f"{cleaned}*"

    @staticmethod
    def _sort_entries_by_publication_desc(
        entries: list[PatentSearchEntry],
    ) -> list[PatentSearchEntry]:
        """Sort search entries by publication_date descending; None last."""
        return sorted(
            entries,
            key=lambda e: (
                e.publication_date is not None,  # dated entries sort ahead of undated
                e.publication_date,
            ),
            reverse=True,
        )

    @staticmethod
    async def _fetch_patent_detail(
        client: EpoClient,
        entry: PatentSearchEntry,
        semaphore: asyncio.Semaphore,
    ) -> dict[str, Any] | None:
        """Fetch biblio + abstract for a single patent.

        Returns ``None`` when the biblio call fails for this patent (the
        bibliographic record is the anchor — without it the entry is
        useless). An abstract failure leaves ``abstract=None`` on an
        otherwise-complete record.
        """
        async with semaphore:
            biblio_task = client.get_biblio(entry.doc_id)
            abstract_task = client.get_abstract(entry.doc_id)
            biblio_result, abstract_result = await asyncio.gather(biblio_task, abstract_task, return_exceptions=True)

        if isinstance(biblio_result, Exception):
            # Missing bibliographic data → log and drop this patent.
            level = logger.info if isinstance(biblio_result, EpoNotFoundError) else logger.warning
            level(
                "Skipping EPO patent: biblio fetch failed",
                extra={"doc_id": entry.doc_id, "error": str(biblio_result)},
            )
            return None

        patent: dict[str, Any] = {
            "doc_id": biblio_result.doc_id,
            "title": biblio_result.title,
            "applicants": list(biblio_result.applicants),
            "inventors": list(biblio_result.inventors),
            "publication_date": biblio_result.publication_date.isoformat() if biblio_result.publication_date else None,
            "application_date": biblio_result.application_date.isoformat() if biblio_result.application_date else None,
            "abstract": None,
            "abstract_lang": None,
        }

        if isinstance(abstract_result, Exception):
            level = logger.info if isinstance(abstract_result, EpoNotFoundError) else logger.warning
            level(
                "EPO abstract unavailable",
                extra={"doc_id": entry.doc_id, "error": str(abstract_result)},
            )
        else:
            patent["abstract"] = abstract_result.text
            patent["abstract_lang"] = abstract_result.lang

        return patent

    @staticmethod
    async def enrich_company(
        db: Session,
        organization_id: str,
        company_name: str,
        *,
        max_patents: int = DEFAULT_MAX_PATENTS,
    ) -> dict[str, Any]:
        """Collect the most recent patents for a company.

        Searches the EPO ``published-data`` index with a wildcard on the
        applicant name, keeps the ``max_patents`` most recent entries
        (by publication date, most recent first), then collects biblio +
        abstract for each in bounded parallelism.

        Args:
            db: Database session, used to resolve credentials.
            organization_id: Keycloak organization UUID.
            company_name: Raw company name. A trailing ``*`` is appended
                to widen the match (``AIRBUS*`` → AIRBUS, AIRBUS SAS…).
            max_patents: Cap on the number of detailed records fetched.

        Returns:
            A plain-JSON dict with the shape expected by
            ``company_enrichments.data``. An empty ``patents`` list is a
            valid success (no patents found) — callers should still
            persist the record.

        Raises:
            EpoFeatureNotEnabledError: EPO flag is off.
            EpoCredentialsMissingError: Consumer Key / Secret missing.
            EpoError: Any transport-level failure from the client (auth,
                quota, parsing, network). Caller is expected to log and
                treat the whole collection as errored.
        """
        client = EpoService._get_client(db, organization_id)
        applicant_query = EpoService._build_applicant_query(company_name)

        logger.info(
            "Starting EPO patent search",
            extra={
                "organization_id": organization_id,
                "applicant_query": applicant_query,
                "max_patents": max_patents,
            },
        )

        search_result = await client.search_patents(applicant_query)

        sorted_entries = EpoService._sort_entries_by_publication_desc(search_result.entries)
        selected = sorted_entries[:max_patents]

        if not selected:
            logger.info(
                "EPO search returned no patents",
                extra={
                    "organization_id": organization_id,
                    "applicant_query": applicant_query,
                    "total_results": search_result.total_results,
                },
            )
            return {
                "applicant_query": applicant_query,
                "total_results": search_result.total_results,
                "patents": [],
            }

        semaphore = asyncio.Semaphore(DEFAULT_PATENT_CONCURRENCY)
        detail_tasks = [EpoService._fetch_patent_detail(client, entry, semaphore) for entry in selected]
        details = await asyncio.gather(*detail_tasks)
        patents = [d for d in details if d is not None]

        logger.info(
            "EPO patent enrichment completed",
            extra={
                "organization_id": organization_id,
                "applicant_query": applicant_query,
                "total_results": search_result.total_results,
                "selected": len(selected),
                "retained": len(patents),
            },
        )

        return {
            "applicant_query": applicant_query,
            "total_results": search_result.total_results,
            "patents": patents,
        }

    @staticmethod
    async def _fetch_family(
        client: EpoClient,
        doc_id: str,
        semaphore: asyncio.Semaphore,
    ) -> PatentFamily | None:
        """Fetch the family/biblio payload for a single ``doc_id``.

        Returns ``None`` on ``EpoNotFoundError`` (EPO has no family record
        for this publication) or any other ``EpoError``; individual
        failures never block the rest of the fan-out.
        """
        async with semaphore:
            try:
                return await client.get_family(doc_id)
            except EpoNotFoundError as exc:
                logger.info(
                    "EPO family not found",
                    extra={"doc_id": doc_id, "error": str(exc)},
                )
                return None
            except EpoError as exc:
                logger.warning(
                    "EPO family fetch failed",
                    extra={"doc_id": doc_id, "error": str(exc)},
                )
                return None

    @staticmethod
    async def _fetch_legal(
        client: EpoClient,
        doc_id: str,
        semaphore: asyncio.Semaphore,
    ) -> PatentLegalStatus | None:
        """Fetch the legal-status payload for a single ``doc_id``.

        Returns ``None`` on ``EpoNotFoundError`` or any other ``EpoError``.
        """
        async with semaphore:
            try:
                return await client.get_legal(doc_id)
            except EpoNotFoundError as exc:
                logger.info(
                    "EPO legal not found",
                    extra={"doc_id": doc_id, "error": str(exc)},
                )
                return None
            except EpoError as exc:
                logger.warning(
                    "EPO legal fetch failed",
                    extra={"doc_id": doc_id, "error": str(exc)},
                )
                return None

    @staticmethod
    async def get_patent_families(
        db: Session,
        organization_id: str,
        doc_ids: list[str],
    ) -> dict[str, Any]:
        """Collect patent families (with biblio) for a list of publications.

        Fans out ``client.get_family`` calls under a semaphore bound by
        ``DEFAULT_PATENT_CONCURRENCY``. A per-``doc_id`` failure drops
        that entry from the result; the method itself only raises when
        credentials cannot be resolved or a search-level EPO error
        escapes the individual fetch.

        Args:
            db: Database session, used to resolve credentials.
            organization_id: Keycloak organization UUID.
            doc_ids: Publications to look up (docdb format).

        Returns:
            Dict with a ``families`` key: list of dicts, each carrying
            ``doc_id``, ``family_size``, ``family_members`` and
            ``cpc_classifications``. Empty input yields
            ``{"families": []}`` without an EPO call.

        Raises:
            EpoFeatureNotEnabledError: EPO flag is off.
            EpoCredentialsMissingError: Consumer Key / Secret missing.
        """
        if not doc_ids:
            return {"families": []}

        client = EpoService._get_client(db, organization_id)

        logger.info(
            "Starting EPO family collection",
            extra={
                "organization_id": organization_id,
                "doc_ids_count": len(doc_ids),
            },
        )

        semaphore = asyncio.Semaphore(DEFAULT_PATENT_CONCURRENCY)
        tasks = [EpoService._fetch_family(client, doc_id, semaphore) for doc_id in doc_ids]
        results = await asyncio.gather(*tasks)

        families = [family.model_dump(mode="json") for family in results if family is not None]

        logger.info(
            "EPO family collection completed",
            extra={
                "organization_id": organization_id,
                "requested": len(doc_ids),
                "retained": len(families),
            },
        )

        return {"families": families}

    @staticmethod
    async def get_legal_status(
        db: Session,
        organization_id: str,
        doc_ids: list[str],
    ) -> dict[str, Any]:
        """Collect legal-status histories for a list of publications.

        Same fan-out / error-isolation strategy as ``get_patent_families``.
        Each retained entry carries the raw events plus a simplified
        bucket (``active`` / ``expired`` / ``pending`` / ``unknown``)
        derived server-side so agents do not need to know EPO event codes.

        Args:
            db: Database session, used to resolve credentials.
            organization_id: Keycloak organization UUID.
            doc_ids: Publications to look up (docdb format).

        Returns:
            Dict with a ``legal_statuses`` key. Empty input yields
            ``{"legal_statuses": []}`` without an EPO call.

        Raises:
            EpoFeatureNotEnabledError: EPO flag is off.
            EpoCredentialsMissingError: Consumer Key / Secret missing.
        """
        if not doc_ids:
            return {"legal_statuses": []}

        client = EpoService._get_client(db, organization_id)

        logger.info(
            "Starting EPO legal collection",
            extra={
                "organization_id": organization_id,
                "doc_ids_count": len(doc_ids),
            },
        )

        semaphore = asyncio.Semaphore(DEFAULT_PATENT_CONCURRENCY)
        tasks = [EpoService._fetch_legal(client, doc_id, semaphore) for doc_id in doc_ids]
        results = await asyncio.gather(*tasks)

        legal_statuses = [status.model_dump(mode="json") for status in results if status is not None]

        logger.info(
            "EPO legal collection completed",
            extra={
                "organization_id": organization_id,
                "requested": len(doc_ids),
                "retained": len(legal_statuses),
            },
        )

        return {"legal_statuses": legal_statuses}


# Re-export EpoError so callers can catch `(EpoFeatureNotEnabledError, EpoCredentialsMissingError, EpoError)` from this module.
__all__ = [
    "DEFAULT_MAX_PATENTS",
    "DEFAULT_PATENT_CONCURRENCY",
    "EpoCredentialsMissingError",
    "EpoError",
    "EpoFeatureNotEnabledError",
    "EpoService",
]
