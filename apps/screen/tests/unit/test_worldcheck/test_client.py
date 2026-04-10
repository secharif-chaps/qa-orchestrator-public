"""Tests for WorldCheck API client with mocked HTTP calls."""

import json
from unittest.mock import AsyncMock, patch

import httpx
import pytest

from app.infrastructure.worldcheck.client import WorldCheckClient
from app.infrastructure.worldcheck.exceptions import (
    WorldCheckAPIError,
    WorldCheckAuthError,
    WorldCheckNotFoundError,
    WorldCheckRateLimitError,
)
from app.infrastructure.worldcheck.schemas import EntityType


@pytest.fixture
def client():
    """Create a WorldCheckClient for testing."""
    return WorldCheckClient(
        api_key="test-key",
        api_secret="test-secret",
        timeout=5.0,
    )


def _mock_response(
    status_code: int, json_data: dict | list | None = None, text: str = "", headers: dict | None = None
) -> httpx.Response:
    """Create a mock httpx.Response."""
    if json_data is not None:
        content = json.dumps(json_data).encode("utf-8")
        response_headers = {"content-type": "application/json"}
    else:
        content = text.encode("utf-8")
        response_headers = {}
    if headers:
        response_headers.update(headers)
    response = httpx.Response(
        status_code=status_code,
        content=content,
        headers=response_headers,
        request=httpx.Request("POST", "https://api.risk.lseg.com/test"),
    )
    return response


class TestScreenEntity:
    """Tests for the screen_entity method."""

    @pytest.mark.asyncio
    async def test_successful_screening(self, client):
        """Test successful entity screening returns parsed response."""
        mock_data = {
            "caseSystemId": "case-123",
            "caseId": None,
            "name": "Acme Corp",
            "results": [
                {
                    "referenceId": "ref-001",
                    "matchStrength": "STRONG",
                    "matchedTerm": "Acme Corporation",
                    "submittedTerm": "Acme Corp",
                    "matchedNameType": "PRIMARY",
                    "categories": [{"name": "Sanctions"}, {"name": "PEP"}],
                    "sources": [{"name": "OFAC"}, {"name": "EU Sanctions"}],
                    "primaryName": "Acme Corporation Ltd",
                    "gender": None,
                    "events": [],
                },
                {
                    "referenceId": "ref-002",
                    "matchStrength": "WEAK",
                    "matchedTerm": "Acme Inc",
                    "categories": [{"name": "Adverse Media"}],
                    "sources": [],
                    "primaryName": "Acme Inc",
                },
            ],
        }

        with patch("app.infrastructure.worldcheck.client.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            mock_client.request = AsyncMock(return_value=_mock_response(200, mock_data))

            response = await client.screen_entity(
                name="Acme Corp",
                entity_type=EntityType.ORGANISATION,
                group_id="group-1",
            )

        assert response.caseSystemId == "case-123"
        assert response.resultCount == 2
        assert len(response.results) == 2

        first = response.results[0]
        assert first.referenceId == "ref-001"
        assert first.matchStrength == "STRONG"
        assert first.categories == ["Sanctions", "PEP"]
        assert first.sources == ["OFAC", "EU Sanctions"]
        assert first.primaryName == "Acme Corporation Ltd"

    @pytest.mark.asyncio
    async def test_screening_no_results(self, client):
        """Test screening with no matches."""
        mock_data = {
            "caseSystemId": "case-456",
            "name": "Clean Corp",
            "results": [],
        }

        with patch("app.infrastructure.worldcheck.client.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            mock_client.request = AsyncMock(return_value=_mock_response(200, mock_data))

            response = await client.screen_entity(
                name="Clean Corp",
                entity_type=EntityType.ORGANISATION,
                group_id="group-1",
            )

        assert response.caseSystemId == "case-456"
        assert response.resultCount == 0
        assert response.results == []


class TestErrorHandling:
    """Tests for error handling in the client."""

    @pytest.mark.asyncio
    async def test_auth_error_raises_exception(self, client):
        """Test HTTP 401 raises WorldCheckAuthError."""
        with patch("app.infrastructure.worldcheck.client.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            mock_client.request = AsyncMock(return_value=_mock_response(401, text="Unauthorized"))

            with pytest.raises(WorldCheckAuthError):
                await client.screen_entity("Test", EntityType.ORGANISATION, "group-1")

    @pytest.mark.asyncio
    async def test_not_found_raises_exception(self, client):
        """Test HTTP 404 raises WorldCheckNotFoundError."""
        with patch("app.infrastructure.worldcheck.client.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            mock_client.request = AsyncMock(return_value=_mock_response(404, text="Not Found"))

            with pytest.raises(WorldCheckNotFoundError):
                await client.get_case_results("nonexistent-case")

    @pytest.mark.asyncio
    async def test_rate_limit_retries_then_raises(self, client):
        """Test HTTP 429 triggers retries then raises WorldCheckRateLimitError."""
        with patch("app.infrastructure.worldcheck.client.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            # Return 429 on all attempts
            mock_client.request = AsyncMock(return_value=_mock_response(429, text="Rate Limited"))

            with (
                patch("app.infrastructure.worldcheck.client.asyncio.sleep", new_callable=AsyncMock),
                pytest.raises(WorldCheckRateLimitError),
            ):
                await client.screen_entity("Test", EntityType.ORGANISATION, "group-1")

            # Should have been called MAX_RETRIES times
            assert mock_client.request.call_count == 3

    @pytest.mark.asyncio
    async def test_rate_limit_succeeds_on_retry(self, client):
        """Test that a successful response after 429 works correctly."""
        mock_success = _mock_response(
            200,
            {
                "caseSystemId": "case-789",
                "results": [],
            },
        )
        mock_429 = _mock_response(429, text="Rate Limited")

        with patch("app.infrastructure.worldcheck.client.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            # First call: 429, second call: 200
            mock_client.request = AsyncMock(side_effect=[mock_429, mock_success])

            with patch("app.infrastructure.worldcheck.client.asyncio.sleep", new_callable=AsyncMock):
                response = await client.screen_entity("Test", EntityType.ORGANISATION, "group-1")

        assert response.caseSystemId == "case-789"
        assert mock_client.request.call_count == 2

    @pytest.mark.asyncio
    async def test_server_error_raises_api_error(self, client):
        """Test HTTP 500 raises WorldCheckAPIError without retry."""
        with patch("app.infrastructure.worldcheck.client.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            mock_client.request = AsyncMock(return_value=_mock_response(500, text="Internal Server Error"))

            with pytest.raises(WorldCheckAPIError) as exc_info:
                await client.screen_entity("Test", EntityType.ORGANISATION, "group-1")

            assert exc_info.value.status_code == 500
            # No retry on 500 - should be called only once
            assert mock_client.request.call_count == 1


class TestGetCaseResults:
    """Tests for get_case_results method."""

    @pytest.mark.asyncio
    async def test_get_results_success(self, client):
        """Test successful case results retrieval."""
        mock_data = {
            "results": [
                {
                    "resultId": "res-001",
                    "referenceId": "ref-001",
                    "matchStrength": "EXACT",
                    "matchedTerm": "Test Entity",
                    "submittedTerm": "Test",
                    "categories": ["Sanctions"],
                    "primaryName": "Test Entity Ltd",
                },
            ],
        }

        with patch("app.infrastructure.worldcheck.client.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            mock_client.request = AsyncMock(return_value=_mock_response(200, mock_data))

            results = await client.get_case_results("case-123")

        assert len(results) == 1
        assert results[0].resultId == "res-001"
        assert results[0].matchStrength == "EXACT"


class TestGetReferenceProfile:
    """Tests for get_reference_profile method."""

    @pytest.mark.asyncio
    async def test_get_profile_success(self, client):
        """Test successful reference profile retrieval."""
        mock_data = {
            "referenceId": "ref-001",
            "name": "Test Entity",
            "entityType": "ORGANISATION",
            "categories": ["Sanctions"],
        }

        with patch("app.infrastructure.worldcheck.client.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            mock_client.request = AsyncMock(return_value=_mock_response(200, mock_data))

            profile = await client.get_reference_profile("ref-001")

        assert profile["referenceId"] == "ref-001"
        assert profile["entityType"] == "ORGANISATION"


class TestPathParamValidation:
    """Tests for path parameter validation against traversal attacks."""

    @pytest.mark.asyncio
    async def test_case_id_path_traversal_rejected(self, client):
        """Test that path traversal in case_system_id is rejected."""
        with pytest.raises(ValueError, match="Invalid case_system_id"):
            await client.get_case_results("../../admin")

    @pytest.mark.asyncio
    async def test_reference_id_path_traversal_rejected(self, client):
        """Test that path traversal in reference_id is rejected."""
        with pytest.raises(ValueError, match="Invalid reference_id"):
            await client.get_reference_profile("../../../etc/passwd")

    @pytest.mark.asyncio
    async def test_empty_case_id_rejected(self, client):
        """Test that empty case_system_id is rejected."""
        with pytest.raises(ValueError, match="Invalid case_system_id"):
            await client.get_case_results("")

    @pytest.mark.asyncio
    async def test_valid_case_id_accepted(self, client):
        """Test that valid alphanumeric IDs with hyphens pass validation."""
        # Should not raise - validation passes, request will be made
        with patch("app.infrastructure.worldcheck.client.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            mock_client.request = AsyncMock(return_value=_mock_response(200, {"results": []}))

            results = await client.get_case_results("case-abc-123_def")
            assert results == []
