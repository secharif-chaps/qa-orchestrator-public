"""Tests for the stream _announce_to_gateway() function.

Covers:
- Skip when GLOBAL_SERVICE_URL not set
- Graceful error handling on network failure
- Correct Authorization header (Internal prefix)
- Correct URL path (/internal/registry/announce/stream)
- Correct openapi_hash payload
- HTTP error handling (4xx/5xx)
"""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest


class TestAnnounceToGateway:
    """Test the stream startup announce to global-service."""

    @pytest.mark.asyncio
    async def test_skips_when_url_not_set(self):
        """Should skip announce when GLOBAL_SERVICE_URL is empty."""
        from app.main import _announce_to_gateway

        mock_app = MagicMock()
        with patch("app.main.settings") as mock_settings:
            mock_settings.GLOBAL_SERVICE_URL = ""
            await _announce_to_gateway(mock_app)

        mock_app.openapi.assert_not_called()

    @pytest.mark.asyncio
    async def test_handles_network_error_gracefully(self):
        """Should log warning and not raise on network error."""
        from app.main import _announce_to_gateway

        mock_app = MagicMock()
        mock_app.openapi.return_value = {"openapi": "3.0.0", "paths": {}}

        with (
            patch("app.main.settings") as mock_settings,
            patch("app.main.httpx.AsyncClient") as mock_client_cls,
            patch("app.main.create_internal_token", return_value="test-token"),
        ):
            mock_settings.GLOBAL_SERVICE_URL = "http://gateway:8000/api"

            mock_client = AsyncMock()
            mock_client.post = AsyncMock(side_effect=Exception("Connection refused"))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await _announce_to_gateway(mock_app)

        mock_app.openapi.assert_called_once()

    @pytest.mark.asyncio
    async def test_sends_internal_auth_header(self):
        """Should send 'Internal' prefix for service-to-service auth."""
        from app.main import _announce_to_gateway

        mock_app = MagicMock()
        mock_app.openapi.return_value = {"openapi": "3.0.0", "paths": {}}

        with (
            patch("app.main.settings") as mock_settings,
            patch("app.main.httpx.AsyncClient") as mock_client_cls,
            patch("app.main.create_internal_token", return_value="test-jwt-token"),
        ):
            mock_settings.GLOBAL_SERVICE_URL = "http://gateway:8000/api"

            mock_response = MagicMock()
            mock_response.raise_for_status = MagicMock()
            mock_response.json.return_value = {"action": "unchanged"}

            mock_client = AsyncMock()
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await _announce_to_gateway(mock_app)

            call_args = mock_client.post.call_args
            auth_header = call_args.kwargs.get("headers", {}).get("Authorization", "")
            assert auth_header.startswith("Internal "), f"Expected 'Internal' prefix, got: {auth_header}"

    @pytest.mark.asyncio
    async def test_sends_correct_url_path(self):
        """Should POST to {GLOBAL_SERVICE_URL}/internal/registry/announce/stream."""
        from app.main import _announce_to_gateway

        mock_app = MagicMock()
        mock_app.openapi.return_value = {"openapi": "3.0.0", "paths": {}}

        with (
            patch("app.main.settings") as mock_settings,
            patch("app.main.httpx.AsyncClient") as mock_client_cls,
            patch("app.main.create_internal_token", return_value="test-token"),
        ):
            mock_settings.GLOBAL_SERVICE_URL = "http://gateway:8000/api"

            mock_response = MagicMock()
            mock_response.raise_for_status = MagicMock()
            mock_response.json.return_value = {"action": "rediscovered"}

            mock_client = AsyncMock()
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await _announce_to_gateway(mock_app)

            call_args = mock_client.post.call_args
            url = call_args.args[0] if call_args.args else call_args.kwargs.get("url", "")
            assert url == "http://gateway:8000/api/internal/registry/announce/stream", f"Wrong URL: {url}"

    @pytest.mark.asyncio
    async def test_sends_correct_openapi_hash(self):
        """Payload should contain the SHA-256 hash of the OpenAPI schema."""
        import hashlib
        import json

        from app.main import _announce_to_gateway

        test_schema = {"openapi": "3.0.0", "paths": {"/test": {"get": {}}}}
        expected_hash = hashlib.sha256(json.dumps(test_schema, sort_keys=True).encode()).hexdigest()

        mock_app = MagicMock()
        mock_app.openapi.return_value = test_schema

        with (
            patch("app.main.settings") as mock_settings,
            patch("app.main.httpx.AsyncClient") as mock_client_cls,
            patch("app.main.create_internal_token", return_value="test-token"),
        ):
            mock_settings.GLOBAL_SERVICE_URL = "http://gateway:8000/api"

            mock_response = MagicMock()
            mock_response.raise_for_status = MagicMock()
            mock_response.json.return_value = {"action": "unchanged"}

            mock_client = AsyncMock()
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await _announce_to_gateway(mock_app)

            call_args = mock_client.post.call_args
            payload = call_args.kwargs.get("json", {})
            assert payload["openapi_hash"] == expected_hash

    @pytest.mark.asyncio
    async def test_handles_http_error_gracefully(self):
        """Should handle 4xx/5xx from gateway without raising."""
        import httpx

        from app.main import _announce_to_gateway

        mock_app = MagicMock()
        mock_app.openapi.return_value = {"openapi": "3.0.0", "paths": {}}

        with (
            patch("app.main.settings") as mock_settings,
            patch("app.main.httpx.AsyncClient") as mock_client_cls,
            patch("app.main.create_internal_token", return_value="test-token"),
        ):
            mock_settings.GLOBAL_SERVICE_URL = "http://gateway:8000/api"

            mock_response = MagicMock()
            mock_response.raise_for_status.side_effect = httpx.HTTPStatusError(
                "503 Service Unavailable",
                request=MagicMock(),
                response=MagicMock(status_code=503),
            )

            mock_client = AsyncMock()
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await _announce_to_gateway(mock_app)

        mock_app.openapi.assert_called_once()

    @pytest.mark.asyncio
    async def test_strips_trailing_slash_from_gateway_url(self):
        """Should strip trailing slash from GLOBAL_SERVICE_URL to avoid double slashes."""
        from app.main import _announce_to_gateway

        mock_app = MagicMock()
        mock_app.openapi.return_value = {"openapi": "3.0.0", "paths": {}}

        with (
            patch("app.main.settings") as mock_settings,
            patch("app.main.httpx.AsyncClient") as mock_client_cls,
            patch("app.main.create_internal_token", return_value="test-token"),
        ):
            mock_settings.GLOBAL_SERVICE_URL = "http://gateway:8000/api/"

            mock_response = MagicMock()
            mock_response.raise_for_status = MagicMock()
            mock_response.json.return_value = {"action": "unchanged"}

            mock_client = AsyncMock()
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await _announce_to_gateway(mock_app)

            call_args = mock_client.post.call_args
            url = call_args.args[0] if call_args.args else call_args.kwargs.get("url", "")
            assert "//" not in url.replace("http://", ""), f"URL has double slash: {url}"
