"""Tests for the ModuleRegistry.

Covers:
- Module discovery from OpenAPI schema (mocked HTTP)
- Route resolution (exact match, parameterized paths)
- Operation metadata extraction (x-permissions, x-token-cost, x-public)
- Graceful handling of unavailable backends
- Overlap resolution by module priority
- Rediscovery of a single module
- ModuleName enum sync between proxy and DB layers
- Stale check with missing module discovery (TTL-gated)
"""

import time
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.proxy.registry import (
    ModuleDefinition,
    ModuleName,
    ModuleRegistry,
    RouteOperation,
    _matches_on_segment_boundary,
    _normalize_path,
)

# Minimal OpenAPI schema for testing
SCREEN_SCHEMA = {
    "openapi": "3.0.0",
    "info": {"title": "Screen", "version": "0.1.0"},
    "paths": {
        "/api/companies": {
            "get": {
                "summary": "List companies",
                "x-permissions": ["company.view"],
            },
            "post": {
                "summary": "Create company",
                "x-permissions": ["company.create"],
                "x-token-cost": 1,
            },
        },
        "/api/companies/{company_id}": {
            "get": {
                "summary": "Get company",
                "x-permissions": ["company.view"],
            },
        },
        "/api/auth/login": {
            "post": {
                "summary": "Login",
                "x-public": True,
            },
        },
    },
}

TARGET_SCHEMA = {
    "openapi": "3.0.0",
    "info": {"title": "Target", "version": "0.1.0"},
    "paths": {
        "/api/watchfiles": {
            "get": {
                "summary": "List watchfiles",
                "x-permissions": ["target.view"],
            },
        },
        "/api/companies": {
            "get": {
                "summary": "List companies (target)",
                "x-permissions": ["target.view"],
            },
        },
    },
}


def _mock_response(schema: dict) -> MagicMock:
    """Create a mock httpx response returning the given schema."""
    resp = MagicMock()
    resp.status_code = 200
    resp.json.return_value = schema
    resp.raise_for_status = MagicMock()
    return resp


class TestModuleDiscovery:
    """Test schema fetching and route extraction."""

    @pytest.mark.asyncio
    async def test_discover_single_module(self):
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCREEN_SCHEMA))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

        assert ModuleName.SCREEN in registry.modules
        module = registry.modules[ModuleName.SCREEN]
        assert len(module.routes) == 3
        assert "/api/companies" in module.routes
        assert "/api/auth/login" in module.routes

    @pytest.mark.asyncio
    async def test_discover_skips_unavailable_backend(self):
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(side_effect=Exception("Connection refused"))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

        assert len(registry.modules) == 0

    @pytest.mark.asyncio
    async def test_discover_skips_unknown_module_name(self):
        registry = ModuleRegistry(backends={"unknown_module": "http://unknown:8000"})

        await registry.discover_all()

        assert len(registry.modules) == 0


class TestRouteExtraction:
    """Test extraction of x-* metadata from OpenAPI operations."""

    @pytest.mark.asyncio
    async def test_extracts_permissions(self):
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCREEN_SCHEMA))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

        op = registry.resolve_operation(ModuleName.SCREEN, "GET", "/api/companies")
        assert op is not None
        assert op.permissions == ["company.view"]

    @pytest.mark.asyncio
    async def test_extracts_token_cost(self):
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCREEN_SCHEMA))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

        op = registry.resolve_operation(ModuleName.SCREEN, "POST", "/api/companies")
        assert op is not None
        assert op.token_cost == 1

    @pytest.mark.asyncio
    async def test_extracts_public_flag(self):
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCREEN_SCHEMA))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

        op = registry.resolve_operation(ModuleName.SCREEN, "POST", "/api/auth/login")
        assert op is not None
        assert op.is_public is True

    @pytest.mark.asyncio
    async def test_defaults_to_non_public_zero_cost(self):
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(SCREEN_SCHEMA))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

        op = registry.resolve_operation(ModuleName.SCREEN, "GET", "/api/companies")
        assert op is not None
        assert op.is_public is False
        assert op.token_cost == 0


class TestRouteResolution:
    """Test resolve() path matching."""

    @pytest.fixture
    def registry_with_screen(self):
        """Create a registry with pre-loaded screen routes (no HTTP)."""
        registry = ModuleRegistry(backends={})
        module = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/companies": {
                    "GET": RouteOperation(method="GET", path="/api/companies", permissions=["company.view"]),
                    "POST": RouteOperation(
                        method="POST", path="/api/companies", permissions=["company.create"], token_cost=1
                    ),
                },
                "/api/companies/{company_id}": {
                    "GET": RouteOperation(
                        method="GET", path="/api/companies/{company_id}", permissions=["company.view"]
                    ),
                },
                "/api/auth/login": {
                    "POST": RouteOperation(method="POST", path="/api/auth/login", is_public=True),
                },
            },
        )
        registry._modules[ModuleName.SCREEN] = module
        registry._build_route_index()
        return registry

    def test_resolve_exact_match(self, registry_with_screen):
        result = registry_with_screen.resolve("companies", "GET")
        assert result is not None
        module, path = result
        assert module.name == ModuleName.SCREEN
        assert path == "/api/companies"

    def test_resolve_parameterized_path(self, registry_with_screen):
        result = registry_with_screen.resolve("companies/123", "GET")
        assert result is not None
        module, path = result
        assert module.name == ModuleName.SCREEN
        assert path == "/api/companies/123"

    def test_resolve_unknown_path_returns_none(self, registry_with_screen):
        result = registry_with_screen.resolve("unknown/path", "GET")
        assert result is None

    def test_resolve_wrong_method_returns_none(self, registry_with_screen):
        result = registry_with_screen.resolve("auth/login", "GET")
        assert result is None

    def test_resolve_public_endpoint(self, registry_with_screen):
        result = registry_with_screen.resolve("auth/login", "POST")
        assert result is not None
        module, path = result
        assert module.name == ModuleName.SCREEN


class TestResolveOperationSiblingRoutes:
    """Regression: sibling parameterised sub-routes must not shadow each other.

    Before the template-match rewrite, `resolve_operation` compared only the
    static prefix before the first `{…}`. Sibling routes like
    `/api/companies/{id}/restore` and `/api/companies/{id}/refresh` therefore
    shared the same prefix (`/api/companies/`) and the first registered one
    swallowed its neighbours — returning its x-permissions (e.g. `company.delete`)
    for every sibling's method+path, causing unexpected 403s even for admins.
    """

    @pytest.fixture
    def registry_with_siblings(self):
        """Register two POST siblings whose static prefix is identical."""
        registry = ModuleRegistry(backends={})
        module = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            # /restore is declared first so under the old prefix-matching logic
            # it would swallow /refresh.
            routes={
                "/api/companies/{company_id}": {
                    "DELETE": RouteOperation(
                        method="DELETE",
                        path="/api/companies/{company_id}",
                        permissions=["company.delete"],
                    ),
                },
                "/api/companies/{company_id}/restore": {
                    "POST": RouteOperation(
                        method="POST",
                        path="/api/companies/{company_id}/restore",
                        permissions=["company.delete"],
                    ),
                },
                "/api/companies/{company_id}/refresh": {
                    "POST": RouteOperation(
                        method="POST",
                        path="/api/companies/{company_id}/refresh",
                        permissions=["company.create"],
                    ),
                },
            },
        )
        registry._modules[ModuleName.SCREEN] = module
        registry._build_route_index()
        return registry

    def test_refresh_resolves_to_own_permissions_not_restore(self, registry_with_siblings):
        op = registry_with_siblings.resolve_operation(ModuleName.SCREEN, "POST", "/api/companies/1/refresh")
        assert op is not None
        assert op.permissions == ["company.create"]

    def test_restore_resolves_to_own_permissions(self, registry_with_siblings):
        op = registry_with_siblings.resolve_operation(ModuleName.SCREEN, "POST", "/api/companies/1/restore")
        assert op is not None
        assert op.permissions == ["company.delete"]

    def test_delete_on_bare_resource_not_shadowed_by_siblings(self, registry_with_siblings):
        op = registry_with_siblings.resolve_operation(ModuleName.SCREEN, "DELETE", "/api/companies/1")
        assert op is not None
        assert op.permissions == ["company.delete"]

    def test_unknown_sibling_returns_none(self, registry_with_siblings):
        op = registry_with_siblings.resolve_operation(ModuleName.SCREEN, "POST", "/api/companies/1/unknown")
        assert op is None


class TestOverlapResolution:
    """Test that higher-priority modules win on path conflicts."""

    def test_screen_wins_over_target(self):
        registry = ModuleRegistry(backends={})

        screen = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/companies": {
                    "GET": RouteOperation(method="GET", path="/api/companies"),
                },
            },
        )
        target = ModuleDefinition(
            name=ModuleName.TARGET,
            backend_url="http://target:8000",
            routes={
                "/api/companies": {
                    "GET": RouteOperation(method="GET", path="/api/companies"),
                },
            },
        )

        registry._modules[ModuleName.SCREEN] = screen
        registry._modules[ModuleName.TARGET] = target
        registry._build_route_index()

        result = registry.resolve("companies", "GET")
        assert result is not None
        module, _ = result
        assert module.name == ModuleName.SCREEN

    def test_non_overlapping_routes_both_resolve(self):
        registry = ModuleRegistry(backends={})

        screen = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/companies": {
                    "GET": RouteOperation(method="GET", path="/api/companies"),
                },
            },
        )
        target = ModuleDefinition(
            name=ModuleName.TARGET,
            backend_url="http://target:8000",
            routes={
                "/api/watchfiles": {
                    "GET": RouteOperation(method="GET", path="/api/watchfiles"),
                },
            },
        )

        registry._modules[ModuleName.SCREEN] = screen
        registry._modules[ModuleName.TARGET] = target
        registry._build_route_index()

        result_companies = registry.resolve("companies", "GET")
        result_watchfiles = registry.resolve("watchfiles", "GET")
        assert result_companies is not None
        assert result_companies[0].name == ModuleName.SCREEN
        assert result_watchfiles is not None
        assert result_watchfiles[0].name == ModuleName.TARGET


class TestRediscovery:
    """Test re-fetching a single module's schema."""

    @pytest.mark.asyncio
    async def test_rediscover_updates_routes(self):
        registry = ModuleRegistry(backends={})

        # Initial state: module with one route
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/companies": {
                    "GET": RouteOperation(method="GET", path="/api/companies"),
                },
            },
        )
        registry._build_route_index()
        assert registry.resolve("tasks", "GET") is None

        # Rediscover with updated schema that has /api/tasks
        updated_schema = {
            "openapi": "3.0.0",
            "paths": {
                "/api/companies": {"get": {}},
                "/api/tasks": {"get": {"x-permissions": ["company.view"]}},
            },
        }

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(updated_schema))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.rediscover_module(ModuleName.SCREEN)

        # New route should now resolve
        result = registry.resolve("tasks", "GET")
        assert result is not None
        assert result[0].name == ModuleName.SCREEN

    @pytest.mark.asyncio
    async def test_rediscover_unknown_module_not_in_backends_is_noop(self):
        """Module not in _modules AND not in _backends → silent noop."""
        registry = ModuleRegistry(backends={})
        await registry.rediscover_module(ModuleName.TARGET)
        assert ModuleName.TARGET not in registry.modules

    @pytest.mark.asyncio
    async def test_late_discovery_for_module_unavailable_at_startup(self):
        """Module in _backends but not _modules (was offline at startup) → discover on announce."""
        target_schema = {
            "openapi": "3.0.0",
            "paths": {
                "/api/watch_files": {"get": {"x-permissions": ["organization.read"]}},
            },
        }

        registry = ModuleRegistry(backends={"target": "http://target:8000"})
        # Simulate startup failure: target is in _backends but not _modules
        assert ModuleName.TARGET not in registry.modules

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(target_schema))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.rediscover_module(ModuleName.TARGET)

        # Module should now be discovered
        assert ModuleName.TARGET in registry.modules
        result = registry.resolve("watch_files", "GET")
        assert result is not None
        assert result[0].name == ModuleName.TARGET

    @pytest.mark.asyncio
    async def test_late_discovery_fetch_failure_does_not_crash(self):
        """Module in _backends but HTTP fetch fails → module stays undiscovered."""
        registry = ModuleRegistry(backends={"target": "http://target:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(side_effect=Exception("Connection refused"))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.rediscover_module(ModuleName.TARGET)

        assert ModuleName.TARGET not in registry.modules


class TestOpenApiConfig:
    """Test per-module OpenAPI discovery configuration."""

    @pytest.mark.asyncio
    async def test_target_uses_api_platform_openapi_path(self):
        """Target module should fetch /api/docs with Accept header instead of /openapi.json."""
        target_schema = {
            "openapi": "3.0.0",
            "paths": {"/api/watch_files": {"get": {}}},
        }

        registry = ModuleRegistry(backends={"target": "http://target:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(target_schema))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

            call_args = mock_client.get.call_args
            url = call_args.args[0] if call_args.args else call_args.kwargs.get("url", "")
            headers = call_args.kwargs.get("headers", {})
            assert url == "http://target:8000/api/docs"
            assert headers.get("Accept") == "application/vnd.openapi+json"

    @pytest.mark.asyncio
    async def test_screen_uses_default_openapi_path(self):
        """Screen module should use default /openapi.json path."""
        screen_schema = {
            "openapi": "3.0.0",
            "paths": {"/api/companies": {"get": {}}},
        }

        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(screen_schema))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

            call_args = mock_client.get.call_args
            url = call_args.args[0] if call_args.args else call_args.kwargs.get("url", "")
            headers = call_args.kwargs.get("headers", {})
            assert url == "http://screen:8000/openapi.json"
            assert headers == {}


class TestNormalizePath:
    """Test path normalization."""

    def test_strips_trailing_slash(self):
        assert _normalize_path("/api/companies/") == "/api/companies"

    def test_preserves_path_without_trailing_slash(self):
        assert _normalize_path("/api/companies") == "/api/companies"

    def test_root_path_stays_slash(self):
        assert _normalize_path("/") == "/"

    def test_double_trailing_slash(self):
        assert _normalize_path("/api/companies//") == "/api/companies"


class TestSegmentBoundary:
    """Test _matches_on_segment_boundary to prevent false prefix matches."""

    def test_exact_match(self):
        assert _matches_on_segment_boundary("/api/companies", "/api/companies") is True

    def test_match_with_subpath(self):
        assert _matches_on_segment_boundary("/api/companies/123", "/api/companies/") is True

    def test_match_prefix_without_trailing_slash(self):
        assert _matches_on_segment_boundary("/api/companies/123", "/api/companies") is True

    def test_rejects_partial_segment_match(self):
        """'/api/companies-old' must NOT match prefix '/api/companies'."""
        assert _matches_on_segment_boundary("/api/companies-old", "/api/companies") is False

    def test_rejects_partial_segment_match_suffix(self):
        assert _matches_on_segment_boundary("/api/companiesXYZ", "/api/companies") is False

    def test_no_match_at_all(self):
        assert _matches_on_segment_boundary("/api/users", "/api/companies") is False


class TestEdgeCases:
    """Edge cases: trailing slashes, mixed case methods, empty schemas, deep params."""

    @pytest.fixture
    def registry_with_deep_routes(self):
        """Registry with multi-level parameterized paths."""
        registry = ModuleRegistry(backends={})
        module = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/companies": {
                    "GET": RouteOperation(method="GET", path="/api/companies"),
                },
                "/api/companies/{company_id}": {
                    "GET": RouteOperation(method="GET", path="/api/companies/{company_id}"),
                },
                "/api/companies/{company_id}/tasks": {
                    "GET": RouteOperation(method="GET", path="/api/companies/{company_id}/tasks"),
                },
                "/api/companies/{company_id}/tasks/{task_id}": {
                    "GET": RouteOperation(method="GET", path="/api/companies/{company_id}/tasks/{task_id}"),
                },
            },
        )
        registry._modules[ModuleName.SCREEN] = module
        registry._build_route_index()
        return registry

    def test_trailing_slash_resolves(self, registry_with_deep_routes):
        """Path with trailing slash should match the same route."""
        result = registry_with_deep_routes.resolve("companies/", "GET")
        assert result is not None
        module, _ = result
        assert module.name == ModuleName.SCREEN

    def test_method_case_insensitive(self, registry_with_deep_routes):
        """Methods like 'get', 'Get', 'GET' should all resolve."""
        for method in ("get", "Get", "GET"):
            result = registry_with_deep_routes.resolve("companies", method)
            assert result is not None, f"Failed for method: {method}"

    def test_deep_parameterized_path(self, registry_with_deep_routes):
        """/api/companies/42/tasks/99 should match the deepest template."""
        result = registry_with_deep_routes.resolve("companies/42/tasks/99", "GET")
        assert result is not None
        module, path = result
        assert module.name == ModuleName.SCREEN
        assert path == "/api/companies/42/tasks/99"

    def test_intermediate_parameterized_path(self, registry_with_deep_routes):
        """/api/companies/42/tasks should match the tasks collection."""
        result = registry_with_deep_routes.resolve("companies/42/tasks", "GET")
        assert result is not None

    def test_partial_segment_no_false_positive(self, registry_with_deep_routes):
        """/api/companies-archived should NOT match /api/companies/{id}."""
        result = registry_with_deep_routes.resolve("companies-archived", "GET")
        assert result is None

    @pytest.mark.asyncio
    async def test_empty_schema_paths(self):
        """Backend returning empty paths should not crash."""
        empty_schema = {"openapi": "3.0.0", "paths": {}}
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(empty_schema))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

        assert ModuleName.SCREEN in registry.modules
        assert len(registry.modules[ModuleName.SCREEN].routes) == 0

    @pytest.mark.asyncio
    async def test_schema_without_paths_key(self):
        """Backend returning schema without 'paths' key should not crash."""
        no_paths_schema = {"openapi": "3.0.0", "info": {"title": "Test"}}
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
    async def test_lowercase_methods_in_schema(self):
        """OpenAPI methods are lowercase ('get', 'post') — should be normalized."""
        schema = {
            "openapi": "3.0.0",
            "paths": {
                "/api/items": {
                    "get": {"x-permissions": ["item.view"]},
                    "post": {"x-token-cost": 2},
                },
            },
        }
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(schema))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

        op_get = registry.resolve_operation(ModuleName.SCREEN, "GET", "/api/items")
        op_post = registry.resolve_operation(ModuleName.SCREEN, "POST", "/api/items")
        assert op_get is not None
        assert op_get.permissions == ["item.view"]
        assert op_post is not None
        assert op_post.token_cost == 2

    @pytest.mark.asyncio
    async def test_non_http_methods_in_schema_ignored(self):
        """OpenAPI 'parameters', 'summary' etc. at path level should be ignored."""
        schema = {
            "openapi": "3.0.0",
            "paths": {
                "/api/items": {
                    "get": {"summary": "List"},
                    "parameters": [{"name": "id", "in": "path"}],  # Not a method
                    "summary": "Item operations",  # Not a method
                },
            },
        }
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(schema))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await registry.discover_all()

        module = registry.modules[ModuleName.SCREEN]
        # Only GET should be registered, not 'parameters' or 'summary'
        assert list(module.routes["/api/items"].keys()) == ["GET"]


class TestCheckAndRediscoverStale:
    """Test the healthcheck-driven stale check and missing module discovery."""

    @pytest.mark.asyncio
    async def test_ttl_skips_when_called_too_soon(self):
        """Within TTL window, all modules return 'skipped'."""
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            openapi_hash="abc123",
        )
        # Simulate a recent check
        registry._last_stale_check = time.monotonic()

        results = await registry.check_and_rediscover_stale()

        assert results == {"screen": "skipped"}

    @pytest.mark.asyncio
    async def test_missing_module_discovered_after_ttl(self):
        """Module that failed at startup is discovered on the next TTL-gated check."""
        target_schema = {
            "openapi": "3.0.0",
            "paths": {"/api/watch_files": {"get": {}}},
        }
        registry = ModuleRegistry(
            backends={
                "screen": "http://screen:8000",
                "target": "http://target:8000",
            }
        )
        # Screen is discovered, target is not
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            openapi_hash="abc123",
        )
        # Expire TTL
        registry._last_stale_check = 0

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()

            # rediscover_module for target → success
            # check_and_rediscover_stale for screen → unchanged
            async def mock_get(url, **kwargs):
                resp = _mock_response(target_schema)
                if "screen" in url:
                    resp.json.return_value = {"openapi": "3.0.0", "paths": {}}
                return resp

            mock_client.get = AsyncMock(side_effect=mock_get)
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            results = await registry.check_and_rediscover_stale()

        assert results["target"] == "discovered"
        assert ModuleName.TARGET in registry.modules
        result = registry.resolve("watch_files", "GET")
        assert result is not None
        assert result[0].name == ModuleName.TARGET

    @pytest.mark.asyncio
    async def test_missing_module_still_unreachable(self):
        """Module that failed at startup stays missing if backend is still down."""
        registry = ModuleRegistry(backends={"target": "http://target:8000"})
        registry._last_stale_check = 0

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(side_effect=Exception("Connection refused"))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            results = await registry.check_and_rediscover_stale()

        assert results["target"] == "error"
        assert ModuleName.TARGET not in registry.modules

    @pytest.mark.asyncio
    async def test_ttl_prevents_retry_spam(self):
        """Missing module is NOT retried within the TTL window."""
        registry = ModuleRegistry(backends={"target": "http://target:8000"})
        # Simulate a check that just happened
        registry._last_stale_check = time.monotonic()

        # No HTTP mock needed — should return before any network call
        results = await registry.check_and_rediscover_stale()

        # Target is not in _modules, so it doesn't appear in "skipped" either
        assert results == {}

    @pytest.mark.asyncio
    async def test_already_discovered_not_refetched_in_missing_loop(self):
        """Modules already in _modules are skipped by the missing-module loop."""
        schema = {"openapi": "3.0.0", "paths": {"/api/companies": {"get": {}}}}
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            openapi_hash=ModuleRegistry._compute_schema_hash(schema),
        )
        registry._last_stale_check = 0

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(schema))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            results = await registry.check_and_rediscover_stale()

        # Screen should be "unchanged" (stale check), not "discovered"
        assert results["screen"] == "unchanged"

    @pytest.mark.asyncio
    async def test_schema_change_triggers_rediscovery(self):
        """When a discovered module's schema hash changes, it is rediscovered."""
        old_schema = {"openapi": "3.0.0", "paths": {"/api/companies": {"get": {}}}}
        new_schema = {
            "openapi": "3.0.0",
            "paths": {
                "/api/companies": {"get": {}},
                "/api/tasks": {"get": {}},
            },
        }
        registry = ModuleRegistry(backends={"screen": "http://screen:8000"})
        registry._modules[ModuleName.SCREEN] = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            openapi_hash=ModuleRegistry._compute_schema_hash(old_schema),
            routes={"/api/companies": {"GET": RouteOperation(method="GET", path="/api/companies")}},
        )
        registry._build_route_index()
        registry._last_stale_check = 0

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.get = AsyncMock(return_value=_mock_response(new_schema))
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            results = await registry.check_and_rediscover_stale()

        assert results["screen"] == "rediscovered"
        assert registry.resolve("tasks", "GET") is not None

    @pytest.mark.asyncio
    async def test_mixed_discovered_and_missing(self):
        """One module discovered + one missing: both handled correctly."""
        screen_schema = {"openapi": "3.0.0", "paths": {"/api/companies": {"get": {}}}}
        target_schema = {"openapi": "3.0.0", "paths": {"/api/watch_files": {"get": {}}}}

        registry = ModuleRegistry(
            backends={
                "screen": "http://screen:8000",
                "target": "http://target:8000",
            }
        )
        screen_module = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            openapi_hash=ModuleRegistry._compute_schema_hash(screen_schema),
            routes={"/api/companies": {"GET": RouteOperation(method="GET", path="/api/companies")}},
        )
        registry._modules[ModuleName.SCREEN] = screen_module
        registry._build_route_index()
        registry._last_stale_check = 0

        with patch("app.proxy.registry.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()

            async def mock_get(url, **kwargs):
                if "target" in url:
                    return _mock_response(target_schema)
                return _mock_response(screen_schema)

            mock_client.get = AsyncMock(side_effect=mock_get)
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            results = await registry.check_and_rediscover_stale()

        assert results["screen"] == "unchanged"
        assert results["target"] == "discovered"
        assert registry.resolve("watch_files", "GET") is not None
        assert registry.resolve("companies", "GET") is not None

    @pytest.mark.asyncio
    async def test_unknown_module_name_in_backends_skipped(self):
        """Invalid module name in _backends doesn't crash the stale check."""
        registry = ModuleRegistry(backends={"invalid_module": "http://invalid:8000"})
        registry._last_stale_check = 0

        results = await registry.check_and_rediscover_stale()

        assert "invalid_module" not in results


class TestModuleNameSync:
    """Verify proxy ModuleName stays in sync with DB ModuleName."""

    def test_module_name_sync_with_db_enum(self):
        """Proxy ModuleName values must match models.organization.ModuleName."""
        from app.models.organization import ModuleName as DbModuleName
        from app.proxy.registry import ModuleName as ProxyModuleName

        proxy_values = {m.value for m in ProxyModuleName}
        db_values = {m.value for m in DbModuleName}

        assert proxy_values == db_values, (
            f"ModuleName enums are out of sync.\n"
            f"  Proxy only: {proxy_values - db_values}\n"
            f"  DB only:    {db_values - proxy_values}"
        )
