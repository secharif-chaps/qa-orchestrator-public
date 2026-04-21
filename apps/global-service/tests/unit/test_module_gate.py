"""Tests for the module gate (TAR-1383).

Verifies that requests to disabled modules return 403,
enabled modules pass through, and public routes skip the gate.
"""

import time
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.proxy.registry import ModuleDefinition, ModuleName, RouteOperation

# ── Helper to build a mock registry resolving to a given module ──


def _mock_registry(module_name: ModuleName = ModuleName.SCREEN, is_public: bool = False) -> MagicMock:
    """Create a mock registry that resolves all paths to the given module."""
    registry = MagicMock()
    module = ModuleDefinition(name=module_name, backend_url="http://backend:8000")
    registry.resolve.return_value = (module, "/api/some-path")
    registry.resolve_operation.return_value = RouteOperation(
        method="GET",
        path="/api/some-path",
        is_public=is_public,
    )
    return registry


def _mock_auth_valid(org_id: str = "org-123"):
    """Return a mock for auth_middleware.validate_request that succeeds."""
    mock_user = MagicMock()
    mock_user.preferred_username = "testuser"
    mock_user.organization = ["TestOrg", {"TestOrg": {"id": org_id}}]
    mock_user.realm_access = {"roles": ["organization.read"]}
    return AsyncMock(return_value=(True, mock_user, {"Authorization": "Internal test-jwt"}))


def _mock_proxy_response():
    """Return mocks that simulate a successful backend response."""
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.headers = {"content-type": "application/json"}

    async def mock_aiter():
        yield b'{"ok":true}'

    mock_response.aiter_bytes = mock_aiter
    mock_response.aclose = AsyncMock()
    return mock_response


# ── Module gate: enabled / disabled ──


class TestModuleGateEnabled:
    """Requests to enabled modules should pass through."""

    @pytest.mark.asyncio
    async def test_enabled_module_passes(self, client):
        """Module enabled for org → request reaches backend."""
        registry = _mock_registry(ModuleName.TARGET)

        mock_client = AsyncMock()
        mock_client.build_request.return_value = MagicMock()
        mock_client.send = AsyncMock(return_value=_mock_proxy_response())

        with (
            patch("app.proxy.routes._registry", registry),
            patch("app.proxy.routes.auth_middleware") as mock_auth_mw,
            patch("app.proxy.routes.get_proxy_client", return_value=mock_client),
            patch("app.proxy.routes._check_module_enabled", new_callable=AsyncMock, return_value=None),
        ):
            mock_auth_mw.validate_request = _mock_auth_valid()
            response = await client.get("/api/watchfiles")

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_screen_fallback_also_checked(self, client):
        """SCREEN (default fallback) is also subject to module gate — disabled → 403."""
        from fastapi.responses import Response as FastAPIResponse

        registry = _mock_registry(ModuleName.SCREEN)
        gate_response = FastAPIResponse(
            content=b'{"detail":"Module \'screen\' not enabled for your organization"}',
            status_code=403,
            media_type="application/json",
        )

        with (
            patch("app.proxy.routes._registry", registry),
            patch("app.proxy.routes.auth_middleware") as mock_auth_mw,
            patch(
                "app.proxy.routes._check_module_enabled",
                new_callable=AsyncMock,
                return_value=gate_response,
            ),
        ):
            mock_auth_mw.validate_request = _mock_auth_valid()
            response = await client.get("/api/companies")

        assert response.status_code == 403
        assert "screen" in response.json()["detail"]


class TestModuleGateDisabled:
    """Requests to disabled modules should return 403."""

    @pytest.mark.asyncio
    async def test_disabled_module_returns_403(self, client):
        """Module disabled for org → 403 with clear error message."""
        from fastapi.responses import Response as FastAPIResponse

        registry = _mock_registry(ModuleName.TARGET)
        gate_response = FastAPIResponse(
            content=b'{"detail":"Module \'target\' not enabled for your organization"}',
            status_code=403,
            media_type="application/json",
        )

        with (
            patch("app.proxy.routes._registry", registry),
            patch("app.proxy.routes.auth_middleware") as mock_auth_mw,
            patch(
                "app.proxy.routes._check_module_enabled",
                new_callable=AsyncMock,
                return_value=gate_response,
            ),
        ):
            mock_auth_mw.validate_request = _mock_auth_valid()
            response = await client.get("/api/watchfiles")

        assert response.status_code == 403

    @pytest.mark.asyncio
    async def test_403_message_includes_module_name(self, client):
        """Error message should name the disabled module."""
        from app.proxy.routes import Response

        gate_response = Response(
            content=b'{"detail":"Module \'target\' not enabled for your organization"}',
            status_code=403,
            media_type="application/json",
        )

        registry = _mock_registry(ModuleName.TARGET)

        with (
            patch("app.proxy.routes._registry", registry),
            patch("app.proxy.routes.auth_middleware") as mock_auth_mw,
            patch("app.proxy.routes._check_module_enabled", new_callable=AsyncMock, return_value=gate_response),
        ):
            mock_auth_mw.validate_request = _mock_auth_valid()
            response = await client.get("/api/watchfiles")

        assert response.status_code == 403
        assert "target" in response.json()["detail"]


class TestModuleGatePublicRoutes:
    """Public routes should skip the module gate."""

    @pytest.mark.asyncio
    async def test_public_route_skips_module_gate(self, client):
        """x-public: true → no module gate check."""
        registry = _mock_registry(ModuleName.SCREEN, is_public=True)

        mock_client = AsyncMock()
        mock_client.build_request.return_value = MagicMock()
        mock_client.send = AsyncMock(return_value=_mock_proxy_response())

        with (
            patch("app.proxy.routes._registry", registry),
            patch("app.proxy.routes.get_proxy_client", return_value=mock_client),
            patch("app.proxy.routes._check_module_enabled", new_callable=AsyncMock) as mock_gate,
        ):
            await client.get("/api/health")

        # Module gate should NOT have been called for public routes
        mock_gate.assert_not_called()


class TestModuleGateNoUser:
    """When there's no user (fallback public via auth_middleware), skip gate."""

    @pytest.mark.asyncio
    async def test_no_user_skips_module_gate(self, client):
        """Unauthenticated request (public fallback) → no module gate."""
        registry = _mock_registry(ModuleName.SCREEN)
        # Auth returns valid but no user (public route via fallback)
        registry.resolve_operation.return_value = RouteOperation(method="GET", path="/api/health", is_public=True)

        mock_client = AsyncMock()
        mock_client.build_request.return_value = MagicMock()
        mock_client.send = AsyncMock(return_value=_mock_proxy_response())

        with (
            patch("app.proxy.routes._registry", registry),
            patch("app.proxy.routes.get_proxy_client", return_value=mock_client),
            patch("app.proxy.routes._check_module_enabled", new_callable=AsyncMock) as mock_gate,
        ):
            await client.get("/api/health")

        mock_gate.assert_not_called()


# ── Cache behavior ──


class TestModuleGateCache:
    """Test that module enablement is cached with TTL."""

    def test_cache_returns_same_result_within_ttl(self):
        """Within TTL, cache should not re-query DB."""
        from app.proxy.routes import _module_enabled_cache

        # Manually populate cache
        _module_enabled_cache[("org-1", "screen")] = (True, time.time())
        _module_enabled_cache[("org-1", "target")] = (False, time.time())

        assert _module_enabled_cache[("org-1", "screen")][0] is True
        assert _module_enabled_cache[("org-1", "target")][0] is False

    def test_cache_expires_after_ttl(self):
        """After TTL, cache entry should be considered stale."""
        from app.proxy.routes import _MODULE_GATE_CACHE_TTL, _module_enabled_cache

        # Set cache entry in the past
        _module_enabled_cache[("org-1", "screen")] = (True, time.time() - _MODULE_GATE_CACHE_TTL - 1)

        ts = _module_enabled_cache[("org-1", "screen")][1]
        assert (time.time() - ts) > _MODULE_GATE_CACHE_TTL


class TestCheckModuleEnabled:
    """Test the _check_module_enabled helper function directly."""

    @pytest.mark.asyncio
    async def test_returns_none_when_enabled(self):
        """Enabled module returns None (allow request)."""
        from app.proxy.routes import _check_module_enabled, _module_enabled_cache

        # Pre-populate cache so no DB call needed
        _module_enabled_cache[("org-1", "screen")] = (True, time.time())

        mock_user = MagicMock()
        mock_user.organization = ["Org", {"Org": {"id": "org-1"}}]

        result = await _check_module_enabled(ModuleName.SCREEN, mock_user)
        assert result is None

    @pytest.mark.asyncio
    async def test_returns_403_when_disabled(self):
        """Disabled module returns 403 Response."""
        from app.proxy.routes import _check_module_enabled, _module_enabled_cache

        _module_enabled_cache[("org-1", "target")] = (False, time.time())

        mock_user = MagicMock()
        mock_user.organization = ["Org", {"Org": {"id": "org-1"}}]

        result = await _check_module_enabled(ModuleName.TARGET, mock_user)
        assert result is not None
        assert result.status_code == 403

    @pytest.mark.asyncio
    async def test_returns_none_when_no_org_id(self):
        """User without org_id → skip gate (allow request)."""
        from app.proxy.routes import _check_module_enabled

        mock_user = MagicMock()
        mock_user.organization = None

        result = await _check_module_enabled(ModuleName.SCREEN, mock_user)
        assert result is None

    @pytest.mark.asyncio
    async def test_queries_db_on_cache_miss(self):
        """On cache miss, should query OrganizationModule in DB."""
        from app.proxy.routes import _check_module_enabled, _module_enabled_cache

        # Clear cache
        _module_enabled_cache.clear()

        mock_user = MagicMock()
        mock_user.organization = ["Org", {"Org": {"id": "org-miss"}}]

        mock_db_result = MagicMock()
        mock_db_result.scalar_one_or_none.return_value = MagicMock(enabled=True)

        mock_session = AsyncMock()
        mock_session.execute = AsyncMock(return_value=mock_db_result)
        mock_session.__aenter__ = AsyncMock(return_value=mock_session)
        mock_session.__aexit__ = AsyncMock(return_value=False)

        with patch("app.database.get_global_db_context", return_value=mock_session):
            result = await _check_module_enabled(ModuleName.SCREEN, mock_user)

        assert result is None
        # Cache should now be populated
        assert ("org-miss", "screen") in _module_enabled_cache

    @pytest.mark.asyncio
    async def test_db_error_allows_request(self):
        """DB error → fail-open (allow request, log warning)."""
        from app.proxy.routes import _check_module_enabled, _module_enabled_cache

        _module_enabled_cache.clear()

        mock_user = MagicMock()
        mock_user.organization = ["Org", {"Org": {"id": "org-err"}}]

        mock_session = AsyncMock()
        mock_session.execute = AsyncMock(side_effect=Exception("DB down"))
        mock_session.__aenter__ = AsyncMock(return_value=mock_session)
        mock_session.__aexit__ = AsyncMock(return_value=False)

        with patch("app.database.get_global_db_context", return_value=mock_session):
            result = await _check_module_enabled(ModuleName.SCREEN, mock_user)

        # Fail-open: allow request
        assert result is None

    @pytest.mark.asyncio
    async def test_always_active_module_skips_gate(self):
        """Stream is always active — should skip DB check entirely."""
        from app.proxy.routes import _check_module_enabled, _module_enabled_cache

        _module_enabled_cache.clear()

        mock_user = MagicMock()
        mock_user.organization = ["Org", {"Org": {"id": "org-1"}}]

        # No cache entry, no DB mock — if it tried to query DB it would fail
        result = await _check_module_enabled(ModuleName.STREAM, mock_user)
        assert result is None
        # Cache should NOT be populated (gate was skipped, not queried)
        assert ("org-1", "stream") not in _module_enabled_cache

    @pytest.mark.asyncio
    async def test_non_always_active_module_still_checked(self):
        """Screen and target are NOT always-active — should still be gated."""
        from app.proxy.routes import _check_module_enabled, _module_enabled_cache

        _module_enabled_cache[("org-1", "screen")] = (False, time.time())
        _module_enabled_cache[("org-1", "target")] = (False, time.time())

        mock_user = MagicMock()
        mock_user.organization = ["Org", {"Org": {"id": "org-1"}}]

        screen_result = await _check_module_enabled(ModuleName.SCREEN, mock_user)
        target_result = await _check_module_enabled(ModuleName.TARGET, mock_user)
        assert screen_result is not None and screen_result.status_code == 403
        assert target_result is not None and target_result.status_code == 403
