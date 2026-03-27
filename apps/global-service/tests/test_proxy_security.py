"""
Tests for proxy security hardening (TAR-1249).

Tests cover:
1. Proxy rejects forwarding authenticated requests without internal headers
2. INTERNAL_JWT_SECRET validation at startup
"""

import os
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from fastapi import Request


class TestProxyAuthGuard:
    """Test that proxy blocks authenticated requests missing internal headers."""

    @pytest.mark.asyncio
    async def test_rejects_authenticated_request_without_internal_headers(self):
        """Authenticated user + empty internal_headers → 502 Bad Gateway."""
        from app.proxy.routes import proxy_request

        mock_request = MagicMock(spec=Request)
        mock_request.method = "GET"
        mock_request.url = MagicMock()
        mock_request.url.query = ""
        mock_request.headers = {"authorization": "Bearer valid-token"}
        mock_request.state = MagicMock()

        mock_user = MagicMock()
        mock_user.preferred_username = "testuser"

        # Simulate: valid JWT but build_internal_headers() returned empty dict
        with patch("app.proxy.routes.auth_middleware") as mock_auth:
            mock_auth.validate_request = AsyncMock(
                return_value=(True, mock_user, {})  # is_valid=True, user exists, but no internal headers
            )

            response = await proxy_request(mock_request, "companies/")

            assert response.status_code == 502
            assert b"cannot forward authenticated request" in response.body

    @pytest.mark.asyncio
    async def test_allows_public_route_without_internal_headers(self):
        """Public route (no user) + empty internal_headers → does NOT return 502."""
        from app.proxy.routes import proxy_request

        mock_request = MagicMock(spec=Request)
        mock_request.method = "GET"
        mock_request.url = MagicMock()
        mock_request.url.query = ""
        mock_request.headers = {}
        mock_request.state = MagicMock()
        mock_request.state.correlation_id = None

        with (
            patch("app.proxy.routes.auth_middleware") as mock_auth,
            patch("app.proxy.routes.get_proxy_client") as mock_client,
            patch("app.proxy.routes._check_company_folder_access", return_value=None),
            patch("app.proxy.routes.filter_request_headers", return_value={}),
        ):
            mock_auth.validate_request = AsyncMock(
                return_value=(True, None, {})  # Public route: no user
            )

            mock_client_instance = AsyncMock()
            mock_client.return_value = mock_client_instance

            # The proxy call will fail due to mocking, but that's fine.
            # We only need to verify the guard does NOT block with our
            # specific 502 message for public routes (user=None).
            response = await proxy_request(mock_request, "health/ready")

            # The response may be a proxy error 502, but it must NOT be
            # the guard's "cannot forward authenticated request" message
            if response.status_code == 502:
                assert b"cannot forward authenticated request" not in response.body


class TestInternalJwtSecretValidation:
    """Test that INTERNAL_JWT_SECRET is validated at startup."""

    def test_rejects_empty_secret(self):
        """Empty INTERNAL_JWT_SECRET in production context → ValueError."""
        from app.core.config import Settings

        # Remove all test/CI bypass env vars to simulate production
        env_overrides = {"SKIP_KEYCLOAK_INIT": "", "CI": ""}
        env_removals = {k: v for k, v in os.environ.items() if k == "PYTEST_CURRENT_TEST"}
        with patch.dict(os.environ, env_overrides, clear=False):
            # Temporarily remove PYTEST_CURRENT_TEST
            for key in env_removals:
                del os.environ[key]
            try:
                with pytest.raises(ValueError, match="INTERNAL_JWT_SECRET must be set"):
                    Settings(INTERNAL_JWT_SECRET="")
            finally:
                os.environ.update(env_removals)

    def test_accepts_empty_secret_in_test_mode(self):
        """Empty INTERNAL_JWT_SECRET with SKIP_KEYCLOAK_INIT=true → allowed."""
        from app.core.config import Settings

        with patch.dict(os.environ, {"SKIP_KEYCLOAK_INIT": "true"}, clear=False):
            s = Settings(INTERNAL_JWT_SECRET="")
            assert s.INTERNAL_JWT_SECRET == ""

    def test_accepts_configured_secret(self):
        """Non-empty INTERNAL_JWT_SECRET → allowed."""
        from app.core.config import Settings

        with patch.dict(os.environ, {"SKIP_KEYCLOAK_INIT": ""}, clear=False):
            s = Settings(INTERNAL_JWT_SECRET="my-super-secret-key-at-least-32-chars")
            assert s.INTERNAL_JWT_SECRET == "my-super-secret-key-at-least-32-chars"
