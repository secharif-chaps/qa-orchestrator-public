"""WorldCheck screening service.

Business logic layer for WorldCheck One entity screening. Handles
credential retrieval from feature flags, client instantiation, and
screening orchestration.
"""

from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.infrastructure.worldcheck.client import WorldCheckClient
from app.infrastructure.worldcheck.exceptions import WorldCheckError
from app.infrastructure.worldcheck.schemas import EntityType, ScreeningResponse
from app.models.organization import FeatureFlag
from app.services.feature_flags import get_feature_config, has_feature

logger = get_logger(__name__)


class WorldCheckFeatureNotEnabledError(Exception):
    """Raised when WorldCheck feature flag is not enabled for the organization."""

    def __init__(self, organization_id: str):
        self.organization_id = organization_id
        super().__init__(f"WorldCheck feature is not enabled for organization {organization_id}")


class WorldCheckCredentialsMissingError(Exception):
    """Raised when WorldCheck credentials are missing or incomplete."""

    def __init__(self, organization_id: str):
        self.organization_id = organization_id
        super().__init__(f"WorldCheck credentials not configured for organization {organization_id}")


class WorldCheckService:
    """Service for WorldCheck entity screening operations.

    Retrieves credentials from the organization's feature flag config
    and delegates to the WorldCheckClient for API calls.
    """

    @staticmethod
    def _get_client(db: Session, organization_id: str) -> WorldCheckClient:
        """Create a WorldCheckClient with credentials from feature flags.

        Args:
            db: Database session
            organization_id: Keycloak organization UUID

        Returns:
            Configured WorldCheckClient instance

        Raises:
            WorldCheckFeatureNotEnabledError: If feature flag is disabled
            WorldCheckCredentialsMissingError: If api_key or api_secret missing
        """
        if not has_feature(db, organization_id, FeatureFlag.WORLDCHECK):
            raise WorldCheckFeatureNotEnabledError(organization_id)

        config = get_feature_config(db, organization_id, FeatureFlag.WORLDCHECK)
        if not config:
            raise WorldCheckCredentialsMissingError(organization_id)

        api_key = config.get("api_key")
        api_secret = config.get("api_secret")

        if not api_key or not api_secret:
            raise WorldCheckCredentialsMissingError(organization_id)

        return WorldCheckClient(api_key=api_key, api_secret=api_secret)

    @staticmethod
    async def _resolve_group_id(client: WorldCheckClient) -> str:
        """Auto-detect the first available WorldCheck screening group.

        Raises:
            WorldCheckError: If no groups are available
        """
        groups = await client.get_groups()
        if not groups:
            raise WorldCheckError("No WorldCheck screening groups available")
        group_id = groups[0].get("id", "")
        if not group_id:
            raise WorldCheckError("WorldCheck group has no id")
        logger.info(f"Auto-detected WorldCheck group: {group_id} ({groups[0].get('name', '')})")
        return group_id

    @staticmethod
    async def screen_company(
        db: Session,
        organization_id: str,
        company_name: str,
    ) -> ScreeningResponse:
        """Screen a company against WorldCheck databases.

        Auto-detects the screening group from the WorldCheck API.

        Args:
            db: Database session
            organization_id: Keycloak organization UUID
            company_name: Company name to screen

        Returns:
            ScreeningResponse with case info and match results

        Raises:
            WorldCheckFeatureNotEnabledError: If WorldCheck not enabled
            WorldCheckCredentialsMissingError: If credentials incomplete
            WorldCheckError: On API errors
        """
        client = WorldCheckService._get_client(db, organization_id)
        group_id = await WorldCheckService._resolve_group_id(client)

        logger.info(
            "Starting WorldCheck company screening",
            extra={
                "organization_id": organization_id,
                "group_id": group_id,
            },
        )

        try:
            response = await client.screen_entity(
                name=company_name,
                entity_type=EntityType.ORGANISATION,
                group_id=group_id,
            )

            logger.info(
                "WorldCheck company screening completed",
                extra={
                    "organization_id": organization_id,
                    "case_system_id": response.caseSystemId,
                    "result_count": response.resultCount,
                },
            )

            return response

        except WorldCheckError:
            logger.error(
                "WorldCheck screening failed",
                exc_info=True,
                extra={"organization_id": organization_id},
            )
            raise

    @staticmethod
    async def screen_individual(
        db: Session,
        organization_id: str,
        name: str,
        secondary_fields: list[dict] | None = None,
    ) -> ScreeningResponse:
        """Screen an individual against WorldCheck databases.

        Auto-detects the screening group from the WorldCheck API.

        Args:
            db: Database session
            organization_id: Keycloak organization UUID
            name: Individual name to screen
            secondary_fields: Optional fields (date of birth, nationality, etc.)

        Returns:
            ScreeningResponse with case info and match results

        Raises:
            WorldCheckFeatureNotEnabledError: If WorldCheck not enabled
            WorldCheckCredentialsMissingError: If credentials incomplete
            WorldCheckError: On API errors
        """
        client = WorldCheckService._get_client(db, organization_id)
        group_id = await WorldCheckService._resolve_group_id(client)

        logger.info(
            "Starting WorldCheck individual screening",
            extra={
                "organization_id": organization_id,
                "group_id": group_id,
            },
        )

        try:
            response = await client.screen_entity(
                name=name,
                entity_type=EntityType.INDIVIDUAL,
                group_id=group_id,
                secondary_fields=secondary_fields,
            )

            logger.info(
                "WorldCheck individual screening completed",
                extra={
                    "organization_id": organization_id,
                    "case_system_id": response.caseSystemId,
                    "result_count": response.resultCount,
                },
            )

            return response

        except WorldCheckError:
            logger.error(
                "WorldCheck individual screening failed",
                exc_info=True,
                extra={"organization_id": organization_id},
            )
            raise
