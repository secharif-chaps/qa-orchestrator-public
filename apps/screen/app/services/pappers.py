"""Pappers enrichment service.

Business logic for enriching company data from the Pappers French
business registry API. Checks feature flags and credentials before
making API calls.
"""

from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.infrastructure.pappers.client import PappersClient
from app.infrastructure.pappers.exceptions import PappersError
from app.infrastructure.pappers.schemas import PappersCompanyData
from app.models.organization import FeatureFlag
from app.services.feature_flags import get_feature_config, has_feature

logger = get_logger(__name__)


class PappersFeatureNotEnabledError(Exception):
    """Raised when Pappers feature flag is not enabled for the organization."""

    def __init__(self, organization_id: str):
        self.organization_id = organization_id
        super().__init__(f"Pappers feature is not enabled for organization {organization_id}")


class PappersCredentialsMissingError(Exception):
    """Raised when Pappers credentials are missing or incomplete."""

    def __init__(self, organization_id: str):
        self.organization_id = organization_id
        super().__init__(f"Pappers credentials not configured for organization {organization_id}")


class PappersService:
    """Service for Pappers company data enrichment.

    Retrieves credentials from the organization's feature flag config
    and delegates to PappersClient for API calls.
    """

    @staticmethod
    def _get_client(db: Session, organization_id: str) -> PappersClient:
        """Create a PappersClient with credentials from feature flags.

        Raises:
            PappersFeatureNotEnabledError: If feature flag is disabled
            PappersCredentialsMissingError: If api_key is missing
        """
        if not has_feature(db, organization_id, FeatureFlag.PAPPERS):
            raise PappersFeatureNotEnabledError(organization_id)

        config = get_feature_config(db, organization_id, FeatureFlag.PAPPERS)
        if not config:
            raise PappersCredentialsMissingError(organization_id)

        api_key = config.get("api_key")
        if not api_key:
            raise PappersCredentialsMissingError(organization_id)

        return PappersClient(api_key=api_key)

    @staticmethod
    async def enrich_company(
        db: Session,
        organization_id: str,
        company_name: str,
    ) -> PappersCompanyData | None:
        """Enrich company data from Pappers.

        Searches by name, takes the best match, then fetches full data by SIREN.

        Args:
            db: Database session
            organization_id: Keycloak organization UUID
            company_name: Company name to search

        Returns:
            PappersCompanyData if found, None if no match

        Raises:
            PappersFeatureNotEnabledError: If Pappers not enabled
            PappersCredentialsMissingError: If credentials incomplete
            PappersError: On API errors
        """
        client = PappersService._get_client(db, organization_id)

        logger.info(
            "Starting Pappers company enrichment",
            extra={"organization_id": organization_id, "company_name": company_name},
        )

        try:
            results = await client.search_company(company_name)
            if not results:
                logger.info(
                    "No Pappers results found",
                    extra={"organization_id": organization_id, "company_name": company_name},
                )
                return None

            # Take best match (first result)
            best_match = results[0]
            logger.info(
                "Pappers match found, fetching full data",
                extra={
                    "siren": best_match.siren,
                    "matched_name": best_match.nom_entreprise,
                },
            )

            company_data = await client.get_company(best_match.siren)

            logger.info(
                "Pappers enrichment completed",
                extra={
                    "organization_id": organization_id,
                    "siren": company_data.siren,
                },
            )

            return company_data

        except PappersError:
            logger.error(
                "Pappers enrichment failed",
                exc_info=True,
                extra={"organization_id": organization_id, "company_name": company_name},
            )
            raise
