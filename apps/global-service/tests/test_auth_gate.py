"""Tests for the x-public auth gate (TAR-1382).

Covers:
- Route with x-public: true skips JWT authentication
- Route without x-public requires JWT (default behavior)
- Fallback public routes (health, webhooks) still work
- Gateway-local routes (not in registry) are unaffected
"""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.proxy.registry import ModuleDefinition, ModuleName, RouteOperation


class TestXPublicAuthGate:
    """Test that x-public: true in OpenAPI schema skips JWT auth."""

    @pytest.mark.asyncio
    async def test_public_route_skips_auth(self, client):
        """Route with x-public: true should be accessible without JWT."""
        mock_registry = MagicMock()
        screen_module = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
        )
        mock_registry.resolve.return_value = (screen_module, "/api/health")
        mock_registry.resolve_operation.return_value = RouteOperation(
            method="GET",
            path="/api/health",
            is_public=True,
        )

        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.headers = {"content-type": "application/json"}

        async def mock_aiter():
            yield b'{"status":"ok"}'

        mock_response.aiter_bytes = mock_aiter
        mock_response.aclose = AsyncMock()

        with (
            patch("app.proxy.routes._registry", mock_registry),
            patch("app.proxy.routes.get_proxy_client") as mock_client_fn,
        ):
            mock_client = AsyncMock()
            mock_client_fn.return_value = mock_client
            mock_client.build_request.return_value = MagicMock()
            mock_client.send = AsyncMock(return_value=mock_response)

            response = await client.get("/api/health")

        # Should succeed without JWT (no Authorization header sent)
        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_non_public_route_requires_auth(self, client):
        """Route without x-public should return 401 without JWT."""
        mock_registry = MagicMock()
        screen_module = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
        )
        mock_registry.resolve.return_value = (screen_module, "/api/companies")
        mock_registry.resolve_operation.return_value = RouteOperation(
            method="GET",
            path="/api/companies",
            is_public=False,
        )

        with patch("app.proxy.routes._registry", mock_registry):
            response = await client.get("/api/companies")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_registry_none_protected_route_requires_auth(self, client):
        """When registry is None, non-public routes still require auth."""
        with patch("app.proxy.routes._registry", None):
            response = await client.get("/api/companies")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_public_route_does_not_send_internal_jwt(self, client):
        """Public routes should not send internal JWT headers to backend."""
        mock_registry = MagicMock()
        screen_module = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
        )
        mock_registry.resolve.return_value = (screen_module, "/api/health/ready")
        mock_registry.resolve_operation.return_value = RouteOperation(
            method="GET",
            path="/api/health/ready",
            is_public=True,
        )

        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.headers = {"content-type": "application/json"}

        async def mock_aiter():
            yield b'{"status":"ready"}'

        mock_response.aiter_bytes = mock_aiter
        mock_response.aclose = AsyncMock()

        with (
            patch("app.proxy.routes._registry", mock_registry),
            patch("app.proxy.routes.get_proxy_client") as mock_client_fn,
        ):
            mock_client = AsyncMock()
            mock_client_fn.return_value = mock_client
            mock_client.build_request.return_value = MagicMock()
            mock_client.send = AsyncMock(return_value=mock_response)

            await client.get("/api/health/ready")

        # Inspect headers passed to build_request via call_args
        call_kwargs = mock_client.build_request.call_args.kwargs
        auth_header = call_kwargs.get("headers", {}).get("Authorization", "")
        assert auth_header == "", "Public route should not receive any Authorization header"


    @pytest.mark.asyncio
    async def test_resolve_operation_returns_none_requires_auth(self, client):
        """When resolve_operation returns None, auth should be required."""
        mock_registry = MagicMock()
        screen_module = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
        )
        mock_registry.resolve.return_value = (screen_module, "/api/unknown-route")
        mock_registry.resolve_operation.return_value = None  # Route not in OpenAPI schema

        with patch("app.proxy.routes._registry", mock_registry):
            response = await client.get("/api/unknown-route")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_method_mismatch_on_public_route_requires_auth(self, client):
        """POST to a GET-only public route should require auth."""
        mock_registry = MagicMock()
        screen_module = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
        )
        # Use a non-health path to avoid the fallback whitelist
        mock_registry.resolve.return_value = (screen_module, "/api/status")

        def resolve_by_method(module_name, method, path):
            if method == "GET":
                return RouteOperation(method="GET", path=path, is_public=True)
            return None

        mock_registry.resolve_operation.side_effect = resolve_by_method

        with patch("app.proxy.routes._registry", mock_registry):
            response = await client.post("/api/status")

        assert response.status_code == 401

    def test_build_internal_headers_returns_empty_for_none_user(self):
        """Both x-public and fallback paths produce empty internal headers for anonymous users."""
        from app.core.auth_middleware import build_internal_headers

        result = build_internal_headers(None)
        assert result == {}, "build_internal_headers(None) should return empty dict"


class TestFallbackPublicRoutes:
    """Test that legacy fallback public routes still work during transition."""

    def test_is_public_route_health(self):
        """Health endpoints remain public via fallback."""
        from app.core.auth_middleware import is_public_route

        assert is_public_route("health") is True
        assert is_public_route("health/live") is True
        assert is_public_route("health/ready") is True

    def test_is_public_route_webhooks(self):
        """Webhook routes remain public via fallback."""
        from app.core.auth_middleware import is_public_route

        assert is_public_route("webhooks/stripe/callback") is True
        assert is_public_route("webhooks/test") is True

    def test_is_public_route_regular_path(self):
        """Regular paths are not public via fallback."""
        from app.core.auth_middleware import is_public_route

        assert is_public_route("companies") is False
        assert is_public_route("companies/123") is False
        assert is_public_route("folders") is False

    def test_is_public_route_empty_path(self):
        """Empty path is not public."""
        from app.core.auth_middleware import is_public_route

        assert is_public_route("") is False

    def test_is_public_route_leading_slash(self):
        """Leading slash is stripped correctly."""
        from app.core.auth_middleware import is_public_route

        assert is_public_route("/health") is True
        assert is_public_route("/webhooks/test") is True
