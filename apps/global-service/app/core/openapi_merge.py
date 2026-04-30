"""Merge all backend OpenAPI schemas into Global-Service docs.

Fetches /openapi.json lazily (on first /docs access) from all backends:
- Screen (FastAPI)
- Target (Symfony/API Platform)
- Stream (FastAPI)

Merges paths + component schemas into the gateway's own OpenAPI spec.
This gives a single unified Swagger UI at the gateway level.

Each backend's schema is cached with a configurable TTL to avoid hitting
backends on every /docs page load.
"""

import asyncio
import time
from copy import deepcopy

import httpx
from fastapi import FastAPI
from fastapi.openapi.utils import get_openapi
from starlette.requests import Request
from starlette.responses import JSONResponse
from starlette.routing import Route

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

# Cache for backend OpenAPI schemas
_backend_schemas: dict[str, dict | None] = {
    "screen": None,
    "target": None,
    "stream": None,
}
_cache_timestamps: dict[str, float] = {
    "screen": 0,
    "target": 0,
    "stream": 0,
}
_CACHE_TTL_SECONDS = 300  # 5 minutes

# Prefixes to avoid schema name collisions between services
_BACKEND_SCHEMA_PREFIXES: dict[str, str] = {
    "screen": "Screen_",
    "target": "Target_",
    "stream": "Stream_",
}

# ─── API metadata ─────────────────────────────────────

API_VERSION = "0.5.0"

API_DESCRIPTION = """\
Unified REST API for the ChapsMind platform — competitive intelligence
and automated company monitoring.

## Authentication

All endpoints except health probes (`/health`, `/ready`) require a valid
Keycloak JWT passed via `Authorization: Bearer <token>`. Both **OAuth2
Password** (for service accounts and Swagger UI) and **Authorization
Code** (for browser-based flows) grant types are supported.

## Streaming

Long-running AI operations may stream responses using one of the
following content types:

- **SSE** — `text/event-stream`
- **NDJSON** — `application/x-ndjson`
- **JSON streaming** — `application/stream+json`

## Error codes

| Code | Meaning |
|------|---------|
| 401  | Authentication failed or token expired |
| 402  | Insufficient tokens to perform the operation |
| 403  | Permission denied |
| 404  | Resource not found or not accessible |
| 5xx  | Service temporarily unavailable |
"""

API_CONTACT = {
    "name": "ChapsVision - ChapsMind Team",
    "email": "dev-chapsmind@chapsvision.com",
}

API_LICENSE = {
    "name": "Proprietary",
}

# ─── Tag groups (x-tagGroups, Redoc extension, also works with Swagger UI plugins) ───

# Maps each OpenAPI tag to a human-readable module group.
# Order matters: tags appear in Swagger UI in this order.
# ⚠️ COUPLED with TAG_GROUPS below — when adding a tag here, also add it
# to the appropriate group in TAG_GROUPS (and vice versa).
TAG_DEFINITIONS: list[dict] = [
    # ── Core ──
    {"name": "authentication", "description": "Login, logout, token refresh and verification"},
    {"name": "account", "description": "Current user profile, sessions and security events"},
    {"name": "organization", "description": "Current user's organization context"},
    # ── Companies & AI ──
    {"name": "companies", "description": "Company CRUD, search, CSV import, archiving"},
    {"name": "tasks", "description": "AI workflow tasks per company (status, restart, tokens)"},
    {"name": "chapse", "description": "AI chat assistant (conversations, context)"},
    {"name": "translation", "description": "Company data translation via Systran"},
    # ── Workspace ──
    {"name": "folders", "description": "Watch-file folders (CRUD, sharing, favorites)"},
    {"name": "team", "description": "Team member listing, permissions, password reset"},
    # ── Organization admin ──
    {"name": "organizations", "description": "Organization CRUD, members and roles management"},
    {"name": "users", "description": "Admin user management (search, create, enable/disable)"},
    {"name": "tokens", "description": "Organization token balance and consumption history"},
    {"name": "credits", "description": "Credit usage stats per organization"},
    {"name": "modules", "description": "Enable/disable feature modules per organization"},
    # ── Configuration ──
    {"name": "ai-preferences", "description": "AI preferences and quick actions"},
    {"name": "feature-flags", "description": "Organization feature flags"},
    {"name": "data-sources", "description": "External data source configuration"},
    # ── Administration ──
    {"name": "admin", "description": "Global admin dashboard and usage stats"},
    {"name": "admin-tasks", "description": "Admin task management (fail-stuck, restart, stats)"},
    {"name": "cost-analysis", "description": "Cost analysis by organization, task type, trends"},
    {"name": "security", "description": "Security health checks and stats"},
    # ── System ──
    {"name": "internal", "description": "Service-to-service internal endpoints"},
    {"name": "health", "description": "Liveness and readiness probes"},
]

# Redoc x-tagGroups extension (groups tags in sidebar)
TAG_GROUPS = [
    {
        "name": "Core",
        "tags": ["authentication", "account", "organization"],
    },
    {
        "name": "Companies & AI",
        "tags": ["companies", "tasks", "chapse", "translation"],
    },
    {
        "name": "Workspace",
        "tags": ["folders", "team"],
    },
    {
        "name": "Organization Admin",
        "tags": ["organizations", "users", "tokens", "credits", "modules"],
    },
    {
        "name": "Configuration",
        "tags": ["ai-preferences", "feature-flags", "data-sources"],
    },
    {
        "name": "Administration",
        "tags": ["admin", "admin-tasks", "cost-analysis", "security"],
    },
    {
        "name": "System",
        "tags": ["internal", "health"],
    },
]


# ─── Schema fetching and merging ─────────────────────


async def _fetch_backend_schema(backend_name: str, backend_url: str) -> dict | None:
    """Fetch a backend's OpenAPI schema with TTL cache.

    Args:
        backend_name: one of "screen", "target", "stream"
        backend_url: base URL of the backend service

    Returns:
        The OpenAPI schema dict, or None if fetch failed

    Different backends expose OpenAPI at different endpoints:
    - FastAPI (screen, stream): /openapi.json
    - Symfony/API Platform (target): /api/docs with Accept: application/vnd.openapi+json

    Uses httpx.AsyncClient to avoid blocking the event loop (called from
    FastAPI's async openapi() handler).

    Note on thread safety: module-level globals are mutated without a lock.
    With uvicorn multiprocess workers each process has its own copy (safe).
    With async concurrency the worst case is a harmless double-fetch.
    """
    global _backend_schemas, _cache_timestamps

    now = time.time()
    cached = _backend_schemas.get(backend_name)
    timestamp = _cache_timestamps.get(backend_name, 0)

    if cached is not None and (now - timestamp) < _CACHE_TTL_SECONDS:
        return cached

    # Determine the correct endpoint and headers for each backend
    if backend_name == "target":
        schema_url = f"{backend_url}/api/docs"
        headers = {"Accept": "application/vnd.openapi+json"}
    else:
        # FastAPI backends (screen, stream)
        schema_url = f"{backend_url}/openapi.json"
        headers = {}

    try:
        async with httpx.AsyncClient(timeout=5) as client:
            resp = await client.get(schema_url, headers=headers)
        resp.raise_for_status()
        schema = resp.json()
        _backend_schemas[backend_name] = schema
        _cache_timestamps[backend_name] = now
        logger.info(f"Fetched {backend_name} OpenAPI schema", extra={"url": schema_url})
        return schema
    except Exception as e:
        logger.warning(
            f"Failed to fetch {backend_name} OpenAPI schema",
            extra={"url": schema_url, "error": str(e)},
        )
        if cached is None:
            return None
        return cached


def _prefix_schema_refs(obj: dict, prefix: str) -> dict:
    """Recursively prefix all $ref schema references in an OpenAPI object."""
    result = {}
    for key, value in obj.items():
        if key == "$ref" and isinstance(value, str) and value.startswith("#/components/schemas/"):
            schema_name = value.split("/")[-1]
            result[key] = f"#/components/schemas/{prefix}{schema_name}"
        elif isinstance(value, dict):
            result[key] = _prefix_schema_refs(value, prefix)
        elif isinstance(value, list):
            result[key] = [_prefix_schema_refs(item, prefix) if isinstance(item, dict) else item for item in value]
        else:
            result[key] = value
    return result


def _build_security_schemes() -> dict:
    """Build OAuth2 security schemes using the public Keycloak URL.

    Uses the public Keycloak client (chapsmind-front) for Swagger UI
    because the password grant requires a public client (no client secret).
    """
    public_url = settings.KEYCLOAK_PUBLIC_URL.rstrip("/")
    realm = settings.KEYCLOAK_REALM
    token_url = f"{public_url}/realms/{realm}/protocol/openid-connect/token"

    return {
        # Referenced by screen endpoints via fastapi-keycloak
        "OAuth2PasswordBearer": {
            "type": "oauth2",
            "flows": {
                "password": {
                    "tokenUrl": token_url,
                    "scopes": {},
                },
            },
        },
        # Referenced by global-service endpoints via HTTPBearer dependency
        "HTTPBearer": {
            "type": "http",
            "scheme": "bearer",
            "bearerFormat": "JWT",
        },
    }


def _merge_backend_schema(gateway_schema: dict, backend_schema: dict, backend_name: str) -> dict:
    """Merge backend paths and components into the gateway schema.

    Args:
        gateway_schema: the gateway's base OpenAPI schema
        backend_schema: the backend's OpenAPI schema to merge
        backend_name: one of "screen", "target", "stream" (used for prefix)

    Returns:
        The merged schema
    """
    merged = deepcopy(gateway_schema)
    prefix = _BACKEND_SCHEMA_PREFIXES.get(backend_name, f"{backend_name.capitalize()}_")

    # Merge paths (backend endpoints that aren't already in gateway)
    backend_paths = backend_schema.get("paths", {})
    for path, methods in backend_paths.items():
        if path not in merged.get("paths", {}):
            path_item = _prefix_schema_refs(deepcopy(methods), prefix)

            # Add security requirement to all methods if not already present.
            # Skip if the backend already set "security": [] (explicitly public)
            # or if the operation carries x-public: true (gateway convention).
            for method in ["get", "post", "put", "patch", "delete", "head", "options"]:
                if method in path_item and isinstance(path_item[method], dict):
                    operation = path_item[method]
                    if "security" not in operation and not operation.get("x-public"):
                        operation["security"] = [{"OAuth2PasswordBearer": []}]

            merged.setdefault("paths", {})[path] = path_item

    # Merge component schemas with prefix
    backend_schemas = backend_schema.get("components", {}).get("schemas", {})
    for name, definition in backend_schemas.items():
        prefixed_name = f"{prefix}{name}"
        merged.setdefault("components", {}).setdefault("schemas", {})
        if prefixed_name not in merged["components"]["schemas"]:
            merged["components"]["schemas"][prefixed_name] = _prefix_schema_refs(definition, prefix)

    return merged


# ─── Setup ────────────────────────────────────────────


def setup_merged_openapi(app: FastAPI) -> None:
    """Replace FastAPI's /openapi.json route with an async one that merges all backend schemas.

    Fetches and merges schemas from:
    - Screen (FastAPI)
    - Target (Symfony/API Platform)
    - Stream (FastAPI)

    Call this once after app creation. The actual fetches happen on first
    access to /docs or /openapi.json.

    We use a custom async route instead of overriding app.openapi() because
    FastAPI calls app.openapi() synchronously. Using an async route lets us
    fetch backend schemas with httpx.AsyncClient without blocking the event loop.

    Each backend schema is cached with a 5-minute TTL to avoid hitting
    backends on every page load.
    """

    async def _build_merged_schema() -> dict:
        gateway_schema = get_openapi(
            title="ChapsMind API",
            description=API_DESCRIPTION,
            version=API_VERSION,
            routes=app.routes,
            tags=TAG_DEFINITIONS,
            contact=API_CONTACT,
            license_info=API_LICENSE,
        )

        # Add Redoc tag groups extension
        gateway_schema["x-tagGroups"] = TAG_GROUPS

        # Override security schemes with public Keycloak URLs
        gateway_schema.setdefault("components", {})["securitySchemes"] = _build_security_schemes()

        # Merge schemas from all backends
        backends = [
            ("screen", settings.SCREEN_BASE_URL),
            ("target", settings.TARGET_BASE_URL),
            ("stream", settings.STREAM_BASE_URL),
        ]

        results = await asyncio.gather(
            *[_fetch_backend_schema(name, url) for name, url in backends],
            return_exceptions=True,
        )
        merged = gateway_schema
        for (backend_name, _), schema in zip(backends, results, strict=False):
            if isinstance(schema, dict):
                merged = _merge_backend_schema(merged, schema, backend_name)

        return merged

    async def openapi_route(request: Request) -> JSONResponse:
        return JSONResponse(await _build_merged_schema())

    # Replace FastAPI's built-in /openapi.json route with our async version
    openapi_url = app.openapi_url or "/openapi.json"
    for i, route in enumerate(app.routes):
        if isinstance(route, Route) and route.path == openapi_url:
            app.routes[i] = Route(openapi_url, openapi_route, include_in_schema=False)
            break
