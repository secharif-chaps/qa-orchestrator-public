"""Tests for WorldCheck service layer."""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.infrastructure.worldcheck.exceptions import WorldCheckAuthError, WorldCheckError
from app.infrastructure.worldcheck.schemas import EntityType, ScreeningResponse, ScreeningResult
from app.services.worldcheck import (
    WorldCheckCredentialsMissingError,
    WorldCheckFeatureNotEnabledError,
    WorldCheckService,
)


@pytest.fixture
def mock_db():
    """Create a mock database session."""
    return MagicMock()


class TestGetClient:
    """Tests for _get_client credential retrieval."""

    def test_raises_when_feature_not_enabled(self, mock_db):
        """Test error when WorldCheck feature flag is disabled."""
        with (
            patch("app.services.worldcheck.has_feature", return_value=False),
            pytest.raises(WorldCheckFeatureNotEnabledError),
        ):
            WorldCheckService._get_client(mock_db, "org-123")

    def test_raises_when_no_config(self, mock_db):
        """Test error when feature config is None."""
        with (
            patch("app.services.worldcheck.has_feature", return_value=True),
            patch("app.services.worldcheck.get_feature_config", return_value=None),
            pytest.raises(WorldCheckCredentialsMissingError),
        ):
            WorldCheckService._get_client(mock_db, "org-123")

    def test_raises_when_api_key_missing(self, mock_db):
        """Test error when api_key is missing from config."""
        with (
            patch("app.services.worldcheck.has_feature", return_value=True),
            patch("app.services.worldcheck.get_feature_config", return_value={"api_secret": "secret"}),
            pytest.raises(WorldCheckCredentialsMissingError),
        ):
            WorldCheckService._get_client(mock_db, "org-123")

    def test_raises_when_api_secret_missing(self, mock_db):
        """Test error when api_secret is missing from config."""
        with (
            patch("app.services.worldcheck.has_feature", return_value=True),
            patch("app.services.worldcheck.get_feature_config", return_value={"api_key": "key"}),
            pytest.raises(WorldCheckCredentialsMissingError),
        ):
            WorldCheckService._get_client(mock_db, "org-123")

    def test_returns_client_with_valid_config(self, mock_db):
        """Test successful client creation with valid credentials."""
        with (
            patch("app.services.worldcheck.has_feature", return_value=True),
            patch(
                "app.services.worldcheck.get_feature_config",
                return_value={
                    "api_key": "test-key",
                    "api_secret": "test-secret",
                },
            ),
        ):
            client = WorldCheckService._get_client(mock_db, "org-123")
            assert client._api_key == "test-key"
            assert client._api_secret == "test-secret"


class TestScreenCompany:
    """Tests for screen_company method."""

    @pytest.mark.asyncio
    async def test_successful_screening(self, mock_db):
        """Test successful company screening with auto-detected group_id."""
        mock_response = ScreeningResponse(
            caseSystemId="case-123",
            results=[
                ScreeningResult(
                    referenceId="ref-001",
                    matchStrength="STRONG",
                    categories=["Sanctions"],
                ),
            ],
            resultCount=1,
        )

        mock_client = AsyncMock()
        mock_client.screen_entity = AsyncMock(return_value=mock_response)
        mock_client.get_groups = AsyncMock(return_value=[{"id": "group-auto", "name": "Test Group"}])

        with patch.object(WorldCheckService, "_get_client", return_value=mock_client):
            result = await WorldCheckService.screen_company(
                db=mock_db,
                organization_id="org-123",
                company_name="Acme Corp",
            )

        assert result.caseSystemId == "case-123"
        assert result.resultCount == 1
        mock_client.get_groups.assert_called_once()
        mock_client.screen_entity.assert_called_once_with(
            name="Acme Corp",
            entity_type=EntityType.ORGANISATION,
            group_id="group-auto",
        )

    @pytest.mark.asyncio
    async def test_feature_not_enabled_raises(self, mock_db):
        """Test that disabled feature flag raises error."""
        with (
            patch("app.services.worldcheck.has_feature", return_value=False),
            pytest.raises(WorldCheckFeatureNotEnabledError),
        ):
            await WorldCheckService.screen_company(
                db=mock_db,
                organization_id="org-123",
                company_name="Acme Corp",
            )

    @pytest.mark.asyncio
    async def test_api_error_propagates(self, mock_db):
        """Test that WorldCheck API errors propagate correctly."""
        mock_client = AsyncMock()
        mock_client.screen_entity = AsyncMock(side_effect=WorldCheckAuthError())
        mock_client.get_groups = AsyncMock(return_value=[{"id": "group-1", "name": "Test"}])

        with (
            patch.object(WorldCheckService, "_get_client", return_value=mock_client),
            pytest.raises(WorldCheckAuthError),
        ):
            await WorldCheckService.screen_company(
                db=mock_db,
                organization_id="org-123",
                company_name="Acme Corp",
            )

    @pytest.mark.asyncio
    async def test_no_groups_raises(self, mock_db):
        """Test error when no screening groups are available."""
        mock_client = AsyncMock()
        mock_client.get_groups = AsyncMock(return_value=[])

        with (
            patch.object(WorldCheckService, "_get_client", return_value=mock_client),
            pytest.raises(WorldCheckError, match="No WorldCheck screening groups"),
        ):
            await WorldCheckService.screen_company(
                db=mock_db,
                organization_id="org-123",
                company_name="Acme Corp",
            )


class TestScreenIndividual:
    """Tests for screen_individual method."""

    @pytest.mark.asyncio
    async def test_successful_individual_screening(self, mock_db):
        """Test successful individual screening with secondary fields."""
        mock_response = ScreeningResponse(
            caseSystemId="case-456",
            results=[],
            resultCount=0,
        )

        mock_client = AsyncMock()
        mock_client.screen_entity = AsyncMock(return_value=mock_response)
        mock_client.get_groups = AsyncMock(return_value=[{"id": "group-auto", "name": "Test"}])

        with patch.object(WorldCheckService, "_get_client", return_value=mock_client):
            secondary = [{"typeId": "SFCT_1", "dateTimeValue": "1980-01-01"}]
            result = await WorldCheckService.screen_individual(
                db=mock_db,
                organization_id="org-123",
                name="John Doe",
                secondary_fields=secondary,
            )

        assert result.caseSystemId == "case-456"
        mock_client.screen_entity.assert_called_once_with(
            name="John Doe",
            entity_type=EntityType.INDIVIDUAL,
            group_id="group-auto",
            secondary_fields=secondary,
        )
