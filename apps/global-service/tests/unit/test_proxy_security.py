"""
Tests for proxy security hardening.

Tests cover:
1. Proxy rejects forwarding authenticated requests without internal headers (TAR-1249)
2. INTERNAL_JWT_SECRET validation at startup (TAR-1249)
3. Permission gate rejects users without required x-permissions (TAR-1384)
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


class TestPermissionGate:
    """Test that proxy rejects users without required x-permissions (TAR-1384)."""

    def test_rejects_user_without_required_permission(self):
        """User with wrong roles + endpoint requiring company.view → 403."""
        from app.proxy.registry import ModuleDefinition, ModuleName, ModuleRegistry, RouteOperation
        from app.proxy.routes import _check_permission_gate

        user = MagicMock()
        user.preferred_username = "testuser"
        user.realm_access = {"roles": ["organization.read"]}

        registry = ModuleRegistry(backends={})
        screen = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/companies": {
                    "GET": RouteOperation(method="GET", path="/api/companies", permissions=["company.view"]),
                },
            },
        )
        registry._modules[ModuleName.SCREEN] = screen
        registry._build_route_index()

        response = _check_permission_gate("companies", "GET", user, registry, screen)

        assert response is not None
        assert response.status_code == 403
        assert b"Insufficient permissions" in response.body

    def test_allows_user_with_matching_permission(self):
        """User with company.view + endpoint requiring company.view → pass."""
        from app.proxy.registry import ModuleDefinition, ModuleName, ModuleRegistry, RouteOperation
        from app.proxy.routes import _check_permission_gate

        user = MagicMock()
        user.preferred_username = "testuser"
        user.realm_access = {"roles": ["company.view", "organization.read"]}

        registry = ModuleRegistry(backends={})
        screen = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/companies": {
                    "GET": RouteOperation(method="GET", path="/api/companies", permissions=["company.view"]),
                },
            },
        )
        registry._modules[ModuleName.SCREEN] = screen
        registry._build_route_index()

        response = _check_permission_gate("companies", "GET", user, registry, screen)

        assert response is None

    def test_allows_user_with_second_permission_or_logic(self):
        """User has only the second of two required permissions → pass (OR logic)."""
        from app.proxy.registry import ModuleDefinition, ModuleName, ModuleRegistry, RouteOperation
        from app.proxy.routes import _check_permission_gate

        user = MagicMock()
        user.preferred_username = "testuser"
        user.realm_access = {"roles": ["admin.organizations"]}

        registry = ModuleRegistry(backends={})
        screen = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/companies": {
                    "GET": RouteOperation(
                        method="GET", path="/api/companies",
                        permissions=["company.view", "admin.organizations"],
                    ),
                },
            },
        )
        registry._modules[ModuleName.SCREEN] = screen
        registry._build_route_index()

        response = _check_permission_gate("companies", "GET", user, registry, screen)

        assert response is None

    def test_allows_endpoint_without_permissions(self):
        """Endpoint with no x-permissions → pass (auth alone is enough)."""
        from app.proxy.registry import ModuleDefinition, ModuleName, ModuleRegistry, RouteOperation
        from app.proxy.routes import _check_permission_gate

        user = MagicMock()
        user.preferred_username = "testuser"
        user.realm_access = {"roles": ["organization.read"]}

        registry = ModuleRegistry(backends={})
        screen = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/health": {
                    "GET": RouteOperation(method="GET", path="/api/health", permissions=[]),
                },
            },
        )
        registry._modules[ModuleName.SCREEN] = screen
        registry._build_route_index()

        response = _check_permission_gate("health", "GET", user, registry, screen)

        assert response is None

    def test_allows_public_route_without_user(self):
        """No user (public route) → pass regardless of permissions."""
        from app.proxy.registry import ModuleDefinition, ModuleName
        from app.proxy.routes import _check_permission_gate

        module = ModuleDefinition(name=ModuleName.SCREEN, backend_url="http://screen:8000")

        response = _check_permission_gate("health/live", "GET", None, None, module)

        assert response is None

    def test_allows_authenticated_user_when_registry_is_none(self):
        """Registry not initialized + authenticated user → fail-open (pass)."""
        from app.proxy.registry import ModuleDefinition, ModuleName
        from app.proxy.routes import _check_permission_gate

        user = MagicMock()
        user.preferred_username = "testuser"
        user.realm_access = {"roles": ["organization.read"]}

        module = ModuleDefinition(name=ModuleName.SCREEN, backend_url="http://screen:8000")

        response = _check_permission_gate("companies", "GET", user, None, module)

        # Fail-open: backend is still responsible for its own permission checks
        assert response is None

    def test_rejects_user_without_permission_on_parameterized_route(self):
        """Parameterized route /api/companies/{id} → permission required, wrong role → 403."""
        from app.proxy.registry import ModuleDefinition, ModuleName, ModuleRegistry, RouteOperation
        from app.proxy.routes import _check_permission_gate

        user = MagicMock()
        user.preferred_username = "testuser"
        user.realm_access = {"roles": ["organization.read"]}

        registry = ModuleRegistry(backends={})
        screen = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/companies/{company_id}": {
                    "GET": RouteOperation(
                        method="GET", path="/api/companies/{company_id}", permissions=["company.view"]
                    ),
                },
            },
        )
        registry._modules[ModuleName.SCREEN] = screen
        registry._build_route_index()

        # resolve_operation handles parameterized routes via prefix matching
        response = _check_permission_gate("companies/some-uuid", "GET", user, registry, screen)

        assert response is not None
        assert response.status_code == 403

    def test_allows_user_with_permission_on_parameterized_route(self):
        """User with company.view + parameterized route /api/companies/{id} → pass."""
        from app.proxy.registry import ModuleDefinition, ModuleName, ModuleRegistry, RouteOperation
        from app.proxy.routes import _check_permission_gate

        user = MagicMock()
        user.preferred_username = "testuser"
        user.realm_access = {"roles": ["company.view"]}

        registry = ModuleRegistry(backends={})
        screen = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/companies/{company_id}": {
                    "GET": RouteOperation(
                        method="GET", path="/api/companies/{company_id}", permissions=["company.view"]
                    ),
                },
            },
        )
        registry._modules[ModuleName.SCREEN] = screen
        registry._build_route_index()

        response = _check_permission_gate("companies/some-uuid", "GET", user, registry, screen)

        assert response is None
