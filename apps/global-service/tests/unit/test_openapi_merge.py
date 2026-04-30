"""Tests for the OpenAPI merge functionality.

Covers:
- Config settings exist (BACKEND_BASE_URL, KEYCLOAK_PUBLIC_URL)
- setup_merged_openapi configures the app
- Schema merging with screen available (mocked)
- Graceful fallback when screen unavailable
- Schema name prefixing (Screen_ prefix)
- Security schemes injection
- Cache TTL behavior
"""

import time
from unittest.mock import AsyncMock, MagicMock, patch

import httpx
import pytest
from fastapi import FastAPI
from httpx import ASGITransport, AsyncClient

from app.core.openapi_merge import (
    _build_security_schemes,
    _fetch_backend_schema,
    _merge_backend_schema,
    _prefix_schema_refs,
    setup_merged_openapi,
)


class TestConfigSettings:
    """Test that required config settings exist."""

    def test_screen_base_url_exists(self):
        from app.core.config import settings

        assert hasattr(settings, "SCREEN_BASE_URL")
        assert settings.SCREEN_BASE_URL is not None

    def test_keycloak_public_url_exists(self):
        from app.core.config import settings

        assert hasattr(settings, "KEYCLOAK_PUBLIC_URL")
        assert settings.KEYCLOAK_PUBLIC_URL is not None


class TestPrefixSchemaRefs:
    """Test $ref prefixing logic."""

    def test_prefixes_schema_ref(self):
        obj = {"$ref": "#/components/schemas/Company"}
        result = _prefix_schema_refs(obj, "Screen_")
        assert result["$ref"] == "#/components/schemas/Screen_Company"

    def test_ignores_non_schema_ref(self):
        obj = {"$ref": "#/components/parameters/pageSize"}
        result = _prefix_schema_refs(obj, "Screen_")
        assert result["$ref"] == "#/components/parameters/pageSize"

    def test_handles_nested_refs(self):
        obj = {"content": {"application/json": {"schema": {"$ref": "#/components/schemas/TaskList"}}}}
        result = _prefix_schema_refs(obj, "Screen_")
        assert result["content"]["application/json"]["schema"]["$ref"] == "#/components/schemas/Screen_TaskList"

    def test_handles_array_of_refs(self):
        obj = {
            "oneOf": [
                {"$ref": "#/components/schemas/A"},
                {"$ref": "#/components/schemas/B"},
            ]
        }
        result = _prefix_schema_refs(obj, "Screen_")
        assert result["oneOf"][0]["$ref"] == "#/components/schemas/Screen_A"
        assert result["oneOf"][1]["$ref"] == "#/components/schemas/Screen_B"

    def test_handles_allof_with_refs(self):
        """allOf is common with Pydantic inheritance — refs inside must be prefixed."""
        obj = {
            "allOf": [
                {"$ref": "#/components/schemas/BaseModel"},
                {"type": "object", "properties": {"name": {"type": "string"}}},
            ]
        }
        result = _prefix_schema_refs(obj, "Screen_")
        assert result["allOf"][0]["$ref"] == "#/components/schemas/Screen_BaseModel"
        assert result["allOf"][1]["type"] == "object"

    def test_handles_non_dict_array_items(self):
        obj = {"enum": ["active", "inactive"]}
        result = _prefix_schema_refs(obj, "Screen_")
        assert result["enum"] == ["active", "inactive"]


class TestBuildSecuritySchemes:
    """Test security scheme generation."""

    def test_returns_oauth2_and_httpbearer(self):
        schemes = _build_security_schemes()
        assert "OAuth2PasswordBearer" in schemes
        assert "HTTPBearer" in schemes

    def test_oauth2_has_password_flow(self):
        schemes = _build_security_schemes()
        assert "password" in schemes["OAuth2PasswordBearer"]["flows"]

    def test_httpbearer_is_jwt(self):
        schemes = _build_security_schemes()
        assert schemes["HTTPBearer"]["type"] == "http"
        assert schemes["HTTPBearer"]["scheme"] == "bearer"


class TestFetchScreenSchema:
    """Test schema fetching with cache."""

    def setup_method(self):
        """Reset cache before each test."""
        import app.core.openapi_merge as mod

        mod._backend_schemas["screen"] = None
        mod._cache_timestamps["screen"] = 0

    @pytest.mark.asyncio
    async def test_fetches_schema_successfully(self):
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.json.return_value = {"openapi": "3.0.0", "paths": {}}
        mock_response.raise_for_status = MagicMock()

        mock_client = AsyncMock()
        mock_client.get = AsyncMock(return_value=mock_response)
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("app.core.openapi_merge.httpx.AsyncClient", return_value=mock_client):
            result = await _fetch_backend_schema("screen", "http://screen:8000")

        assert result is not None
        assert result["openapi"] == "3.0.0"
        mock_client.get.assert_called_once()

    @pytest.mark.asyncio
    async def test_returns_none_when_unavailable(self):
        mock_client = AsyncMock()
        mock_client.get = AsyncMock(side_effect=Exception("Connection refused"))
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("app.core.openapi_merge.httpx.AsyncClient", return_value=mock_client):
            result = await _fetch_backend_schema("screen", "http://screen:8000")

        assert result is None

    @pytest.mark.asyncio
    async def test_uses_cache_within_ttl(self):
        mock_response = MagicMock()
        mock_response.json.return_value = {"openapi": "3.0.0"}
        mock_response.raise_for_status = MagicMock()

        mock_client = AsyncMock()
        mock_client.get = AsyncMock(return_value=mock_response)
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("app.core.openapi_merge.httpx.AsyncClient", return_value=mock_client):
            # First call fetches
            await _fetch_backend_schema("screen", "http://screen:8000")
            # Second call should use cache
            await _fetch_backend_schema("screen", "http://screen:8000")

        assert mock_client.get.call_count == 1

    @pytest.mark.asyncio
    async def test_refetches_after_ttl(self):
        import app.core.openapi_merge as mod

        mock_response = MagicMock()
        mock_response.json.return_value = {"openapi": "3.0.0"}
        mock_response.raise_for_status = MagicMock()

        mock_client = AsyncMock()
        mock_client.get = AsyncMock(return_value=mock_response)
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("app.core.openapi_merge.httpx.AsyncClient", return_value=mock_client):
            # First call
            await _fetch_backend_schema("screen", "http://screen:8000")
            # Expire cache
            mod._cache_timestamps["screen"] = time.time() - 400
            # Second call should re-fetch
            await _fetch_backend_schema("screen", "http://screen:8000")

        assert mock_client.get.call_count == 2

    @pytest.mark.asyncio
    async def test_returns_stale_cache_when_fetch_fails(self):
        """When fetch succeeds then later fails, the stale cached schema is returned."""
        import app.core.openapi_merge as mod

        cached_schema = {"openapi": "3.0.0", "paths": {"/api/cached": {}}}
        mock_response = MagicMock()
        mock_response.json.return_value = cached_schema
        mock_response.raise_for_status = MagicMock()

        mock_client_ok = AsyncMock()
        mock_client_ok.get = AsyncMock(return_value=mock_response)
        mock_client_ok.__aenter__ = AsyncMock(return_value=mock_client_ok)
        mock_client_ok.__aexit__ = AsyncMock(return_value=False)

        # First call: fetch succeeds → cache populated
        with patch("app.core.openapi_merge.httpx.AsyncClient", return_value=mock_client_ok):
            result1 = await _fetch_backend_schema("screen", "http://screen:8000")
        assert result1 is not None
        assert result1["paths"] == {"/api/cached": {}}

        # Expire the cache
        mod._cache_timestamps["screen"] = time.time() - 400

        # Second call: fetch fails → stale cache returned (not None)
        mock_client_fail = AsyncMock()
        mock_client_fail.get = AsyncMock(side_effect=Exception("Screen is down"))
        mock_client_fail.__aenter__ = AsyncMock(return_value=mock_client_fail)
        mock_client_fail.__aexit__ = AsyncMock(return_value=False)

        with patch("app.core.openapi_merge.httpx.AsyncClient", return_value=mock_client_fail):
            result2 = await _fetch_backend_schema("screen", "http://screen:8000")

        assert result2 is not None
        assert result2["paths"] == {"/api/cached": {}}

    @pytest.mark.asyncio
    async def test_handles_timeout_exception(self):
        """httpx.TimeoutException is caught gracefully, returns None on empty cache."""
        mock_client = AsyncMock()
        mock_client.get = AsyncMock(side_effect=httpx.TimeoutException("Connection timed out"))
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("app.core.openapi_merge.httpx.AsyncClient", return_value=mock_client):
            result = await _fetch_backend_schema("screen", "http://screen:8000")

        assert result is None

    @pytest.mark.asyncio
    async def test_handles_connect_error(self):
        """httpx.ConnectError (Screen not reachable) is caught gracefully."""
        mock_client = AsyncMock()
        mock_client.get = AsyncMock(side_effect=httpx.ConnectError("Connection refused"))
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("app.core.openapi_merge.httpx.AsyncClient", return_value=mock_client):
            result = await _fetch_backend_schema("screen", "http://screen:8000")

        assert result is None


class TestMergeScreenSchema:
    """Test schema merging logic."""

    def test_merges_paths(self):
        gateway = {"paths": {"/api/tokens": {"get": {}}}, "components": {}}
        screen = {"paths": {"/api/companies": {"get": {}}}, "components": {}}

        result = _merge_backend_schema(gateway, screen, "screen")

        assert "/api/tokens" in result["paths"]
        assert "/api/companies" in result["paths"]

    def test_gateway_paths_take_priority(self):
        gateway = {"paths": {"/api/shared": {"get": {"summary": "gateway"}}}, "components": {}}
        screen = {"paths": {"/api/shared": {"get": {"summary": "screen"}}}, "components": {}}

        result = _merge_backend_schema(gateway, screen, "screen")

        assert result["paths"]["/api/shared"]["get"]["summary"] == "gateway"

    def test_prefixes_screen_schemas(self):
        gateway = {"paths": {}, "components": {"schemas": {}}}
        screen = {
            "paths": {},
            "components": {"schemas": {"Company": {"type": "object"}}},
        }

        result = _merge_backend_schema(gateway, screen, "screen")

        assert "Screen_Company" in result["components"]["schemas"]
        assert "Company" not in result["components"]["schemas"]

    def test_does_not_modify_original(self):
        gateway = {"paths": {"/api/a": {}}, "components": {}}
        screen = {"paths": {"/api/b": {}}, "components": {}}

        _merge_backend_schema(gateway, screen, "screen")

        assert "/api/b" not in gateway["paths"]


class TestSetupMergedOpenapi:
    """Test setup_merged_openapi integration."""

    def test_replaces_openapi_route(self):
        from starlette.routing import Route

        app = FastAPI(title="Test")
        setup_merged_openapi(app)

        openapi_routes = [r for r in app.routes if isinstance(r, Route) and r.path == "/openapi.json"]
        assert len(openapi_routes) == 1
        # The route endpoint should be our custom async one, not FastAPI's default
        assert openapi_routes[0].endpoint.__name__ == "openapi_route"

    @pytest.mark.asyncio
    @patch("app.core.openapi_merge._fetch_backend_schema", new_callable=AsyncMock, return_value=None)
    async def test_returns_gateway_only_when_screen_unavailable(self, mock_fetch):
        app = FastAPI(title="Test")
        app.add_api_route("/api/test", lambda: {"ok": True})

        setup_merged_openapi(app)

        async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as client:
            resp = await client.get("/openapi.json")

        schema = resp.json()
        assert "ChapsMind API" in schema.get("info", {}).get("title", "")
        assert "securitySchemes" in schema.get("components", {})

    @pytest.mark.asyncio
    @patch("app.core.openapi_merge._fetch_backend_schema", new_callable=AsyncMock)
    async def test_merges_screen_when_available(self, mock_fetch):
        mock_fetch.return_value = {
            "paths": {"/api/companies": {"get": {"summary": "List companies"}}},
            "components": {"schemas": {"Company": {"type": "object"}}},
        }

        app = FastAPI(title="Test")
        app.add_api_route("/api/test", lambda: {"ok": True})

        setup_merged_openapi(app)

        async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as client:
            resp = await client.get("/openapi.json")

        schema = resp.json()
        assert "/api/companies" in schema["paths"]
        assert "Screen_Company" in schema["components"]["schemas"]


class TestOpenAPISchemaValidity:
    """Validate that the merged schema is structurally valid OpenAPI 3.x."""

    REALISTIC_SCREEN_SCHEMA = {
        "openapi": "3.1.0",
        "info": {"title": "Screen API", "version": "0.1.0"},
        "paths": {
            "/api/companies": {
                "get": {
                    "summary": "List companies",
                    "operationId": "list_companies",
                    "tags": ["companies"],
                    "responses": {
                        "200": {
                            "description": "Successful Response",
                            "content": {
                                "application/json": {
                                    "schema": {"$ref": "#/components/schemas/PaginatedResponse_CompanyResponse_"}
                                }
                            },
                        }
                    },
                },
                "post": {
                    "summary": "Create company",
                    "operationId": "create_company",
                    "tags": ["companies"],
                    "requestBody": {
                        "content": {"application/json": {"schema": {"$ref": "#/components/schemas/CompanyCreate"}}}
                    },
                    "responses": {
                        "200": {
                            "description": "Successful Response",
                            "content": {
                                "application/json": {"schema": {"$ref": "#/components/schemas/CompanyResponse"}}
                            },
                        }
                    },
                },
            },
            "/api/companies/{company_id}": {
                "get": {
                    "summary": "Get company",
                    "operationId": "get_company",
                    "tags": ["companies"],
                    "parameters": [
                        {
                            "name": "company_id",
                            "in": "path",
                            "required": True,
                            "schema": {"type": "integer"},
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Successful Response",
                            "content": {
                                "application/json": {"schema": {"$ref": "#/components/schemas/CompanyResponse"}}
                            },
                        }
                    },
                },
            },
        },
        "components": {
            "schemas": {
                "CompanyCreate": {
                    "type": "object",
                    "properties": {
                        "name": {"type": "string"},
                        "website": {"type": "string", "format": "uri"},
                    },
                    "required": ["name", "website"],
                },
                "CompanyResponse": {
                    "type": "object",
                    "properties": {
                        "id": {"type": "integer"},
                        "name": {"type": "string"},
                        "website": {"type": "string"},
                    },
                },
                "PaginatedResponse_CompanyResponse_": {
                    "type": "object",
                    "properties": {
                        "items": {
                            "type": "array",
                            "items": {"$ref": "#/components/schemas/CompanyResponse"},
                        },
                        "total": {"type": "integer"},
                    },
                },
                "InheritedModel": {
                    "allOf": [
                        {"$ref": "#/components/schemas/CompanyResponse"},
                        {"type": "object", "properties": {"extra": {"type": "string"}}},
                    ]
                },
            }
        },
    }

    @pytest.mark.asyncio
    @patch("app.core.openapi_merge._fetch_backend_schema", new_callable=AsyncMock)
    async def test_merged_schema_is_valid_openapi(self, mock_fetch):
        """Merged schema has all required OpenAPI 3.x top-level fields."""
        mock_fetch.return_value = self.REALISTIC_SCREEN_SCHEMA

        app = FastAPI(title="Test")
        app.add_api_route("/api/health", lambda: {"ok": True}, tags=["health"])
        setup_merged_openapi(app)

        async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as client:
            resp = await client.get("/openapi.json")

        assert resp.status_code == 200
        schema = resp.json()

        # Required OpenAPI 3.x top-level fields
        assert "openapi" in schema
        assert schema["openapi"].startswith("3.")
        assert "info" in schema
        assert "title" in schema["info"]
        assert "version" in schema["info"]
        assert "paths" in schema

    @pytest.mark.asyncio
    @patch("app.core.openapi_merge._fetch_backend_schema", new_callable=AsyncMock)
    async def test_merged_refs_are_resolvable(self, mock_fetch):
        """All $ref pointers in merged paths point to existing schemas."""
        mock_fetch.return_value = self.REALISTIC_SCREEN_SCHEMA

        app = FastAPI(title="Test")
        app.add_api_route("/api/health", lambda: {"ok": True}, tags=["health"])
        setup_merged_openapi(app)

        async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as client:
            resp = await client.get("/openapi.json")

        schema = resp.json()
        available_schemas = set(schema.get("components", {}).get("schemas", {}).keys())

        # Collect all $ref from paths
        def collect_refs(obj: dict | list) -> list[str]:
            refs = []
            if isinstance(obj, dict):
                for k, v in obj.items():
                    if k == "$ref" and isinstance(v, str) and v.startswith("#/components/schemas/"):
                        refs.append(v.split("/")[-1])
                    elif isinstance(v, dict | list):
                        refs.extend(collect_refs(v))
            elif isinstance(obj, list):
                for item in obj:
                    if isinstance(item, dict | list):
                        refs.extend(collect_refs(item))
            return refs

        path_refs = collect_refs(schema.get("paths", {}))
        schema_refs = collect_refs(schema.get("components", {}).get("schemas", {}))
        all_refs = set(path_refs + schema_refs)

        missing = all_refs - available_schemas
        assert missing == set(), f"Unresolvable $ref pointers: {missing}"

    @pytest.mark.asyncio
    @patch("app.core.openapi_merge._fetch_backend_schema", new_callable=AsyncMock)
    async def test_merged_schema_has_security_schemes(self, mock_fetch):
        """Merged schema includes proper security schemes for Swagger UI."""
        mock_fetch.return_value = self.REALISTIC_SCREEN_SCHEMA

        app = FastAPI(title="Test")
        setup_merged_openapi(app)

        async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as client:
            resp = await client.get("/openapi.json")

        schema = resp.json()
        security_schemes = schema.get("components", {}).get("securitySchemes", {})
        assert "OAuth2PasswordBearer" in security_schemes
        assert "HTTPBearer" in security_schemes
        assert "password" in security_schemes["OAuth2PasswordBearer"]["flows"]

    @pytest.mark.asyncio
    @patch("app.core.openapi_merge._fetch_backend_schema", new_callable=AsyncMock)
    async def test_merged_schema_has_tag_groups(self, mock_fetch):
        """Merged schema includes x-tagGroups for Redoc sidebar."""
        mock_fetch.return_value = self.REALISTIC_SCREEN_SCHEMA

        app = FastAPI(title="Test")
        setup_merged_openapi(app)

        async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as client:
            resp = await client.get("/openapi.json")

        schema = resp.json()
        assert "x-tagGroups" in schema
        group_names = [g["name"] for g in schema["x-tagGroups"]]
        assert "Core" in group_names
        assert "System" in group_names


class TestDocsAccessControl:
    """Verify ENABLE_DOCS setting controls /docs and /openapi.json availability."""

    @pytest.mark.asyncio
    async def test_docs_enabled_by_default(self):
        """When ENABLE_DOCS is true (default), /openapi.json and /docs are accessible."""
        app = FastAPI(title="Test")
        app.add_api_route("/api/health", lambda: {"ok": True})
        setup_merged_openapi(app)

        async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as client:
            openapi_resp = await client.get("/openapi.json")
            docs_resp = await client.get("/docs")

        assert openapi_resp.status_code == 200
        assert docs_resp.status_code == 200
        assert "swagger" in docs_resp.text.lower()

    @pytest.mark.asyncio
    async def test_docs_disabled_returns_404(self):
        """When docs are disabled, /openapi.json, /docs, and /redoc return 404."""
        app = FastAPI(
            title="Test",
            docs_url=None,
            redoc_url=None,
            openapi_url=None,
        )
        app.add_api_route("/api/health", lambda: {"ok": True})

        async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as client:
            openapi_resp = await client.get("/openapi.json")
            docs_resp = await client.get("/docs")
            redoc_resp = await client.get("/redoc")

        assert openapi_resp.status_code == 404
        assert docs_resp.status_code == 404
        assert redoc_resp.status_code == 404
