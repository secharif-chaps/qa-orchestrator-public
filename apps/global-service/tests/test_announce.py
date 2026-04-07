"""Tests for the self-announce endpoint and schema hash detection.

Covers:
- Announce endpoint: unchanged, rediscovered, unknown module, auth
- Hash calculation: stored after discovery, stable, changes with schema
- Healthcheck schema staleness detection
"""

import hashlib
import json
import time
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.core.internal_jwt import InternalTokenPayload, get_internal_token
from app.proxy.registry import ModuleDefinition, ModuleName, ModuleRegistry

# Minimal OpenAPI schemas for testing
SCHEMA_A = {
    "openapi": "3.0.0",
    "info": {"title": "Screen", "version": "0.1.0"},
    "paths": {
        "/api/companies": {
            "get": {"summary": "List companies"},
        },
    },
}

SCHEMA_B = {
    "openapi": "3.0.0",
    "info": {"title": "Screen", "version": "0.2.0"},
    "paths": {
        "/api/companies": {
            "get": {"summary": "List companies"},
        },
        "/api/tasks": {
            "get": {"summary": "List tasks"},
        },
    },
}


def _compute_hash(schema: dict) -> str:
    """Compute the sha256 hash for a schema (same algorithm as registry)."""
    return hashlib.sha256(
        json.dumps(schema, sort_keys=True).encode()
    ).hexdigest()


def _mock_response(schema: dict) -> MagicMock:
    """Create a mock httpx response returning the given schema."""
    resp = MagicMock()
    resp.status_code = 200
    resp.json.return_value = schema
    resp.raise_for_status = MagicMock()
    return resp


# ── Hash calculation tests ──


class TestHashCalculation:
    """Test that schema hashes are computed and stored correctly."""

    @pytest.mark.asyncio
    async def test_fetch_and_register_stores_hash(self):
        """Hash should be populated after discovery."""
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCHEMA_A))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

        module = registry.modules[ModuleName.SCREEN]
        assert module.openapi_hash is not None
        assert module.openapi_hash == _compute_hash(SCHEMA_A)

    @pytest.mark.asyncio
    async def test_hash_stable_for_same_schema(self):
        """Same schema should produce the same hash on repeated discovery."""
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCHEMA_A))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()
            hash_first = registry.modules[ModuleName.SCREEN].openapi_hash

            await registry.rediscover_module(ModuleName.SCREEN)
            hash_second = registry.modules[ModuleName.SCREEN].openapi_hash

        assert hash_first == hash_second

    @pytest.mark.asyncio
    async def test_hash_changes_when_schema_changes(self):
        """Different schema should produce a different hash."""
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCHEMA_A))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()
            hash_a = registry.modules[ModuleName.SCREEN].openapi_hash

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCHEMA_B))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.rediscover_module(ModuleName.SCREEN)
            hash_b = registry.modules[ModuleName.SCREEN].openapi_hash

        assert hash_a != hash_b

    def test_get_module_hash_returns_none_for_unknown(self):
        """get_module_hash should return None for unregistered modules."""
        registry = ModuleRegistry(backends={})
        assert registry.get_module_hash(ModuleName.TARGET) is None

    def test_get_module_hash_returns_stored_hash(self):
        """get_module_hash should return the stored hash value."""
        registry = ModuleRegistry(backends={})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            openapi_hash="abc123",
        )
        assert registry.get_module_hash(ModuleName.SCREEN) == "abc123"


# ── check_and_rediscover_stale tests ──


class TestCheckAndRediscoverStale:
    """Test the schema staleness detection method."""

    @pytest.mark.asyncio
    async def test_no_change_returns_unchanged(self):
        """Same hash should return 'unchanged' without rediscovery."""
        schema_hash = _compute_hash(SCHEMA_A)
        registry = ModuleRegistry(backends={})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            openapi_hash=schema_hash,
        )
        registry._build_route_index()

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCHEMA_A))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            results = await registry.check_and_rediscover_stale()

        assert results["screen"] == "unchanged"

    @pytest.mark.asyncio
    async def test_hash_changed_triggers_rediscovery(self):
        """Different hash should trigger rediscovery and return 'rediscovered'."""
        old_hash = _compute_hash(SCHEMA_A)
        registry = ModuleRegistry(backends={})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            openapi_hash=old_hash,
        )
        registry._build_route_index()

        # Mock: check_and_rediscover_stale fetches SCHEMA_B (different hash)
        # and rediscover_module also fetches SCHEMA_B
        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCHEMA_B))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            results = await registry.check_and_rediscover_stale()

        assert results["screen"] == "rediscovered"

    @pytest.mark.asyncio
    async def test_backend_down_returns_error(self):
        """Unreachable backend should return 'error' without crashing."""
        registry = ModuleRegistry(backends={})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            openapi_hash="some-hash",
        )

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(side_effect=Exception("Connection refused"))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            results = await registry.check_and_rediscover_stale()

        assert results["screen"] == "error"


# ── Healthcheck registry schema integration ──


class TestCheckRegistrySchemas:
    """Test the health check function for registry schema staleness."""

    @pytest.mark.asyncio
    async def test_returns_ok_when_registry_not_initialized(self):
        """Should return informational 'ok' when registry is None."""
        from app.health import check_registry_schemas

        with patch("app.proxy.routes.get_module_registry", return_value=None):
            result = await check_registry_schemas()

        assert result["status"] == "ok"
        assert "not initialized" in result["detail"]

    @pytest.mark.asyncio
    async def test_returns_ok_with_module_results(self):
        """Should return per-module results from the registry."""
        from app.health import check_registry_schemas

        mock_registry = AsyncMock()
        mock_registry.check_and_rediscover_stale = AsyncMock(
            return_value={"screen": "unchanged"}
        )

        with patch("app.proxy.routes.get_module_registry", return_value=mock_registry):
            result = await check_registry_schemas()

        assert result["status"] == "ok"
        assert result["modules"] == {"screen": "unchanged"}

    @pytest.mark.asyncio
    async def test_returns_error_on_exception(self):
        """Should catch exceptions and return error status."""
        from app.health import check_registry_schemas

        mock_registry = AsyncMock()
        mock_registry.check_and_rediscover_stale = AsyncMock(
            side_effect=RuntimeError("boom")
        )

        with patch("app.proxy.routes.get_module_registry", return_value=mock_registry):
            result = await check_registry_schemas()

        assert result["status"] == "error"

    @pytest.mark.asyncio
    async def test_schema_check_does_not_affect_readiness(self):
        """Registry schema check failure should not make readiness fail."""
        from app.health import run_readiness_checks

        with (
            patch("app.health.check_database", AsyncMock(return_value=True)),
            patch("app.health.check_grpc", AsyncMock(return_value=True)),
            patch("app.health.check_proxy_client", AsyncMock(return_value=True)),
            patch(
                "app.health.check_registry_schemas",
                AsyncMock(side_effect=RuntimeError("schema check boom")),
            ),
        ):
            checks, all_ready = await run_readiness_checks(include_keycloak=False)

        # Core checks pass, registry schema failure is informational
        assert all_ready is True
        assert checks == {"database": True, "grpc": True, "proxy_client": True}


# ── Announce endpoint tests ──


def _make_token_payload() -> InternalTokenPayload:
    """Create a test internal token payload for announce tests."""
    return InternalTokenPayload(
        sub="screen-service",
        username="screen",
        org_id="system",
        org_name="system",
        roles=["service"],
    )


# Valid sha256 hex strings for endpoint tests (64 chars, hex only)
_HASH_A = "a" * 64
_HASH_B = "b" * 64
_HASH_C = "c" * 64


class TestAnnounceEndpoint:
    """Test the POST /internal/registry/announce/{module_name} endpoint."""

    @pytest.fixture(autouse=True)
    def _override_auth(self, client):
        """Override the internal JWT dependency for all announce tests."""
        payload = _make_token_payload()
        client.app.dependency_overrides[get_internal_token] = lambda: payload
        yield
        client.app.dependency_overrides.pop(get_internal_token, None)

    @pytest.mark.asyncio
    async def test_announce_hash_unchanged(self, client):
        """Same hash should return 'unchanged' without rediscovery."""
        mock_registry = MagicMock()
        mock_registry.get_module_hash.return_value = _HASH_A

        with patch("app.api.endpoints.internal.get_module_registry", return_value=mock_registry):
            response = await client.post(
                "/api/internal/registry/announce/screen",
                json={"openapi_hash": _HASH_A},
            )

        assert response.status_code == 200
        data = response.json()
        assert data["module"] == "screen"
        assert data["action"] == "unchanged"

    @pytest.mark.asyncio
    async def test_announce_hash_changed(self, client):
        """Different hash should trigger rediscovery."""
        mock_registry = MagicMock()
        mock_registry.get_module_hash.return_value = _HASH_A
        mock_registry.rediscover_module = AsyncMock()

        with patch("app.api.endpoints.internal.get_module_registry", return_value=mock_registry):
            response = await client.post(
                "/api/internal/registry/announce/screen",
                json={"openapi_hash": _HASH_B},
            )

        assert response.status_code == 200
        data = response.json()
        assert data["module"] == "screen"
        assert data["action"] == "rediscovered"
        mock_registry.rediscover_module.assert_called_once()

    @pytest.mark.asyncio
    async def test_announce_unknown_module(self, client):
        """Invalid module name should return 400."""
        response = await client.post(
            "/api/internal/registry/announce/nonexistent",
            json={"openapi_hash": _HASH_A},
        )

        assert response.status_code == 400

    @pytest.mark.asyncio
    async def test_announce_requires_internal_jwt(self, client):
        """Missing authorization header should return 401/422."""
        # Remove the auth override so the real dependency runs
        client.app.dependency_overrides.pop(get_internal_token, None)

        response = await client.post(
            "/api/internal/registry/announce/screen",
            json={"openapi_hash": _HASH_A},
        )

        # FastAPI returns 422 for missing required header
        assert response.status_code in (401, 422)

    @pytest.mark.asyncio
    async def test_announce_registry_not_initialized(self, client):
        """Announce when registry is None should return 503."""
        with patch("app.api.endpoints.internal.get_module_registry", return_value=None):
            response = await client.post(
                "/api/internal/registry/announce/screen",
                json={"openapi_hash": _HASH_A},
            )

        assert response.status_code == 503

    @pytest.mark.asyncio
    async def test_announce_triggers_rediscovery_on_hash_mismatch(self, client):
        """Verify rediscover_module is called with the correct ModuleName."""
        mock_registry = MagicMock()
        mock_registry.get_module_hash.return_value = _HASH_A
        mock_registry.rediscover_module = AsyncMock()

        with patch("app.api.endpoints.internal.get_module_registry", return_value=mock_registry):
            await client.post(
                "/api/internal/registry/announce/screen",
                json={"openapi_hash": _HASH_B},
            )

        mock_registry.rediscover_module.assert_called_once_with(ModuleName.SCREEN)


# ── Hash validation edge cases (Point 8) ──


class TestAnnounceHashValidation:
    """Test that malformed hashes are rejected by Pydantic validation."""

    @pytest.fixture(autouse=True)
    def _override_auth(self, client):
        payload = _make_token_payload()
        client.app.dependency_overrides[get_internal_token] = lambda: payload
        yield
        client.app.dependency_overrides.pop(get_internal_token, None)

    @pytest.mark.asyncio
    async def test_empty_hash_rejected(self, client):
        """Empty string should be rejected (min_length=64)."""
        response = await client.post(
            "/api/internal/registry/announce/screen",
            json={"openapi_hash": ""},
        )
        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_short_hash_rejected(self, client):
        """Hash shorter than 64 chars should be rejected."""
        response = await client.post(
            "/api/internal/registry/announce/screen",
            json={"openapi_hash": "abc123"},
        )
        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_non_hex_hash_rejected(self, client):
        """Hash with non-hex characters should be rejected."""
        response = await client.post(
            "/api/internal/registry/announce/screen",
            json={"openapi_hash": "g" * 64},  # 'g' is not hex
        )
        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_missing_body_rejected(self, client):
        """Request without JSON body should be rejected."""
        response = await client.post("/api/internal/registry/announce/screen")
        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_valid_hash_accepted(self, client):
        """Valid 64-char hex hash should be accepted."""
        mock_registry = MagicMock()
        mock_registry.get_module_hash.return_value = _HASH_A

        with patch("app.api.endpoints.internal.get_module_registry", return_value=mock_registry):
            response = await client.post(
                "/api/internal/registry/announce/screen",
                json={"openapi_hash": _HASH_A},
            )
        assert response.status_code == 200


# ── Multi-module staleness check (Point 9) ──


class TestCheckAndRediscoverStaleMultiModule:
    """Test staleness check with multiple modules."""

    @pytest.mark.asyncio
    async def test_one_changed_one_unchanged(self):
        """When one module changes and another doesn't, both are reported correctly."""
        hash_screen = _compute_hash(SCHEMA_A)
        registry = ModuleRegistry(backends={})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN, backend_url="http://screen:8000", openapi_hash=hash_screen,
        )
        registry._modules[ModuleName.TARGET] = ModuleDefinition(
            name=ModuleName.TARGET, backend_url="http://target:8000", openapi_hash="stale-hash",
        )
        registry._build_route_index()

        def _mock_get(url):
            if "screen" in url:
                return _mock_response(SCHEMA_A)  # unchanged
            return _mock_response(SCHEMA_B)  # target changed

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(side_effect=lambda url: _mock_get(url))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            results = await registry.check_and_rediscover_stale()

        assert results["screen"] == "unchanged"
        assert results["target"] == "rediscovered"

    @pytest.mark.asyncio
    async def test_ttl_skips_check(self):
        """Within TTL window, check should return 'skipped' for all modules."""
        registry = ModuleRegistry(backends={})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN, backend_url="http://screen:8000", openapi_hash="hash",
        )
        registry._last_stale_check = time.monotonic()

        results = await registry.check_and_rediscover_stale()
        assert results["screen"] == "skipped"


# ── Edge case tests: network failures, malformed data, empty schemas ──


class TestStaleCheckNetworkFailures:
    """Test network failure handling in check_and_rediscover_stale."""

    @pytest.mark.asyncio
    async def test_malformed_json_returns_error(self):
        """Backend returns 200 but with invalid JSON — should not crash."""
        registry = ModuleRegistry(backends={})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN, backend_url="http://screen:8000", openapi_hash="hash",
        )

        mock_resp = MagicMock()
        mock_resp.raise_for_status = MagicMock()
        mock_resp.json.side_effect = ValueError("Expecting value")

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=mock_resp)
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            results = await registry.check_and_rediscover_stale()

        assert results["screen"] == "error"

    @pytest.mark.asyncio
    async def test_timeout_returns_error(self):
        """Backend timeout should return 'error' without crashing."""
        import httpx

        registry = ModuleRegistry(backends={})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN, backend_url="http://screen:8000", openapi_hash="hash",
        )

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(side_effect=httpx.TimeoutException("timed out"))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            results = await registry.check_and_rediscover_stale()

        assert results["screen"] == "error"


class TestEmptySchemaRediscovery:
    """Test behavior with empty or minimal schemas."""

    @pytest.mark.asyncio
    async def test_empty_schema_clears_routes(self):
        """Schema with no paths should clear module routes after rediscovery."""
        empty_schema = {"openapi": "3.0.0", "info": {"title": "Screen", "version": "0.1.0"}, "paths": {}}

        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        # First: discover with routes
        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCHEMA_A))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            await registry.discover_all()

        assert len(registry.modules[ModuleName.SCREEN].routes) > 0

        # Then: rediscover with empty schema
        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(empty_schema))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            await registry.rediscover_module(ModuleName.SCREEN)

        assert len(registry.modules[ModuleName.SCREEN].routes) == 0

    @pytest.mark.asyncio
    async def test_schema_without_paths_key(self):
        """Schema missing the 'paths' key entirely should not crash."""
        no_paths_schema = {"openapi": "3.0.0", "info": {"title": "Screen", "version": "0.1.0"}}

        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(no_paths_schema))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            await registry.discover_all()

        assert ModuleName.SCREEN in registry.modules
        assert len(registry.modules[ModuleName.SCREEN].routes) == 0

    @pytest.mark.asyncio
    async def test_prefetched_schema_skips_http_call(self):
        """rediscover_module with prefetched_schema should not make HTTP requests."""
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        # Initial discovery
        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCHEMA_A))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            await registry.discover_all()

        old_hash = registry.modules[ModuleName.SCREEN].openapi_hash

        # Rediscover with prefetched schema — no HTTP mock needed
        await registry.rediscover_module(ModuleName.SCREEN, prefetched_schema=SCHEMA_B)

        new_hash = registry.modules[ModuleName.SCREEN].openapi_hash
        assert new_hash != old_hash
        assert new_hash == _compute_hash(SCHEMA_B)


class TestModuleDownThenUp:
    """Test module going down and coming back with different schema."""

    @pytest.mark.asyncio
    async def test_module_recovers_with_new_schema(self):
        """Module goes down (error), then comes back with different schema."""
        registry = ModuleRegistry(backends={})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN, backend_url="http://screen:8000", openapi_hash=_compute_hash(SCHEMA_A),
        )
        registry._build_route_index()

        # First check: backend is down
        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(side_effect=Exception("Connection refused"))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            results_down = await registry.check_and_rediscover_stale()

        assert results_down["screen"] == "error"
        # Hash unchanged (still old value)
        assert registry.modules[ModuleName.SCREEN].openapi_hash == _compute_hash(SCHEMA_A)

        # Reset TTL so next check runs
        registry._last_stale_check = 0

        # Second check: backend is back with SCHEMA_B
        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCHEMA_B))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)
            results_up = await registry.check_and_rediscover_stale()

        assert results_up["screen"] == "rediscovered"
        assert registry.modules[ModuleName.SCREEN].openapi_hash == _compute_hash(SCHEMA_B)


class TestAnnounceRediscoveryFailure:
    """Test announce endpoint when rediscovery fails."""

    @pytest.fixture(autouse=True)
    def _override_auth(self, client):
        payload = _make_token_payload()
        client.app.dependency_overrides[get_internal_token] = lambda: payload
        yield
        client.app.dependency_overrides.pop(get_internal_token, None)

    @pytest.mark.asyncio
    async def test_announce_rediscovery_exception_returns_503(self, client):
        """If rediscover_module raises, announce should return 503."""
        mock_registry = MagicMock()
        mock_registry.get_module_hash.return_value = _HASH_A
        mock_registry.rediscover_module = AsyncMock(side_effect=Exception("fetch failed"))

        with patch("app.api.endpoints.internal.get_module_registry", return_value=mock_registry):
            response = await client.post(
                "/api/internal/registry/announce/screen",
                json={"openapi_hash": _HASH_B},
            )

        assert response.status_code == 503


# ── Screen announce function tests ──
# NOTE: _announce_to_gateway() tests live in apps/screen/tests/ because
# they need screen's import context. See apps/screen/tests/test_announce.py
