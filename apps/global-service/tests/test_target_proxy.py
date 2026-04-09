"""
Tests for the Target proxy — /api/target/* forwarding with module check.

Covers:
1. _is_target_enabled_for_org: DB lookup, caching, fail-closed, empty org_id
2. target_proxy route: 401 when not authenticated, 403 when module disabled,
   200 (forwarded) when module enabled
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch

from app.models.organization import ModuleName, OrganizationModule


# ─── Unit tests: _is_target_enabled_for_org ────────────────────────────────


class TestIsTargetEnabledForOrg:
    """Unit tests for the module-enabled DB helper."""

    @pytest.fixture(autouse=True)
    def clear_module_cache(self):
        """Reset the TTL cache before every test to avoid state leakage."""
        from app.api.routes.target_proxy import _module_cache
        _module_cache.clear()
        yield
        _module_cache.clear()

    @pytest.mark.asyncio
    async def test_returns_false_for_empty_org_id(self):
        from app.api.routes.target_proxy import _is_target_enabled_for_org
        assert await _is_target_enabled_for_org("") is False

    @pytest.mark.asyncio
    async def test_returns_true_when_module_enabled(self, global_db_session):
        """Returns True when OrganizationModule(TARGET, enabled=True) exists."""
        from app.api.routes.target_proxy import _is_target_enabled_for_org

        module = OrganizationModule(
            organization_id="org-enabled",
            module_name=ModuleName.TARGET,
            enabled=True,
        )
        global_db_session.add(module)
        await global_db_session.commit()

        with patch(
            "app.api.routes.target_proxy.get_global_db_context",
            return_value=_async_ctx(global_db_session),
        ):
            result = await _is_target_enabled_for_org("org-enabled")

        assert result is True

    @pytest.mark.asyncio
    async def test_returns_false_when_module_disabled(self, global_db_session):
        """Returns False when the module row exists but enabled=False."""
        from app.api.routes.target_proxy import _is_target_enabled_for_org

        module = OrganizationModule(
            organization_id="org-disabled",
            module_name=ModuleName.TARGET,
            enabled=False,
        )
        global_db_session.add(module)
        await global_db_session.commit()

        with patch(
            "app.api.routes.target_proxy.get_global_db_context",
            return_value=_async_ctx(global_db_session),
        ):
            result = await _is_target_enabled_for_org("org-disabled")

        assert result is False

    @pytest.mark.asyncio
    async def test_returns_false_when_no_row_exists(self, global_db_session):
        """Returns False when no OrganizationModule row exists for this org."""
        from app.api.routes.target_proxy import _is_target_enabled_for_org

        with patch(
            "app.api.routes.target_proxy.get_global_db_context",
            return_value=_async_ctx(global_db_session),
        ):
            result = await _is_target_enabled_for_org("org-unknown")

        assert result is False

    @pytest.mark.asyncio
    async def test_result_is_cached(self, global_db_session):
        """Second call with same org_id uses cache, not DB."""
        from app.api.routes.target_proxy import _is_target_enabled_for_org

        module = OrganizationModule(
            organization_id="org-cache",
            module_name=ModuleName.TARGET,
            enabled=True,
        )
        global_db_session.add(module)
        await global_db_session.commit()

        ctx = _async_ctx(global_db_session)
        with patch(
            "app.api.routes.target_proxy.get_global_db_context",
            return_value=ctx,
        ) as mock_ctx:
            await _is_target_enabled_for_org("org-cache")
            await _is_target_enabled_for_org("org-cache")
            # DB context used only once — second call hit the cache
            assert mock_ctx.call_count == 1

    @pytest.mark.asyncio
    async def test_fails_closed_on_db_error(self):
        """Returns False (denies access) when the DB raises an exception."""
        from app.api.routes.target_proxy import _is_target_enabled_for_org

        broken_ctx = MagicMock()
        broken_ctx.__aenter__ = AsyncMock(side_effect=RuntimeError("DB unavailable"))
        broken_ctx.__aexit__ = AsyncMock(return_value=False)

        with patch(
            "app.api.routes.target_proxy.get_global_db_context",
            return_value=broken_ctx,
        ):
            result = await _is_target_enabled_for_org("org-any")

        assert result is False


# ─── Integration tests: proxy route ─────────────────────────────────────────


class TestTargetProxyRoute:
    """Tests for the /api/target/* route handler."""

    def _make_gateway_user(self, org_id: str = "org-123"):
        from app.core.keycloak import OIDCUser
        from datetime import UTC, datetime

        now = int(datetime.now(UTC).timestamp())
        return OIDCUser(
            sub="user-abc",
            preferred_username="testuser",
            email="test@example.com",
            email_verified=True,
            iat=now,
            exp=now + 3600,
            organization=["Test Org", {"Test Org": {"id": org_id}}],
            enabled_modules=["Target"],
        )

    @pytest.fixture(autouse=True)
    def clear_module_cache(self):
        from app.api.routes.target_proxy import _module_cache
        _module_cache.clear()
        yield
        _module_cache.clear()

    @pytest.mark.asyncio
    async def test_returns_401_when_not_authenticated(self, client):
        """Unauthenticated request → 401, proxy never called."""
        with patch(
            "app.api.routes.target_proxy.auth_middleware.validate_request",
            new_callable=AsyncMock,
            return_value=(False, None, {}),
        ):
            response = await client.get("/api/target/watch_files")

        assert response.status_code == 401
        assert response.json()["detail"] == "Not authenticated"

    @pytest.mark.asyncio
    async def test_returns_403_when_module_disabled(self, client):
        """Authenticated request but TARGET module disabled → 403."""
        user = self._make_gateway_user(org_id="org-no-target")

        with (
            patch(
                "app.api.routes.target_proxy.auth_middleware.validate_request",
                new_callable=AsyncMock,
                return_value=(True, user, {"Authorization": "Internal fake-jwt"}),
            ),
            patch(
                "app.api.routes.target_proxy._is_target_enabled_for_org",
                new_callable=AsyncMock,
                return_value=False,
            ),
        ):
            response = await client.get("/api/target/watch_files")

        assert response.status_code == 403
        assert response.json()["detail"] == "Module not enabled for your organization"

    @pytest.mark.asyncio
    async def test_forwards_request_when_module_enabled(self, client):
        """Authenticated + module enabled → request forwarded to Target backend."""
        user = self._make_gateway_user(org_id="org-with-target")

        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.headers = MagicMock(items=lambda: [("content-type", "application/json")])
        mock_response.headers.get = lambda k, d=None: "application/json" if k == "content-type" else d

        async def fake_stream():
            yield b'{"data": "ok"}'

        mock_response.aiter_bytes = fake_stream
        mock_response.aclose = AsyncMock()

        mock_httpx_client = AsyncMock()
        mock_httpx_client.build_request = MagicMock(return_value=MagicMock())
        mock_httpx_client.send = AsyncMock(return_value=mock_response)

        with (
            patch(
                "app.api.routes.target_proxy.auth_middleware.validate_request",
                new_callable=AsyncMock,
                return_value=(True, user, {"Authorization": "Internal fake-jwt"}),
            ),
            patch(
                "app.api.routes.target_proxy._is_target_enabled_for_org",
                new_callable=AsyncMock,
                return_value=True,
            ),
            patch(
                "app.api.routes.target_proxy.get_proxy_client",
                new_callable=AsyncMock,
                return_value=mock_httpx_client,
            ),
        ):
            response = await client.get("/api/target/watch_files")

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_strips_target_prefix_in_forwarded_path(self, client):
        """Verifies that /api/target/watch_files is forwarded as /api/watch_files."""
        user = self._make_gateway_user()

        captured_path: list[str] = []

        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.headers = MagicMock(items=lambda: [])
        mock_response.headers.get = lambda k, d=None: None

        async def fake_stream():
            yield b""

        mock_response.aiter_bytes = fake_stream
        mock_response.aclose = AsyncMock()

        def capture_build_request(method, url, **kwargs):
            captured_path.append(url)
            return MagicMock()

        mock_httpx_client = AsyncMock()
        mock_httpx_client.build_request = MagicMock(side_effect=capture_build_request)
        mock_httpx_client.send = AsyncMock(return_value=mock_response)

        with (
            patch(
                "app.api.routes.target_proxy.auth_middleware.validate_request",
                new_callable=AsyncMock,
                return_value=(True, user, {"Authorization": "Internal fake-jwt"}),
            ),
            patch(
                "app.api.routes.target_proxy._is_target_enabled_for_org",
                new_callable=AsyncMock,
                return_value=True,
            ),
            patch(
                "app.api.routes.target_proxy.get_proxy_client",
                new_callable=AsyncMock,
                return_value=mock_httpx_client,
            ),
        ):
            await client.get("/api/target/watch_files")

        assert captured_path == ["/api/watch_files"]


# ─── Helpers ─────────────────────────────────────────────────────────────────


class _async_ctx:
    """Minimal async context manager that yields an existing session."""

    def __init__(self, session):
        self._session = session

    async def __aenter__(self):
        return self._session

    async def __aexit__(self, *args):
        pass
