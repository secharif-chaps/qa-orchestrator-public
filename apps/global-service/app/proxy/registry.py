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
import hashlib
import json
import re
import time
from dataclasses import dataclass, field
from enum import StrEnum
from functools import lru_cache

import httpx

from app.core.logging_config import get_logger

logger = get_logger(__name__)

# HTTP methods accepted for routing (uppercase)
_VALID_METHODS = {"GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"}


def _normalize_path(path: str) -> str:
    """Normalize a path: strip trailing slash for consistent matching."""
    return path.rstrip("/") or "/"


# Captures OpenAPI path parameters like "{company_id}". Parameter values cannot
# contain a slash in practice, so "[^/]+" is the right template substitution.
_PARAM_RE = re.compile(r"\{[^/}]+\}")


@lru_cache(maxsize=1024)
def _compile_template(template: str) -> tuple[re.Pattern[str], int]:
    """Turn an OpenAPI path template into a `(regex, static_char_count)` pair.

    `static_char_count` is the number of literal (non-parameter) characters in
    the template. It is used as a specificity score when several templates
    match the same request path: more literal characters wins.

    This replaces the previous `route_path.split("{")[0]` prefix matching,
    which could not distinguish between sibling parameterised sub-routes
    (e.g. `.../{id}/restore` vs `.../{id}/refresh`) — both had the same
    static prefix, so the first declared sibling swallowed its neighbours.
    """
    parts: list[str] = []
    last_end = 0
    static_chars = 0
    for match in _PARAM_RE.finditer(template):
        literal = template[last_end : match.start()]
        parts.append(re.escape(literal))
        parts.append(r"[^/]+")
        static_chars += len(literal)
        last_end = match.end()
    tail = template[last_end:]
    parts.append(re.escape(tail))
    static_chars += len(tail)
    return re.compile("^" + "".join(parts) + r"/?$"), static_chars


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
    STREAM = "stream"


# Module priority for overlap resolution (lower index = higher priority)
MODULE_PRIORITY: list[ModuleName] = [
    ModuleName.SCREEN,
    ModuleName.TARGET,
    ModuleName.EXPLORE,
    ModuleName.STREAM,
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

    _STALE_CHECK_TTL_SECONDS = 300  # Only check schema staleness every 5 minutes

    @staticmethod
    def _compute_schema_hash(schema: dict) -> str:
        """Compute a deterministic SHA-256 hash of an OpenAPI schema."""
        return hashlib.sha256(json.dumps(schema, sort_keys=True).encode()).hexdigest()

    # OpenAPI discovery config per module (path, extra headers).
    # Modules not listed here use GET /openapi.json with no extra headers.
    _OPENAPI_CONFIG: dict[str, tuple[str, dict[str, str]]] = {
        "target": ("/api/docs", {"Accept": "application/vnd.openapi+json"}),
    }

    def __init__(self, backends: dict[str, str]) -> None:
        """Initialize with a mapping of module_name -> backend_url."""
        self._backends = backends
        self._modules: dict[ModuleName, ModuleDefinition] = {}
        # Flat lookup: (path, method) -> ModuleName for fast resolution
        self._route_index: dict[tuple[str, str], ModuleName] = {}
        # Protects _modules and _route_index during rediscovery
        self._lock = asyncio.Lock()
        # TTL tracking for stale schema checks (per-instance, not shared)
        self._last_stale_check: float = 0

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

    async def _fetch_and_register(
        self, name: ModuleName, backend_url: str, prefetched_schema: dict | None = None
    ) -> None:
        """Fetch a single module's OpenAPI schema and register its routes.

        Must be called under self._lock.

        Args:
            prefetched_schema: If provided, skip the HTTP fetch and use this schema directly.
        """
        if prefetched_schema is not None:
            schema = prefetched_schema
        else:
            openapi_path, extra_headers = self._OPENAPI_CONFIG.get(name.value, ("/openapi.json", {}))
            url = f"{backend_url.rstrip('/')}{openapi_path}"
            try:
                async with httpx.AsyncClient(timeout=10) as client:
                    resp = await client.get(url, headers=extra_headers)
                    resp.raise_for_status()
                    schema = resp.json()
            except Exception as e:
                logger.warning(
                    "Failed to discover module, skipping",
                    extra={"module_name": name, "url": url, "error": str(e)},
                )
                return

        schema_hash = self._compute_schema_hash(schema)

        module_def = ModuleDefinition(name=name, backend_url=backend_url.rstrip("/"), openapi_hash=schema_hash)
        self._extract_routes(module_def, schema)
        self._modules[name] = module_def

        logger.info(
            "Discovered module",
            extra={
                "module_name": name,
                "routes": len(module_def.routes),
                "url": backend_url,
                "openapi_hash": schema_hash,
            },
        )

    async def rediscover_module(self, name: ModuleName, prefetched_schema: dict | None = None) -> None:
        """Re-fetch a module's schema (after announce or hash change).

        Also handles first-time discovery for modules that were unreachable
        at startup but have since come online and announced themselves.

        Args:
            prefetched_schema: If provided, skip the HTTP fetch and use this schema directly.
        """
        async with self._lock:
            module = self._modules.get(name)
            if module:
                backend_url = module.backend_url
            elif name.value in self._backends:
                backend_url = self._backends[name.value]
                logger.info(
                    "Late discovery for module that was unavailable at startup",
                    extra={"module_name": name},
                )
            else:
                logger.warning("Cannot rediscover unknown module", extra={"module_name": name})
                return

            await self._fetch_and_register(name, backend_url, prefetched_schema=prefetched_schema)
            self._build_route_index()

    def get_module_hash(self, name: ModuleName) -> str | None:
        """Get the stored openapi_hash for a module."""
        module = self._modules.get(name)
        return module.openapi_hash if module else None

    async def check_and_rediscover_stale(self) -> dict[str, str]:
        """Check all backends for schema changes and discover missing modules.

        For already-discovered modules: fetches the OpenAPI schema, computes
        its hash, and triggers rediscovery if the hash changed.

        For modules that failed at startup: attempts first-time discovery so
        backends that were slow to start get registered without a gateway restart.

        Both checks are gated by _STALE_CHECK_TTL_SECONDS (5 min) to avoid
        hammering backends on every Kubernetes readiness probe.

        Returns:
            dict of module_name -> action ("unchanged" | "rediscovered" | "discovered" | "error" | "skipped")
        """
        now = time.monotonic()
        if (now - self._last_stale_check) < self._STALE_CHECK_TTL_SECONDS:
            return {name.value: "skipped" for name in self._modules}
        self._last_stale_check = now

        results: dict[str, str] = {}

        # ── Attempt discovery for modules unreachable at startup ──
        for module_name in self._backends:
            try:
                name = ModuleName(module_name)
            except ValueError:
                continue
            if name in self._modules:
                continue

            await self.rediscover_module(name)
            if name in self._modules:
                results[module_name] = "discovered"
                logger.info(
                    "Late-discovered module via stale check",
                    extra={"module_name": module_name},
                )
            else:
                results[module_name] = "error"

        # ── Check already-discovered modules for schema changes ──
        async with httpx.AsyncClient(timeout=5) as client:
            for name, module in list(self._modules.items()):
                if name.value in results:
                    continue  # just discovered above, skip
                openapi_path, extra_headers = self._OPENAPI_CONFIG.get(name.value, ("/openapi.json", {}))
                url = f"{module.backend_url}{openapi_path}"
                try:
                    resp = await client.get(url, headers=extra_headers)
                    resp.raise_for_status()
                    schema = resp.json()

                    new_hash = self._compute_schema_hash(schema)

                    # Re-read current hash after await — a concurrent announce
                    # may have already updated it
                    current_module = self._modules.get(name)
                    current_hash = current_module.openapi_hash if current_module else None

                    if current_module is None or new_hash == current_hash:
                        results[name.value] = "unchanged"
                    else:
                        logger.info(
                            "Schema hash changed, rediscovering module",
                            extra={"module_name": name, "old_hash": current_hash, "new_hash": new_hash},
                        )
                        await self.rediscover_module(name, prefetched_schema=schema)
                        results[name.value] = "rediscovered"
                except Exception as e:
                    logger.warning(
                        "Failed to check schema for module",
                        extra={"module_name": name, "error": str(e)},
                    )
                    results[name.value] = "error"

        return results

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

        # Exact match (non-parameterised routes)
        methods = module.routes.get(normalized)
        if methods:
            return methods.get(method_upper)

        # Template match: compile each route template to a regex and keep the
        # most specific match. Specificity = number of literal characters in
        # the template, so `/api/companies/{id}/refresh` beats `/api/companies/{id}`
        # for the path `/api/companies/1/refresh`.
        best_op: RouteOperation | None = None
        best_score = -1
        for route_path, route_methods in module.routes.items():
            op = route_methods.get(method_upper)
            if op is None:
                continue
            pattern, static_chars = _compile_template(route_path)
            if not pattern.match(normalized):
                continue
            if static_chars > best_score:
                best_op = op
                best_score = static_chars

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

                # Coerce token_cost to int at ingestion to catch mistyped OpenAPI specs early
                raw_token_cost = operation.get("x-token-cost", 0)
                try:
                    token_cost = int(raw_token_cost)
                except (TypeError, ValueError):
                    logger.warning(
                        "Invalid x-token-cost value %r for %s %s, defaulting to 0",
                        raw_token_cost,
                        method_upper,
                        normalized_path,
                    )
                    token_cost = 0

                route_methods[method_upper] = RouteOperation(
                    method=method_upper,
                    path=normalized_path,
                    permissions=operation.get("x-permissions", []),
                    token_cost=token_cost,
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
