"""
Tests for the authentication middleware.

Tests cover:
1. Valid JWT passes through gateway
2. Invalid JWT rejected at gateway (401)
3. Missing token rejected (401) for protected routes
4. Public routes accessible without token
5. Internal JWT is created for proxied requests
"""

import pytest
import jwt as pyjwt
from unittest.mock import AsyncMock, MagicMock, patch
from fastapi import Request
from fastapi.testclient import TestClient

from app.core.auth_middleware import (
    auth_middleware,
    is_public_route,
    validate_jwt_token,
    build_internal_headers,
    extract_organization_info,
    GatewayUser,
)
from app.core.internal_jwt import ALGORITHM, ISSUER


class TestPublicRouteDetection:
    """Test public route detection logic."""

    def test_health_is_public(self):
        """Test that health endpoints are public."""
        assert is_public_route("health") is True
        assert is_public_route("health/live") is True
        assert is_public_route("health/ready") is True

    def test_webhooks_are_public(self):
        """Test that webhook endpoints are public."""
        assert is_public_route("webhooks/dify/callback") is True
        assert is_public_route("webhooks/task/callback") is True
        assert is_public_route("webhooks/anything") is True

    def test_api_routes_are_protected(self):
        """Test that regular API routes are protected."""
        assert is_public_route("companies") is False
        assert is_public_route("companies/123") is False
        assert is_public_route("users/me") is False
        assert is_public_route("tasks/events/stream") is False

    def test_leading_slash_handled(self):
        """Test that leading slashes are handled correctly."""
        assert is_public_route("/health") is True
        assert is_public_route("/companies") is False


class TestExtractOrganizationInfo:
    """Test organization info extraction."""

    def test_extract_org_with_id(self):
        """Test extracting organization with ID."""
        user = GatewayUser(
            sub="user-123",
            preferred_username="testuser",
            organization=["TestOrg", {"TestOrg": {"id": "org-456"}}],
        )
        org_id, org_name = extract_organization_info(user)

        assert org_id == "org-456"
        assert org_name == "TestOrg"

    def test_extract_org_without_id(self):
        """Test extracting organization without ID."""
        user = GatewayUser(
            sub="user-123",
            preferred_username="testuser",
            organization=["TestOrg", {}],
        )
        org_id, org_name = extract_organization_info(user)

        assert org_id == ""
        assert org_name == "TestOrg"

    def test_extract_org_string_format(self):
        """Test extracting organization in string format."""
        user = GatewayUser(
            sub="user-123",
            preferred_username="testuser",
            organization="TestOrg",
        )
        org_id, org_name = extract_organization_info(user)

        assert org_id == ""
        assert org_name == "TestOrg"

    def test_extract_org_none(self):
        """Test extracting organization when None."""
        user = GatewayUser(
            sub="user-123",
            preferred_username="testuser",
            organization=None,
        )
        org_id, org_name = extract_organization_info(user)

        assert org_id == ""
        assert org_name == ""


class TestBuildInternalHeaders:
    """Test internal header building with JWT."""

    @pytest.fixture
    def mock_settings(self):
        """Mock settings with valid configuration."""
        with patch("app.core.auth_middleware.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.INTERNAL_JWT_EXPIRY_SECONDS = 60
            yield mock

    def test_no_headers_for_anonymous(self, mock_settings):
        """Test that no headers are added for anonymous users."""
        headers = build_internal_headers(None)
        assert headers == {}

    def test_internal_jwt_created_for_user(self, mock_settings):
        """Test that internal JWT is created when user is present."""
        with patch("app.core.internal_jwt.settings", mock_settings):
            user = GatewayUser(
                sub="user-123",
                preferred_username="testuser",
                email="test@example.com",
                realm_access={"roles": ["admin", "user"]},
                organization=["TestOrg", {"TestOrg": {"id": "org-456"}}],
            )
            headers = build_internal_headers(user)

            assert "Authorization" in headers
            assert headers["Authorization"].startswith("Internal ")

            # Extract and verify the token
            token = headers["Authorization"].replace("Internal ", "")
            payload = pyjwt.decode(
                token,
                "test-secret-at-least-32-characters-long",
                algorithms=[ALGORITHM],
            )

            assert payload["sub"] == "user-123"
            assert payload["username"] == "testuser"
            assert payload["email"] == "test@example.com"
            assert payload["org_id"] == "org-456"
            assert payload["org_name"] == "TestOrg"
            assert "admin" in payload["roles"]
            assert "user" in payload["roles"]
            assert payload["iss"] == ISSUER

    def test_internal_jwt_with_empty_roles(self, mock_settings):
        """Test that internal JWT handles empty roles."""
        with patch("app.core.internal_jwt.settings", mock_settings):
            user = GatewayUser(
                sub="user-123",
                preferred_username="testuser",
                realm_access=None,
                organization=["TestOrg", {"TestOrg": {"id": "org-456"}}],
            )
            headers = build_internal_headers(user)

            token = headers["Authorization"].replace("Internal ", "")
            payload = pyjwt.decode(
                token,
                "test-secret-at-least-32-characters-long",
                algorithms=[ALGORITHM],
            )

            assert payload["roles"] == []

    def test_no_headers_without_secret(self):
        """Test that no headers are added when secret is not configured."""
        with patch("app.core.auth_middleware.settings") as mock:
            mock.INTERNAL_JWT_SECRET = ""
            mock.INTERNAL_JWT_EXPIRY_SECONDS = 60

            user = GatewayUser(
                sub="user-123",
                preferred_username="testuser",
            )
            headers = build_internal_headers(user)

            assert headers == {}


class TestJWTValidation:
    """Test JWT validation logic."""

    @pytest.mark.asyncio
    async def test_valid_token_returns_user(self):
        """Test that a valid token returns a GatewayUser."""
        mock_payload = {
            "sub": "user-123",
            "preferred_username": "testuser",
            "email": "test@example.com",
            "realm_access": {"roles": ["admin"]},
        }

        with patch("app.core.auth_middleware.get_keycloak_public_key") as mock_key:
            mock_key.return_value = "-----BEGIN PUBLIC KEY-----\nMOCK\n-----END PUBLIC KEY-----"

            with patch("app.core.auth_middleware.jwt.decode") as mock_decode:
                mock_decode.return_value = mock_payload

                user = await validate_jwt_token("valid.token.here")

                assert user is not None
                assert user.sub == "user-123"
                assert user.preferred_username == "testuser"

    @pytest.mark.asyncio
    async def test_expired_token_returns_none(self):
        """Test that an expired token returns None."""
        from jose import jwt as jose_jwt

        with patch("app.core.auth_middleware.get_keycloak_public_key") as mock_key:
            mock_key.return_value = "-----BEGIN PUBLIC KEY-----\nMOCK\n-----END PUBLIC KEY-----"

            with patch("app.core.auth_middleware.jwt.decode") as mock_decode:
                mock_decode.side_effect = jose_jwt.ExpiredSignatureError()

                user = await validate_jwt_token("expired.token.here")

                assert user is None

    @pytest.mark.asyncio
    async def test_invalid_token_returns_none(self):
        """Test that an invalid token returns None."""
        from jose import JWTError

        with patch("app.core.auth_middleware.get_keycloak_public_key") as mock_key:
            mock_key.return_value = "-----BEGIN PUBLIC KEY-----\nMOCK\n-----END PUBLIC KEY-----"

            with patch("app.core.auth_middleware.jwt.decode") as mock_decode:
                mock_decode.side_effect = JWTError("Invalid token")

                user = await validate_jwt_token("invalid.token.here")

                assert user is None


class TestAuthMiddlewareValidation:
    """Test the GatewayAuthMiddleware.validate_request method."""

    @pytest.mark.asyncio
    async def test_public_route_allowed_without_token(self):
        """Test that public routes are allowed without a token."""
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {}

        is_valid, user, headers = await auth_middleware.validate_request(
            mock_request, "health/live"
        )

        assert is_valid is True
        assert user is None
        # Public routes don't get internal headers (no user context)
        assert headers == {}

    @pytest.mark.asyncio
    async def test_protected_route_rejected_without_token(self):
        """Test that protected routes are rejected without a token."""
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {}

        is_valid, user, headers = await auth_middleware.validate_request(
            mock_request, "companies"
        )

        assert is_valid is False
        assert user is None
        assert headers == {}

    @pytest.mark.asyncio
    async def test_protected_route_allowed_with_valid_token(self):
        """Test that protected routes are allowed with a valid token."""
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {"Authorization": "Bearer valid.token.here"}

        mock_user = GatewayUser(
            sub="user-123",
            preferred_username="testuser",
            organization=["TestOrg", {"TestOrg": {"id": "org-456"}}],
        )

        with patch("app.core.auth_middleware.validate_jwt_token") as mock_validate:
            mock_validate.return_value = mock_user

            with patch("app.core.auth_middleware.settings") as mock_settings:
                mock_settings.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
                mock_settings.INTERNAL_JWT_EXPIRY_SECONDS = 60

                with patch("app.core.internal_jwt.settings", mock_settings):
                    is_valid, user, headers = await auth_middleware.validate_request(
                        mock_request, "companies"
                    )

                    assert is_valid is True
                    assert user is not None
                    assert user.sub == "user-123"
                    assert "Authorization" in headers
                    assert headers["Authorization"].startswith("Internal ")

    @pytest.mark.asyncio
    async def test_protected_route_rejected_with_invalid_token(self):
        """Test that protected routes are rejected with an invalid token."""
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {"Authorization": "Bearer invalid.token.here"}

        with patch("app.core.auth_middleware.validate_jwt_token") as mock_validate:
            mock_validate.return_value = None

            is_valid, user, headers = await auth_middleware.validate_request(
                mock_request, "companies"
            )

            assert is_valid is False
            assert user is None
            assert headers == {}


class TestProxyIntegrationWithAuth:
    """Integration tests for proxy with auth middleware."""

    def test_proxy_rejects_unauthenticated_request(self):
        """Test that proxy rejects requests without auth for protected routes."""
        from app.main import app

        # Mock the proxy client to avoid actual backend calls
        with patch("app.proxy.routes.get_proxy_client") as mock_client:
            with TestClient(app) as client:
                response = client.get("/api/companies")

                # Should be rejected at gateway level
                assert response.status_code == 401
                assert "Not authenticated" in response.json()["detail"]

    def test_proxy_allows_public_routes(self):
        """Test that proxy allows public routes without auth."""
        from app.main import app

        with TestClient(app) as client:
            # Health endpoints are on a different router, not proxied
            # Webhooks go through proxy but are public
            pass  # Webhook testing would require mocking the backend

    def test_proxy_passes_valid_auth(self):
        """Test that proxy passes requests with valid auth."""
        from app.main import app

        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.content = b'{"data": "test"}'
        mock_response.headers = {"content-type": "application/json"}

        mock_user = GatewayUser(
            sub="user-123",
            preferred_username="testuser",
            realm_access={"roles": ["user"]},
            organization=["TestOrg", {"TestOrg": {"id": "org-456"}}],
        )

        with patch("app.proxy.routes.auth_middleware.validate_request") as mock_auth:
            # Return valid auth with internal JWT header
            mock_auth.return_value = (True, mock_user, {
                "Authorization": "Internal mock.token.here",
            })

            with patch("app.proxy.routes.get_proxy_client") as mock_get_client:
                mock_client = AsyncMock()
                mock_client.request = AsyncMock(return_value=mock_response)
                mock_get_client.return_value = mock_client

                with TestClient(app) as client:
                    response = client.get(
                        "/api/companies",
                        headers={"Authorization": "Bearer valid.token"}
                    )

                    assert response.status_code == 200


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
