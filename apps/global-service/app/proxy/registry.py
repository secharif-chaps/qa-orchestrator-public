"""
Module Registry — auto-discovers backend routes via OpenAPI schemas.

Fetches each backend's /openapi.json at startup and builds a routing
table that maps URL paths to modules. Extracts gateway metadata from
OpenAPI extensions (x-permissions, x-token-cost, x-public).

Usage:
    registry = ModuleRegistry(backends={"screen": "http://screen:8000"})
    await registry.discover_all()

    match = registry.resolve("/api/companies", "GET")
    if match:
        module, backend_path = match
        operation = registry.resolve_operation(module.name, "GET", backend_path)
"""

from __future__ import annotations

import asyncio
from dataclasses import dataclass, field
from enum import StrEnum

import httpx

from app.core.logging_config import get_logger

logger = get_logger(__name__)

# HTTP methods accepted for routing (uppercase)
_VALID_METHODS = {"GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"}


def _normalize_path(path: str) -> str:
    """Normalize a path: strip trailing slash for consistent matching."""
    return path.rstrip("/") or "/"


def _matches_on_segment_boundary(request_path: str, static_prefix: str) -> bool:
    """Check that request_path starts with static_prefix on a '/' boundary.

    Prevents "/api/companies-old" from matching "/api/companies/{id}"
    whose static_prefix is "/api/companies/".

    The prefix must either:
    - Be an exact match, or
    - End with '/', or
    - Be followed by '/' in the request path
    """
    if not request_path.startswith(static_prefix):
        return False
    # Exact match
    if len(request_path) == len(static_prefix):
        return True
    # Prefix ends with / (e.g. "/api/companies/")
    if static_prefix.endswith("/"):
        return True
    # Next char in request is / (e.g. prefix="/api/companies", path="/api/companies/123")
    return request_path[len(static_prefix)] == "/"


class ModuleName(StrEnum):
    """Backend modules known to the gateway.

    Mirrors the DB enum in models/organization.py but kept separate
    to avoid importing SQLAlchemy models in the proxy layer.

    ⚠️ COUPLED: values must stay in sync with models.organization.ModuleName.
    See test_module_name_sync_with_db_enum in tests/test_module_registry.py.
    """

    SCREEN = "screen"
    TARGET = "target"
    EXPLORE = "explore"


# Module priority for overlap resolution (lower index = higher priority)
MODULE_PRIORITY: list[ModuleName] = [
    ModuleName.SCREEN,
    ModuleName.TARGET,
    ModuleName.EXPLORE,
]


@dataclass
class RouteOperation:
    """Gateway metadata for a single API operation."""

    method: str
    path: str
    permissions: list[str] = field(default_factory=list)
    token_cost: int = 0
    is_public: bool = False
    token_lock_timeout: int | None = None


@dataclass
class ModuleDefinition:
    """A registered backend module with its routes."""

    name: ModuleName
    backend_url: str
    openapi_hash: str | None = None
    routes: dict[str, dict[str, RouteOperation]] = field(default_factory=dict)
    """Mapping of path -> {method -> RouteOperation}"""


class ModuleRegistry:
    """Registry that discovers and resolves backend routes.

    Each backend exposes an OpenAPI schema. The registry fetches it,
    extracts paths and x-* extensions, and builds a lookup table.
    """

    def __init__(self, backends: dict[str, str]) -> None:
        """Initialize with a mapping of module_name -> backend_url."""
        self._backends = backends
        self._modules: dict[ModuleName, ModuleDefinition] = {}
        # Flat lookup: (path, method) -> ModuleName for fast resolution
        self._route_index: dict[tuple[str, str], ModuleName] = {}
        # Protects _modules and _route_index during rediscovery
        self._lock = asyncio.Lock()

    @property
    def modules(self) -> dict[ModuleName, ModuleDefinition]:
        return self._modules

    async def discover_all(self) -> None:
        """Fetch all backend schemas and build the routing table."""
        async with self._lock:
            for module_name, backend_url in self._backends.items():
                try:
                    name = ModuleName(module_name)
                except ValueError:
                    logger.warning(
                        "Unknown module name, skipping",
                        extra={"module_name": module_name},
                    )
                    continue
                await self._fetch_and_register(name, backend_url)

            self._build_route_index()
            logger.info(
                "Module discovery complete",
                extra={
                    "modules": list(self._modules.keys()),
                    "total_routes": len(self._route_index),
                },
            )

    async def _fetch_and_register(self, name: ModuleName, backend_url: str) -> None:
        """Fetch a single module's OpenAPI schema and register its routes.

        Must be called under self._lock.
        """
        url = f"{backend_url.rstrip('/')}/openapi.json"
        try:
            async with httpx.AsyncClient(timeout=10) as client:
                resp = await client.get(url)
                resp.raise_for_status()
                schema = resp.json()
        except Exception as e:
            logger.warning(
                "Failed to discover module, skipping",
                extra={"module_name": name, "url": url, "error": str(e)},
            )
            return

        module_def = ModuleDefinition(name=name, backend_url=backend_url.rstrip("/"))
        self._extract_routes(module_def, schema)
        self._modules[name] = module_def

        logger.info(
            "Discovered module",
            extra={
                "module_name": name,
                "routes": len(module_def.routes),
                "url": backend_url,
            },
        )

    async def rediscover_module(self, name: ModuleName) -> None:
        """Re-fetch a module's schema (after announce or hash change)."""
        async with self._lock:
            module = self._modules.get(name)
            if not module:
                logger.warning("Cannot rediscover unknown module", extra={"module_name": name})
                return

            await self._fetch_and_register(name, module.backend_url)
            self._build_route_index()

    def resolve(self, path: str, method: str = "GET") -> tuple[ModuleDefinition, str] | None:
        """Resolve an API path to its owning module.

        Args:
            path: The request path without /api/ prefix (e.g. "companies/123")
            method: HTTP method (GET, POST, etc.)

        Returns:
            (ModuleDefinition, backend_path) or None if no module matches.
            backend_path is the path to forward to the backend (e.g. "/api/companies/123").
            The original trailing slash is preserved to avoid 307 redirects from FastAPI.
        """
        # Keep the original path to forward as-is (preserving trailing slash)
        original_api_path = f"/api/{path}"
        # Normalize for route matching only
        api_path = original_api_path.rstrip("/") or "/api"
        method_upper = method.upper()

        # Exact match first
        key = (api_path, method_upper)
        if key in self._route_index:
            module = self._modules[self._route_index[key]]
            return module, original_api_path

        # Try prefix matching for parameterized paths like /api/companies/{id}
        # Complexity: O(n) where n = number of routes in _route_index.
        # Acceptable with current route count (<100). If this becomes a bottleneck,
        # consider a trie-based index keyed by path segments.
        best_match: tuple[ModuleName, str] | None = None
        best_length = 0

        for (route_path, route_method), module_name in self._route_index.items():
            if route_method != method_upper:
                continue
            # Convert OpenAPI path template to prefix match
            # e.g. "/api/companies/{company_id}" matches "/api/companies/123"
            static_prefix = route_path.split("{")[0]
            if not static_prefix:
                continue
            # Ensure match is on a segment boundary to prevent
            # "/api/companies-old" from matching "/api/companies/{id}"
            if not _matches_on_segment_boundary(api_path, static_prefix):
                continue
            if len(static_prefix) > best_length:
                best_match = (module_name, original_api_path)
                best_length = len(static_prefix)

        if best_match:
            module = self._modules[best_match[0]]
            return module, best_match[1]

        return None

    def resolve_operation(self, module_name: ModuleName, method: str, path: str) -> RouteOperation | None:
        """Find x-permissions, x-token-cost, x-public for a specific operation.

        Args:
            module_name: The module that owns the route
            method: HTTP method
            path: Full API path (e.g. "/api/companies")

        Returns:
            RouteOperation with metadata, or None if not found.
        """
        module = self._modules.get(module_name)
        if not module:
            return None

        normalized = _normalize_path(path)
        method_upper = method.upper()

        # Exact match
        methods = module.routes.get(normalized)
        if methods:
            return methods.get(method_upper)

        # Prefix match for parameterized routes (segment-boundary safe)
        best_op: RouteOperation | None = None
        best_length = 0
        for route_path, route_methods in module.routes.items():
            if method_upper not in route_methods:
                continue
            static_prefix = route_path.split("{")[0]
            if not static_prefix:
                continue
            if not _matches_on_segment_boundary(normalized, static_prefix):
                continue
            if len(static_prefix) > best_length:
                best_op = route_methods[method_upper]
                best_length = len(static_prefix)

        return best_op

    def _extract_routes(self, module: ModuleDefinition, schema: dict) -> None:
        """Extract routes and metadata from an OpenAPI schema."""
        paths = schema.get("paths", {})
        if not isinstance(paths, dict):
            return
        for path, methods in paths.items():
            if not isinstance(methods, dict):
                continue
            normalized_path = _normalize_path(path)
            route_methods: dict[str, RouteOperation] = {}
            for method, operation in methods.items():
                method_upper = method.upper()
                if method_upper not in _VALID_METHODS:
                    continue
                if not isinstance(operation, dict):
                    continue

                route_methods[method_upper] = RouteOperation(
                    method=method_upper,
                    path=normalized_path,
                    permissions=operation.get("x-permissions", []),
                    token_cost=operation.get("x-token-cost", 0),
                    is_public=operation.get("x-public", False),
                    token_lock_timeout=operation.get("x-token-lock-timeout"),
                )
            if route_methods:
                module.routes[normalized_path] = route_methods

    def _build_route_index(self) -> None:
        """Build flat lookup index, resolving overlaps by module priority."""
        self._route_index.clear()

        # Process modules in priority order so higher-priority wins
        for module_name in MODULE_PRIORITY:
            module = self._modules.get(module_name)
            if not module:
                continue
            for path, methods in module.routes.items():
                for method in methods:
                    key = (path, method)
                    if key not in self._route_index:
                        self._route_index[key] = module_name
                    else:
                        existing = self._route_index[key]
                        logger.debug(
                            "Route overlap: %s %s claimed by %s, already owned by %s",
                            method,
                            path,
                            module_name,
                            existing,
                        )
