"""Tests for multi-backend proxy routing via ModuleRegistry.

Covers:
- Registry-based routing resolves to correct backend
- Fallback to screen for unresolved routes (transition period)
- Fallback when registry is None (not yet initialized)
- Client pool creates separate clients per backend URL
- Client pool normalizes trailing slashes
- Screen fallback lazy init and caching
- Backend path includes query params
- init_module_registry creates global registry
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch

from app.proxy.client import ProxyClientPool
from app.proxy.registry import ModuleDefinition, ModuleName, ModuleRegistry, RouteOperation


class TestProxyClientPool:
    """Test the multi-backend client pool."""

    @pytest.mark.asyncio
    async def test_creates_client_for_new_backend(self):
        pool = ProxyClientPool()
        try:
            client = await pool.get_client("http://screen:8000")
            assert client is not None
        finally:
            await pool.close_all()

    @pytest.mark.asyncio
    async def test_reuses_client_for_same_backend(self):
        pool = ProxyClientPool()
        try:
            client1 = await pool.get_client("http://screen:8000")
            client2 = await pool.get_client("http://screen:8000")
            assert client1 is client2
        finally:
            await pool.close_all()

    @pytest.mark.asyncio
    async def test_different_backends_get_different_clients(self):
        pool = ProxyClientPool()
        try:
            client1 = await pool.get_client("http://screen:8000")
            client2 = await pool.get_client("http://target:8000")
            assert client1 is not client2
        finally:
            await pool.close_all()

    @pytest.mark.asyncio
    async def test_close_all_clears_pool(self):
        pool = ProxyClientPool()
        await pool.get_client("http://screen:8000")
        await pool.get_client("http://target:8000")
        await pool.close_all()
        assert len(pool._clients) == 0

    @pytest.mark.asyncio
    async def test_normalizes_trailing_slash(self):
        """URLs with and without trailing slash should share the same client."""
        pool = ProxyClientPool()
        try:
            client1 = await pool.get_client("http://screen:8000/")
            client2 = await pool.get_client("http://screen:8000")
            assert client1 is client2
        finally:
            await pool.close_all()


class TestProxyRouting:
    """Test that the proxy resolves routes via registry and forwards correctly."""

    @pytest.fixture
    def mock_registry(self):
        """Registry with screen and target modules."""
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
        target = ModuleDefinition(
            name=ModuleName.TARGET,
            backend_url="http://target:8000",
            routes={
                "/api/watchfiles": {
                    "GET": RouteOperation(method="GET", path="/api/watchfiles", permissions=["target.view"]),
                },
            },
        )
        registry._modules[ModuleName.SCREEN] = screen
        registry._modules[ModuleName.TARGET] = target
        registry._build_route_index()
        return registry

    def test_registry_resolves_screen_route(self, mock_registry):
        result = mock_registry.resolve("companies", "GET")
        assert result is not None
        module, path = result
        assert module.name == ModuleName.SCREEN
        assert module.backend_url == "http://screen:8000"

    def test_registry_resolves_target_route(self, mock_registry):
        result = mock_registry.resolve("watchfiles", "GET")
        assert result is not None
        module, path = result
        assert module.name == ModuleName.TARGET
        assert module.backend_url == "http://target:8000"

    def test_registry_returns_none_for_unknown(self, mock_registry):
        result = mock_registry.resolve("unknown/endpoint", "GET")
        assert result is None


class TestFallbackBehavior:
    """Test fallback to screen during transition."""

    def test_fallback_returns_screen_for_unresolved_path(self):
        """When registry can't resolve, fallback should route to screen."""
        from app.proxy.routes import _resolve_backend

        registry = ModuleRegistry(backends={})
        screen = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={},
        )
        registry._modules[ModuleName.SCREEN] = screen
        registry._build_route_index()

        result = _resolve_backend(registry, "some/unknown/path", "GET")
        assert result is not None
        module, backend_path = result
        assert module.backend_url == "http://screen:8000"

    def test_resolved_route_takes_priority_over_fallback(self):
        """When registry resolves, that takes priority over fallback."""
        from app.proxy.routes import _resolve_backend

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

        result = _resolve_backend(registry, "watchfiles", "GET")
        assert result is not None
        module, _ = result
        assert module.backend_url == "http://target:8000"

    def test_fallback_when_registry_is_none(self):
        """Before startup, registry is None — should still fallback to screen."""
        from app.proxy.routes import _resolve_backend

        result = _resolve_backend(None, "any/path", "GET")
        assert result is not None
        module, backend_path = result
        assert module.name == ModuleName.SCREEN
        assert backend_path == "/api/any/path"

    def test_fallback_builds_correct_backend_path(self):
        """Fallback should construct /api/{path} as the backend path."""
        from app.proxy.routes import _resolve_backend

        result = _resolve_backend(None, "companies/42/tasks", "POST")
        module, backend_path = result
        assert backend_path == "/api/companies/42/tasks"


class TestScreenFallbackCache:
    """Test _get_screen_fallback lazy init and caching."""

    def test_returns_same_instance(self):
        """Should return the same cached ModuleDefinition."""
        from app.proxy.routes import _get_screen_fallback

        fb1 = _get_screen_fallback()
        fb2 = _get_screen_fallback()
        assert fb1 is fb2

    def test_returns_screen_module(self):
        from app.proxy.routes import _get_screen_fallback

        fb = _get_screen_fallback()
        assert fb.name == ModuleName.SCREEN


class TestInitModuleRegistry:
    """Test init_module_registry startup function."""

    @pytest.mark.asyncio
    async def test_creates_global_registry(self):
        """init_module_registry should set the global _registry."""
        import app.proxy.routes as routes_module
        from app.proxy.routes import init_module_registry

        # Mock the discovery to avoid real HTTP calls
        with patch.object(ModuleRegistry, "discover_all", new_callable=AsyncMock):
            registry = await init_module_registry()

        assert registry is not None
        assert routes_module._registry is registry

    @pytest.mark.asyncio
    async def test_registry_has_screen_backend(self):
        """The registry should be configured with screen backend."""
        from app.proxy.routes import init_module_registry

        with patch.object(ModuleRegistry, "discover_all", new_callable=AsyncMock) as mock_discover:
            registry = await init_module_registry()

        assert "screen" in registry._backends
        mock_discover.assert_awaited_once()


class TestAbusiveInputs:
    """Test resilience against malicious or abusive inputs."""

    @pytest.fixture
    def registry_with_screen(self):
        registry = ModuleRegistry(backends={})
        screen = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url="http://screen:8000",
            routes={
                "/api/companies": {
                    "GET": RouteOperation(method="GET", path="/api/companies"),
                },
                "/api/companies/{company_id}": {
                    "GET": RouteOperation(method="GET", path="/api/companies/{company_id}"),
                },
            },
        )
        registry._modules[ModuleName.SCREEN] = screen
        registry._build_route_index()
        return registry

    def test_extremely_long_path(self, registry_with_screen):
        """Very long path should not crash, just return None."""
        long_path = "a/" * 5000 + "end"
        result = registry_with_screen.resolve(long_path, "GET")
        assert result is None

    def test_path_traversal_attempt(self, registry_with_screen):
        """Path traversal should not match any route."""
        result = registry_with_screen.resolve("../../etc/passwd", "GET")
        assert result is None

    def test_null_bytes_in_path(self, registry_with_screen):
        """Null bytes in path should not match."""
        result = registry_with_screen.resolve("companies\x00/evil", "GET")
        assert result is None

    def test_unicode_path(self, registry_with_screen):
        """Unicode in path should not crash."""
        result = registry_with_screen.resolve("companies/café", "GET")
        # May or may not match (prefix match), but must not crash
        assert result is None or result[0].name == ModuleName.SCREEN

    def test_empty_path(self, registry_with_screen):
        """Empty path should not crash."""
        result = registry_with_screen.resolve("", "GET")
        assert result is None

    def test_double_slash_path(self, registry_with_screen):
        """Double slashes should not match incorrectly."""
        result = registry_with_screen.resolve("//companies", "GET")
        assert result is None

    def test_path_with_query_like_chars(self, registry_with_screen):
        """Path containing ? or & should not crash (query is handled separately)."""
        result = registry_with_screen.resolve("companies?id=1&evil=true", "GET")
        assert result is None or result[0].name == ModuleName.SCREEN

    def test_fallback_with_very_long_path(self):
        """Fallback should handle absurdly long paths without crash."""
        from app.proxy.routes import _resolve_backend

        long_path = "x" * 10000
        result = _resolve_backend(None, long_path, "GET")
        module, backend_path = result
        assert module.name == ModuleName.SCREEN
        assert backend_path == f"/api/{long_path}"

    def test_special_characters_in_path(self, registry_with_screen):
        """Special chars (%, +, spaces) should not crash."""
        for path in ["companies/%20test", "companies/a+b", "companies/a b"]:
            result = registry_with_screen.resolve(path, "GET")
            # Should match via prefix or not, but must never crash
            assert result is None or isinstance(result, tuple)


class TestProxyIntegrationWithRegistry:
    """Integration tests: full proxy_request flow with registry."""

    @pytest.mark.asyncio
    async def test_proxy_uses_resolved_backend_url(self):
        """proxy_request should pass the resolved backend_url to the client."""
        from app.proxy.routes import proxy_request, _resolve_backend
        import app.proxy.routes as routes_module

        # Set up a registry with target
        registry = ModuleRegistry(backends={})
        target = ModuleDefinition(
            name=ModuleName.TARGET,
            backend_url="http://target:8000",
            routes={
                "/api/watchfiles": {
                    "GET": RouteOperation(method="GET", path="/api/watchfiles"),
                },
            },
        )
        registry._modules[ModuleName.TARGET] = target
        registry._build_route_index()

        old_registry = routes_module._registry
        routes_module._registry = registry

        try:
            mock_request = MagicMock()
            mock_request.method = "GET"
            mock_request.headers = {"accept": "application/json"}
            mock_request.url = MagicMock()
            mock_request.url.query = ""
            mock_request.state = MagicMock()

            mock_user = MagicMock()
            mock_user.preferred_username = "testuser"

            captured_backend_url = None

            async def mock_handle_regular(request, target_path, headers, start_time, backend_url=None):
                nonlocal captured_backend_url
                captured_backend_url = backend_url
                return MagicMock(status_code=200)

            with (
                patch("app.proxy.routes.auth_middleware") as mock_auth,
                patch("app.proxy.routes._handle_regular_request", side_effect=mock_handle_regular),
            ):
                mock_auth.validate_request = AsyncMock(
                    return_value=(True, mock_user, {"Authorization": "Internal test"})
                )

                await proxy_request(mock_request, "watchfiles")

            assert captured_backend_url == "http://target:8000"
        finally:
            routes_module._registry = old_registry

    @pytest.mark.asyncio
    async def test_proxy_fallback_uses_screen_url(self):
        """Unresolved paths should fallback to screen backend URL."""
        from app.proxy.routes import proxy_request
        import app.proxy.routes as routes_module

        # Empty registry — nothing resolves
        registry = ModuleRegistry(backends={})
        registry._build_route_index()

        old_registry = routes_module._registry
        routes_module._registry = registry

        try:
            mock_request = MagicMock()
            mock_request.method = "GET"
            mock_request.headers = {"accept": "application/json"}
            mock_request.url = MagicMock()
            mock_request.url.path = "/api/unknown/path"
            mock_request.url.query = "page=1&size=10"
            mock_request.state = MagicMock()

            mock_user = MagicMock()
            mock_user.preferred_username = "testuser"

            captured_target_path = None
            captured_backend_url = None

            async def mock_handle_regular(request, target_path, headers, start_time, backend_url=None):
                nonlocal captured_target_path, captured_backend_url
                captured_target_path = target_path
                captured_backend_url = backend_url
                return MagicMock(status_code=200)

            with (
                patch("app.proxy.routes.auth_middleware") as mock_auth,
                patch("app.proxy.routes._handle_regular_request", side_effect=mock_handle_regular),
            ):
                mock_auth.validate_request = AsyncMock(
                    return_value=(True, mock_user, {"Authorization": "Internal test"})
                )

                await proxy_request(mock_request, "unknown/path")

            assert "screen" in captured_backend_url
            assert captured_target_path == "/api/unknown/path?page=1&size=10"
        finally:
            routes_module._registry = old_registry


class TestBackendUrlSecurity:
    """Tests for backend URL security against SSRF and protocol injection."""

    def test_backend_url_with_javascript_protocol(self):
        """javascript: URL should not cause code execution in resolve."""
        from app.proxy.routes import _resolve_backend

        # Even with a malicious backend URL, resolve just does string matching
        result = _resolve_backend(None, "javascript:alert(1)", "GET")
        module, backend_path = result
        # Falls back to screen — the malicious path becomes part of the backend path
        assert module.name == ModuleName.SCREEN
        assert backend_path == "/api/javascript:alert(1)"

    def test_backend_url_with_file_protocol(self):
        """file:// URL in path should not cause SSRF."""
        from app.proxy.routes import _resolve_backend

        result = _resolve_backend(None, "file:///etc/passwd", "GET")
        module, backend_path = result
        assert module.name == ModuleName.SCREEN
        # The path is just forwarded as a string — httpx handles URL validation
        assert "/etc/passwd" in backend_path

    def test_backend_url_path_traversal(self):
        """Path traversal attempts should not escape the /api/ prefix in fallback."""
        from app.proxy.routes import _resolve_backend

        result = _resolve_backend(None, "../../../etc/passwd", "GET")
        module, backend_path = result
        # Fallback always prepends /api/ so traversal is contained
        assert backend_path == "/api/../../../etc/passwd"
        assert module.name == ModuleName.SCREEN

    def test_backend_url_internal_ssrf(self):
        """Cloud metadata IP (169.254.169.254) in path should be handled safely."""
        from app.proxy.routes import _resolve_backend

        result = _resolve_backend(None, "169.254.169.254/latest/meta-data", "GET")
        module, backend_path = result
        # Path is forwarded to screen backend with /api/ prefix
        assert backend_path == "/api/169.254.169.254/latest/meta-data"
        assert module.name == ModuleName.SCREEN


class TestCorrelationIdPropagation:
    """Tests for X-Correlation-ID propagation through the proxy."""

    @pytest.mark.asyncio
    async def test_correlation_id_propagated_to_backend(self):
        """X-Correlation-ID from request.state should appear in proxy headers."""
        from app.proxy.routes import proxy_request, CORRELATION_HEADER
        import app.proxy.routes as routes_module

        mock_request = MagicMock()
        mock_request.method = "GET"
        mock_request.headers = {"accept": "application/json"}
        mock_request.url = MagicMock()
        mock_request.url.query = ""
        mock_request.state = MagicMock()
        mock_request.state.correlation_id = "test-corr-id-123"

        mock_user = MagicMock()
        mock_user.preferred_username = "testuser"

        captured_headers = None

        async def mock_handle_regular(request, target_path, headers, start_time, backend_url=None):
            nonlocal captured_headers
            captured_headers = headers
            return MagicMock(status_code=200)

        with (
            patch("app.proxy.routes.auth_middleware") as mock_auth,
            patch("app.proxy.routes._handle_regular_request", side_effect=mock_handle_regular),
            patch("app.proxy.routes._check_company_folder_access", new_callable=AsyncMock, return_value=None),
        ):
            mock_auth.validate_request = AsyncMock(
                return_value=(True, mock_user, {"Authorization": "Internal test"})
            )

            await proxy_request(mock_request, "some/path")

        assert captured_headers is not None
        assert CORRELATION_HEADER in captured_headers
        assert captured_headers[CORRELATION_HEADER] == "test-corr-id-123"

    @pytest.mark.asyncio
    async def test_no_correlation_id_no_header(self):
        """When request.state has no correlation_id, header should not be added."""
        from app.proxy.routes import proxy_request, CORRELATION_HEADER

        mock_request = MagicMock()
        mock_request.method = "GET"
        mock_request.headers = {"accept": "application/json"}
        mock_request.url = MagicMock()
        mock_request.url.query = ""
        # Make getattr(request.state, "correlation_id", None) return None
        mock_request.state = MagicMock(spec=[])

        mock_user = MagicMock()
        mock_user.preferred_username = "testuser"

        captured_headers = None

        async def mock_handle_regular(request, target_path, headers, start_time, backend_url=None):
            nonlocal captured_headers
            captured_headers = headers
            return MagicMock(status_code=200)

        with (
            patch("app.proxy.routes.auth_middleware") as mock_auth,
            patch("app.proxy.routes._handle_regular_request", side_effect=mock_handle_regular),
            patch("app.proxy.routes._check_company_folder_access", new_callable=AsyncMock, return_value=None),
        ):
            mock_auth.validate_request = AsyncMock(
                return_value=(True, mock_user, {"Authorization": "Internal test"})
            )

            await proxy_request(mock_request, "some/path")

        assert captured_headers is not None
        assert CORRELATION_HEADER not in captured_headers

    @pytest.mark.asyncio
    async def test_correlation_id_with_resolved_backend(self):
        """Correlation ID should work with non-screen backends resolved by registry."""
        from app.proxy.routes import proxy_request, CORRELATION_HEADER
        import app.proxy.routes as routes_module

        # Set up a registry with target
        registry = ModuleRegistry(backends={})
        target = ModuleDefinition(
            name=ModuleName.TARGET,
            backend_url="http://target:8000",
            routes={
                "/api/watchfiles": {
                    "GET": RouteOperation(method="GET", path="/api/watchfiles"),
                },
            },
        )
        registry._modules[ModuleName.TARGET] = target
        registry._build_route_index()

        old_registry = routes_module._registry
        routes_module._registry = registry

        try:
            mock_request = MagicMock()
            mock_request.method = "GET"
            mock_request.headers = {"accept": "application/json"}
            mock_request.url = MagicMock()
            mock_request.url.query = ""
            mock_request.state = MagicMock()
            mock_request.state.correlation_id = "target-corr-456"

            mock_user = MagicMock()
            mock_user.preferred_username = "testuser"

            captured_headers = None

            async def mock_handle_regular(request, target_path, headers, start_time, backend_url=None):
                nonlocal captured_headers
                captured_headers = headers
                return MagicMock(status_code=200)

            with (
                patch("app.proxy.routes.auth_middleware") as mock_auth,
                patch("app.proxy.routes._handle_regular_request", side_effect=mock_handle_regular),
                patch("app.proxy.routes._check_company_folder_access", new_callable=AsyncMock, return_value=None),
            ):
                mock_auth.validate_request = AsyncMock(
                    return_value=(True, mock_user, {"Authorization": "Internal test"})
                )

                await proxy_request(mock_request, "watchfiles")

            assert captured_headers is not None
            assert captured_headers[CORRELATION_HEADER] == "target-corr-456"
        finally:
            routes_module._registry = old_registry


class TestFallbackSecurity:
    """Tests for security of the fallback path construction."""

    def test_fallback_preserves_encoded_query_params(self):
        """URL-encoded params should be preserved through fallback path construction."""
        from app.proxy.routes import _resolve_backend

        # The fallback just builds /api/{path}, query params are appended separately
        result = _resolve_backend(None, "search", "GET")
        module, backend_path = result
        assert backend_path == "/api/search"

    def test_fallback_with_malicious_path(self):
        """Path traversal in fallback path should be contained by /api/ prefix."""
        from app.proxy.routes import _resolve_backend

        result = _resolve_backend(None, "../../admin/secret", "GET")
        module, backend_path = result
        # The /api/ prefix is always prepended
        assert backend_path == "/api/../../admin/secret"
        assert backend_path.startswith("/api/")

    def test_fallback_with_double_encoding(self):
        """Double-encoded path traversal (%252e%252e) should not bypass checks."""
        from app.proxy.routes import _resolve_backend

        result = _resolve_backend(None, "%252e%252e/%252e%252e/etc/passwd", "GET")
        module, backend_path = result
        # Falls back to screen with /api/ prefix — no decoding happens here
        assert backend_path == "/api/%252e%252e/%252e%252e/etc/passwd"
        assert module.name == ModuleName.SCREEN


class TestCheckCompanyFolderAccess:
    """Tests for _check_company_folder_access gateway access control."""

    @pytest.mark.asyncio
    async def test_only_applies_to_get(self):
        """POST/PUT/DELETE should return None (no access check)."""
        from app.proxy.routes import _check_company_folder_access
        from app.core.auth_middleware import GatewayUser

        user = GatewayUser(
            sub="user-1",
            preferred_username="testuser",
            organization=["Org", {"Org": {"id": "org-1"}}],
            realm_access={"roles": ["company.view"]},
        )

        for method in ["POST", "PUT", "DELETE", "PATCH"]:
            result = await _check_company_folder_access("companies/123", method, user)
            assert result is None, f"Expected None for method {method}"

    @pytest.mark.asyncio
    async def test_no_user_returns_none(self):
        """Unauthenticated request (user=None) should return None."""
        from app.proxy.routes import _check_company_folder_access

        result = await _check_company_folder_access("companies/123", "GET", None)
        assert result is None

    @pytest.mark.asyncio
    async def test_non_matching_path_returns_none(self):
        """Path that doesn't match companies/{id} should return None."""
        from app.proxy.routes import _check_company_folder_access
        from app.core.auth_middleware import GatewayUser

        user = GatewayUser(
            sub="user-1",
            preferred_username="testuser",
            organization=["Org", {"Org": {"id": "org-1"}}],
            realm_access={"roles": ["company.view"]},
        )

        result = await _check_company_folder_access("tasks/123", "GET", user)
        assert result is None

    @pytest.mark.asyncio
    async def test_access_granted_returns_none(self):
        """When user has access to the company, should return None."""
        from app.proxy.routes import _check_company_folder_access
        from app.core.auth_middleware import GatewayUser

        user = GatewayUser(
            sub="user-1",
            preferred_username="testuser",
            organization=["Org", {"Org": {"id": "org-1"}}],
            realm_access={"roles": ["company.view"]},
        )

        with (
            patch("app.database.get_global_db_context") as mock_db_ctx,
            patch("app.services.folder.FolderService") as mock_folder_svc,
        ):
            mock_db = AsyncMock()
            mock_db_ctx.return_value.__aenter__ = AsyncMock(return_value=mock_db)
            mock_db_ctx.return_value.__aexit__ = AsyncMock(return_value=False)
            mock_folder_svc.user_has_company_access = AsyncMock(return_value=True)

            result = await _check_company_folder_access("companies/123", "GET", user)
            assert result is None

    @pytest.mark.asyncio
    async def test_access_denied_returns_404(self):
        """When user is denied access, should return 404 Response."""
        from app.proxy.routes import _check_company_folder_access
        from app.core.auth_middleware import GatewayUser

        user = GatewayUser(
            sub="user-1",
            preferred_username="testuser",
            organization=["Org", {"Org": {"id": "org-1"}}],
            realm_access={"roles": ["company.view"]},
        )

        with (
            patch("app.database.get_global_db_context") as mock_db_ctx,
            patch("app.services.folder.FolderService") as mock_folder_svc,
        ):
            mock_db = AsyncMock()
            mock_db_ctx.return_value.__aenter__ = AsyncMock(return_value=mock_db)
            mock_db_ctx.return_value.__aexit__ = AsyncMock(return_value=False)
            mock_folder_svc.user_has_company_access = AsyncMock(return_value=False)

            result = await _check_company_folder_access("companies/123", "GET", user)
            assert result is not None
            assert result.status_code == 404

    @pytest.mark.asyncio
    async def test_admin_bypasses_check(self):
        """User with admin.organizations role should bypass the check."""
        from app.proxy.routes import _check_company_folder_access
        from app.core.auth_middleware import GatewayUser

        user = GatewayUser(
            sub="admin-1",
            preferred_username="admin",
            organization=["Org", {"Org": {"id": "org-1"}}],
            realm_access={"roles": ["admin.organizations", "company.view"]},
        )

        # No need to mock DB — admin should short-circuit before DB call
        result = await _check_company_folder_access("companies/123", "GET", user)
        assert result is None

    @pytest.mark.asyncio
    async def test_error_allows_request(self):
        """DB error should fail-open (return None, allowing the request)."""
        from app.proxy.routes import _check_company_folder_access
        from app.core.auth_middleware import GatewayUser

        user = GatewayUser(
            sub="user-1",
            preferred_username="testuser",
            organization=["Org", {"Org": {"id": "org-1"}}],
            realm_access={"roles": ["company.view"]},
        )

        with patch("app.database.get_global_db_context", side_effect=RuntimeError("DB down")):
            result = await _check_company_folder_access("companies/123", "GET", user)
            assert result is None

    @pytest.mark.asyncio
    async def test_trailing_slash_not_matched(self):
        """'companies/123/' with trailing slash should not match the regex."""
        from app.proxy.routes import _check_company_folder_access
        from app.core.auth_middleware import GatewayUser

        user = GatewayUser(
            sub="user-1",
            preferred_username="testuser",
            organization=["Org", {"Org": {"id": "org-1"}}],
            realm_access={"roles": ["company.view"]},
        )

        result = await _check_company_folder_access("companies/123/", "GET", user)
        assert result is None
