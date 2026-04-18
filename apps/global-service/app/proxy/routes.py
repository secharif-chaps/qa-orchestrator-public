"""
Proxy routes for forwarding /api/* requests to backend services.

This module implements a transparent proxy that:
- Resolves the target backend via ModuleRegistry (OpenAPI-based autodiscovery)
- Falls back to Screen for unresolved routes (transition period)
- Validates user JWT tokens at the gateway using Keycloak
- Creates short-lived internal JWTs for secure backend communication
- Forwards all /api/* requests with internal Authorization header
- Preserves headers, body, query params
- Preserves response status, headers, body
- Streams request AND response bodies (bidirectional streaming, memory-efficient)
- Handles streaming responses (SSE, NDJSON, JSON streaming) with dedicated client

Security:
- External JWT validated against Keycloak public key
- Internal JWT (60s TTL) signed with shared secret
- Backend verifies internal JWT, no re-validation with Keycloak needed
"""

from __future__ import annotations

import ipaddress
import json
import re
import time
from collections.abc import AsyncGenerator
from urllib.parse import urlparse, urlunparse
from uuid import UUID

import httpx
from fastapi import APIRouter, Request, Response
from fastapi.responses import StreamingResponse

from app.core.auth_middleware import GatewayUser, auth_middleware, extract_organization_info
from app.core.config import settings
from app.core.correlation import CORRELATION_HEADER
from app.core.logging_config import get_logger
from app.database import get_global_db_context
from app.models.organization import ModuleName as DBModuleName
from app.models.organization import TokenLock
from app.proxy.client import get_proxy_client, get_streaming_client
from app.proxy.registry import ModuleDefinition, ModuleName, ModuleRegistry, RouteOperation
from app.proxy.token_lock import InsufficientTokensError, TokenLockManager, strip_internal_headers
from app.proxy.utils import (
    EXCLUDED_REQUEST_HEADERS,
    EXCLUDED_RESPONSE_HEADERS,
    STREAMING_CONTENT_TYPES,
    filter_request_headers,
    filter_response_headers,
    has_request_body,
    is_streaming_request,
)

# Re-export for backward compatibility (existing tests import from this module)
__all__ = [
    "filter_request_headers",
    "filter_response_headers",
    "has_request_body",
    "is_streaming_request",
    "rewrite_location_header",
    "EXCLUDED_REQUEST_HEADERS",
    "EXCLUDED_RESPONSE_HEADERS",
    "STREAMING_CONTENT_TYPES",
]


def rewrite_location_header(
    location: str,
    backend_url: str | None,
    request: Request,
) -> str:
    """Rewrite a Location response header from internal backend URL to the public URL.

    Handles absolute internal URLs, relative paths, and X-Forwarded-* headers
    from trusted reverse proxies (nginx, Traefik).

    Args:
        location:    The Location header value returned by the backend.
        backend_url: The backend origin URL (e.g. "http://screen:8000").
                     Used to detect and strip the internal origin.
        request:     The incoming FastAPI request (provides public scheme/host).

    Returns:
        The rewritten location string, or the original if no rewrite is needed.
    """
    if not location:
        return location

    # ── Determine if the immediate client is a trusted reverse proxy ──────────
    is_trusted_proxy = False
    client_ip: str | None = None
    try:
        if request.client:
            client_ip = request.client.host
    except Exception:
        pass

    trusted_proxies_str = getattr(settings, "TRUSTED_PROXIES", "") or ""
    if client_ip and trusted_proxies_str:
        try:
            addr = ipaddress.ip_address(client_ip)
            for cidr in trusted_proxies_str.split(","):
                cidr = cidr.strip()
                if cidr and addr in ipaddress.ip_network(cidr, strict=False):
                    is_trusted_proxy = True
                    break
        except (ValueError, TypeError):
            pass

    # ── Resolve the public scheme ──────────────────────────────────────────────
    public_scheme = request.url.scheme
    if is_trusted_proxy:
        forwarded_proto = request.headers.get("x-forwarded-proto", "").lower()
        if forwarded_proto in ("http", "https"):
            public_scheme = forwarded_proto

    # ── Resolve the public netloc ─────────────────────────────────────────────
    public_netloc = request.url.netloc
    trusted_hosts_pattern = getattr(settings, "TRUSTED_HOSTS", "") or ""
    if is_trusted_proxy and trusted_hosts_pattern:
        forwarded_host = request.headers.get("x-forwarded-host", "")
        if forwarded_host:
            try:
                if re.fullmatch(trusted_hosts_pattern, forwarded_host):
                    public_netloc = forwarded_host
            except re.error:
                pass

    # ── Relative path → prepend public origin ─────────────────────────────────
    if location.startswith("/"):
        parsed = urlparse(location)
        return urlunparse((public_scheme, public_netloc, parsed.path, parsed.params, parsed.query, parsed.fragment))

    # ── Absolute URL: only rewrite if it matches the backend origin ───────────
    if not backend_url:
        return location

    normalized_backend = backend_url.rstrip("/")
    if not normalized_backend:
        return location

    if location.startswith(normalized_backend):
        parsed = urlparse(location)
        return urlunparse((public_scheme, public_netloc, parsed.path, parsed.params, parsed.query, parsed.fragment))

    # External URL or non-matching backend — leave unchanged
    return location


logger = get_logger(__name__)

# Module registry — initialized at startup via init_module_registry()
_registry: ModuleRegistry | None = None


def get_module_registry() -> ModuleRegistry | None:
    """Get the global module registry (None if not yet initialized)."""
    return _registry


async def init_module_registry() -> ModuleRegistry:
    """Initialize the module registry with configured backends.

    Called at startup from main.py. Discovers all backend schemas.
    """
    global _registry
    backends = {
        "screen": settings.SCREEN_BASE_URL,
        "target": settings.TARGET_BASE_URL,
        "stream": settings.STREAM_BASE_URL,
    }
    _registry = ModuleRegistry(backends=backends)
    await _registry.discover_all()
    return _registry


# Pre-computed fallback module to avoid allocating a dataclass per request
_screen_fallback: ModuleDefinition | None = None


def _get_screen_fallback() -> ModuleDefinition:
    """Return a cached screen fallback module (lazy init)."""
    global _screen_fallback
    if _screen_fallback is None:
        _screen_fallback = ModuleDefinition(
            name=ModuleName.SCREEN,
            backend_url=settings.SCREEN_BASE_URL.rstrip("/"),
        )
    return _screen_fallback


def _resolve_backend(
    registry: ModuleRegistry | None, path: str, method: str
) -> tuple[ModuleDefinition, str, RouteOperation | None]:
    """Resolve which backend should handle this request.

    Tries the registry first. If no match, falls back to screen
    (catch-all during transition period).

    Returns:
        (module_def, backend_path, route_operation)
    """
    if registry:
        result = registry.resolve(path, method)
        if result:
            module_def, backend_path = result
            route_op = registry.resolve_operation(module_def.name, method, backend_path)
            return module_def, backend_path, route_op

    return _get_screen_fallback(), f"/api/{path}", None


# ── Module gate: check if module is enabled for user's organization ──

# Modules that are always active and skip the per-org enablement check
_ALWAYS_ACTIVE_MODULES: frozenset[ModuleName] = frozenset({ModuleName.STREAM})

# Cache: (org_id, module_name) -> (enabled: bool, timestamp: float)
_module_enabled_cache: dict[tuple[str, str], tuple[bool, float]] = {}
_MODULE_GATE_CACHE_TTL = 30  # seconds


async def _check_module_enabled(module_name: ModuleName, user: object) -> Response | None:
    """Check if the resolved module is enabled for the user's organization.

    Returns a 403 Response if the module is disabled, None if enabled (allow).
    Skips the check for always-active modules (e.g. stream) and when
    user has no organization context (e.g., service accounts).

    Uses a TTL cache to avoid querying the DB on every request.
    Fails open on DB errors (logs warning, allows request).
    """
    if module_name in _ALWAYS_ACTIVE_MODULES:
        return None

    org_id, _ = extract_organization_info(user)
    if not org_id:
        return None

    cache_key = (org_id, module_name.value)
    now = time.time()

    # Check cache
    cached = _module_enabled_cache.get(cache_key)
    if cached and (now - cached[1]) < _MODULE_GATE_CACHE_TTL:
        enabled = cached[0]
    else:
        # Query DB
        try:
            from sqlalchemy import select

            from app.database import get_global_db_context
            from app.models.organization import OrganizationModule

            async with get_global_db_context() as db:
                stmt = select(OrganizationModule).where(
                    OrganizationModule.organization_id == org_id,
                    OrganizationModule.module_name == module_name.value,
                )
                result = await db.execute(stmt)
                record = result.scalar_one_or_none()
                enabled = record.enabled if record else False

            _module_enabled_cache[cache_key] = (enabled, now)
        except Exception as e:
            logger.warning(
                "Module gate DB check failed, allowing request",
                extra={"module_name": module_name, "org_id": org_id, "error": str(e)},
            )
            return None

    if not enabled:
        logger.warning(
            "Module gate: access denied",
            extra={"module_name": module_name, "org_id": org_id},
        )
        return Response(
            content=f'{{"detail":"Module \'{module_name.value}\' not enabled for your organization"}}'.encode(),
            status_code=403,
            media_type="application/json",
        )

    return None


router = APIRouter()


# Pattern to match GET /companies/{id} (single company access)
_COMPANY_BY_ID_PATTERN = re.compile(r"^companies/(\d+)$")


async def _check_company_folder_access(path: str, method: str, user) -> Response | None:
    """Check folder-based access for company endpoints at the gateway.

    Returns a 404 Response if access is denied, None if access is granted.
    Only applies to GET /companies/{id} requests from authenticated users.
    """
    if method != "GET" or not user:
        return None

    match = _COMPANY_BY_ID_PATTERN.match(path)
    if not match:
        return None

    company_id = int(match.group(1))
    org_id, _ = extract_organization_info(user)
    if not org_id:
        return None

    roles: list[str] = []
    if user.realm_access:
        roles = user.realm_access.get("roles", [])

    # Managers bypass folder access check
    if any(r in roles for r in ("admin.organizations", "organization.manage")):
        return None

    try:
        from app.database import get_global_db_context
        from app.services.folder import FolderService

        async with get_global_db_context() as db:
            has_access = await FolderService.user_has_company_access(
                db=db,
                company_id=company_id,
                user_id=user.sub,
                organization_id=org_id,
                username=user.preferred_username or "",
                user_roles=roles,
            )

        if not has_access:
            logger.warning(
                f"🚫 Folder access denied: {user.preferred_username} → company {company_id}",
            )
            return Response(
                content=b'{"detail": "Company not found"}',
                status_code=404,
                media_type="application/json",
            )
    except Exception as e:
        # Don't block on access check failures — log and allow
        logger.error(f"Folder access check failed, allowing request: {e}")

    return None


def _check_permission_gate(
    path: str, method: str, user: GatewayUser | None, registry: ModuleRegistry | None, module: ModuleDefinition,
) -> Response | None:
    """Fast-reject requests where user lacks required permissions (x-permissions).

    Returns a 403 Response if the user doesn't have any of the required permissions,
    None if access is granted. Uses OR logic: any matching role is sufficient.
    """
    # Public routes (no user) — already validated by auth gate
    if not user:
        return None

    # No registry — fail-open (backend still does its own checks)
    if not registry:
        logger.warning(
            "⚠️ Permission gate bypassed: registry not initialized",
            extra={"path": path, "method": method},
        )
        return None

    backend_path = f"/api/{path}"
    operation = registry.resolve_operation(module.name, method, backend_path)

    # No operation metadata or no permissions declared — auth alone is enough
    if not operation or not operation.permissions:
        return None

    # Extract user roles from Keycloak JWT
    user_roles: list[str] = []
    if user.realm_access:
        user_roles = user.realm_access.get("roles", [])

    # OR logic: user needs at least one of the declared permissions
    if any(role in user_roles for role in operation.permissions):
        return None

    logger.warning(
        f"🚫 PERMISSION DENIED: {user.preferred_username} → {method} /api/{path} "
        f"(requires any of {operation.permissions}, user has no matching role)",
        extra={"path": path, "method": method, "user": user.preferred_username},
    )
    return Response(
        content=b'{"detail": "Insufficient permissions"}',
        status_code=403,
        media_type="application/json",
    )


@router.api_route(
    "/{path:path}",
    methods=["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"],
    include_in_schema=False,
)
async def proxy_request(request: Request, path: str) -> Response:
    """
    Proxy requests to the appropriate backend service.

    Pipeline:
    1. Resolve backend via ModuleRegistry (fallback: screen)
    2. Validate JWT token at the gateway (returns 401 if invalid)
    3. Add internal trust headers for the backend
    4. Forward request preserving headers, body, query parameters
    5. Handle streaming responses (SSE)
    """
    start_time = time.time()

    # Phase 0: Resolve which backend handles this path
    module, backend_path, route_op = _resolve_backend(_registry, path, request.method)
    backend_url = module.backend_url

    # Phase 0b: Check if the operation is marked as public (x-public: true in OpenAPI).
    # Trust boundary: backends are trusted internal services that control their own auth
    # policy via x-public. If a backend marks an endpoint as public, the gateway skips
    # JWT validation entirely. This is by design — backends own their security posture.
    route_is_public = False
    if _registry:
        operation = _registry.resolve_operation(module.name, request.method, backend_path)
        if operation and operation.is_public:
            route_is_public = True

    # Phase 1: Validate JWT at gateway (skip if route is public)
    if route_is_public:
        logger.info(
            "Public route (x-public), skipping auth",
            extra={"path": path, "method": request.method, "target_module": module.name},
        )
        user = None
        internal_headers = {}
    else:
        is_valid, user, internal_headers = await auth_middleware.validate_request(request, path)

        if not is_valid:
            logger.warning(
                f"🔒 AUTH REJECTED: {request.method} /api/{path}",
                extra={"path": path, "method": request.method},
            )
            return Response(
                content=b'{"detail": "Not authenticated"}',
                status_code=401,
                media_type="application/json",
            )

    # Guard: authenticated routes must have internal headers
    # If user is authenticated but internal headers are empty, the backend
    # would receive an unauthenticated request — reject with 502
    if user and not internal_headers:
        logger.error(
            f"🚫 PROXY BLOCKED: internal headers empty for authenticated user "
            f"{user.preferred_username} on {request.method} /api/{path}. "
            "Check INTERNAL_JWT_SECRET configuration.",
            extra={"path": path, "method": request.method},
        )
        return Response(
            content=b'{"detail": "Internal gateway error: cannot forward authenticated request"}',
            status_code=502,
            media_type="application/json",
        )

    # Log request
    username = user.preferred_username if user else "anonymous"
    logger.info(
        f"🔐 {'PUBLIC' if route_is_public else 'AUTH OK'}: {username} → {request.method} /api/{path} → {module.name}",
        extra={
            "path": path,
            "method": request.method,
            "user": username,
            "backend": module.name,
            "public": route_is_public,
        },
    )

    # Permission gate: check x-permissions from OpenAPI spec (fast-reject, no I/O)
    denied = _check_permission_gate(path, request.method, user, _registry, module)
    if denied:
        return denied

    # Module gate: check if module is enabled for user's organization
    if not route_is_public and user:
        module_denied = await _check_module_enabled(module.name, user)
        if module_denied:
            return module_denied

    # Folder-based access control at the gateway (DB query, after permission gate)
    denied = await _check_company_folder_access(path, request.method, user)
    if denied:
        return denied

    # Build the target URL — preserve trailing slash from original request
    # (Starlette's {path:path} strips trailing slashes from the captured parameter)
    target_path = backend_path
    if request.url.path.endswith("/") and not target_path.endswith("/"):
        target_path += "/"
    if request.url.query:
        target_path = f"{target_path}?{request.url.query}"

    # Prepare headers: filter hop-by-hop + add internal trust headers
    headers = filter_request_headers(dict(request.headers))
    # Remove any existing Authorization header (case-insensitive) before adding internal JWT
    for key in list(headers.keys()):
        if key.lower() == "authorization":
            del headers[key]
    headers.update(internal_headers)

    # Propagate correlation ID to backend
    correlation_id = getattr(request.state, "correlation_id", None)
    if correlation_id:
        headers[CORRELATION_HEADER] = correlation_id

    # Phase 2: Token lock — reserve tokens before proxying
    token_lock_id: str | None = None
    token_cost = route_op.token_cost if route_op else 0

    if token_cost > 0 and user:
        org_id, _ = extract_organization_info(user)
        if org_id:
            try:
                async with get_global_db_context() as db:
                    manager = TokenLockManager(db)
                    lock = await manager.lock(
                        organization_id=org_id,
                        amount=token_cost,
                        module=DBModuleName(module.name.value),
                        user_id=user.sub,
                        correlation_id=correlation_id or "",
                        lock_ttl=route_op.token_lock_timeout if route_op else None,
                    )
                    token_lock_id = str(lock.id)
            except InsufficientTokensError as e:
                return Response(
                    content=json.dumps({
                        "detail": "Insufficient tokens",
                        "required": e.required,
                        "current_balance": e.current_balance,
                    }).encode(),
                    status_code=402,
                    media_type="application/json",
                )

    # Check if request has body (without reading it into memory)
    request_has_body = has_request_body(request)

    # Check if streaming request (SSE, NDJSON, etc.)
    is_streaming = is_streaming_request(request)

    # Log the request
    logger.info(
        f"🔄 PROXY → {module.name} {request.method} {target_path}",
        extra={
            "method": request.method,
            "path": target_path,
            "backend": module.name,
            "backend_url": backend_url,
            "has_body": request_has_body,
            "is_streaming": is_streaming,
            "token_lock_id": token_lock_id,
        },
    )

    try:
        if is_streaming:
            response = await _handle_streaming_request(
                request=request,
                target_path=target_path,
                headers=headers,
                start_time=start_time,
                backend_url=backend_url,
            )
        else:
            response = await _handle_regular_request(
                request=request,
                target_path=target_path,
                headers=headers,
                start_time=start_time,
                backend_url=backend_url,
            )

        # Phase 3: Confirm or release token lock based on response
        if token_lock_id:
            await _settle_token_lock(
                token_lock_id=token_lock_id,
                status_code=response.status_code,
                response_headers=dict(response.headers) if hasattr(response, "headers") else {},
            )

        return response

    except Exception as e:
        # Release token lock on any proxy error
        if token_lock_id:
            await _release_token_lock(token_lock_id)

        elapsed = (time.time() - start_time) * 1000

        if isinstance(e, httpx.TimeoutException):
            logger.error(
                f"⏱️ PROXY TIMEOUT after {elapsed:.0f}ms: {target_path}",
                extra={"path": target_path, "elapsed_ms": elapsed, "error": str(e)},
            )
            return Response(
                content=b'{"detail": "Backend service timeout"}',
                status_code=504,
                media_type="application/json",
            )

        if isinstance(e, httpx.ConnectError):
            logger.error(
                f"🔌 PROXY CONNECTION ERROR: {target_path}",
                extra={"path": target_path, "elapsed_ms": elapsed, "error": str(e)},
            )
            return Response(
                content=b'{"detail": "Backend service unavailable"}',
                status_code=503,
                media_type="application/json",
            )

        logger.exception(
            f"❌ PROXY ERROR: {target_path}",
            extra={"path": target_path, "elapsed_ms": elapsed, "error": str(e)},
        )
        return Response(
            content=b'{"detail": "Proxy error"}',
            status_code=502,
            media_type="application/json",
        )


async def _settle_token_lock(
    token_lock_id: str,
    status_code: int,
    response_headers: dict,
) -> None:
    """Confirm or release a token lock based on backend response."""
    try:
        async with get_global_db_context() as db:
            lock = await db.get(TokenLock, UUID(token_lock_id))
            if not lock:
                logger.error(f"Token lock {token_lock_id} not found for settlement")
                return

            manager = TokenLockManager(db)

            if status_code < 400:
                # Success — confirm the lock
                cost_override_str = response_headers.get("x-token-cost-override")
                reference_id = response_headers.get("x-token-reference-id")
                cost_override = int(cost_override_str) if cost_override_str is not None else None
                await manager.confirm(lock, cost_override=cost_override, reference_id=reference_id)
            else:
                # Error — release the lock
                await manager.release(lock)
    except Exception:
        logger.exception(f"Failed to settle token lock {token_lock_id}")


async def _release_token_lock(token_lock_id: str) -> None:
    """Release a token lock (on proxy error/timeout)."""
    try:
        async with get_global_db_context() as db:
            lock = await db.get(TokenLock, UUID(token_lock_id))
            if lock:
                manager = TokenLockManager(db)
                await manager.release(lock)
    except Exception:
        logger.exception(f"Failed to release token lock {token_lock_id}")


async def _handle_regular_request(
    request: Request,
    target_path: str,
    headers: dict,
    start_time: float,
    backend_url: str | None = None,
) -> Response:
    """
    Handle regular requests with full bidirectional streaming.

    Both request and response bodies are streamed without loading into RAM.
    This is memory-efficient for large file uploads AND large file downloads.
    """
    client = await get_proxy_client(backend_url)

    # Build the httpx request with streaming body
    backend_request = client.build_request(
        method=request.method,
        url=target_path,
        headers=headers,
        content=request.stream(),
    )

    # Send request and get streaming response (don't use context manager)
    response = await client.send(backend_request, stream=True)

    # Log the response
    elapsed = (time.time() - start_time) * 1000
    logger.info(
        f"🔄 PROXY ← {response.status_code} ({elapsed:.0f}ms)",
        extra={
            "status_code": response.status_code,
            "elapsed_ms": elapsed,
            "path": target_path,
        },
    )

    # Create streaming response generator with proper cleanup
    async def stream_response_body() -> AsyncGenerator[bytes, None]:
        """Stream response body and ensure proper cleanup."""
        try:
            async for chunk in response.aiter_bytes():
                yield chunk
        finally:
            await response.aclose()
            logger.debug(f"🔄 PROXY response stream closed: {target_path}")

    response_headers = filter_response_headers(response.headers)
    # Strip internal token-lock headers so they don't leak to the client (case-insensitive)
    response_headers = strip_internal_headers(response_headers)

    # Rewrite Location header: replace internal backend URL with public URL
    # e.g. http://screen:8000/api/companies/ → https://exemple.chapsmind.com/api/companies/
    location = response_headers.get("location")
    if location:
        response_headers["location"] = rewrite_location_header(location, backend_url, request)

    return StreamingResponse(
        stream_response_body(),
        status_code=response.status_code,
        headers=response_headers,
        media_type=response.headers.get("content-type"),
    )


async def _handle_streaming_request(
    request: Request,
    target_path: str,
    headers: dict,
    start_time: float,
    backend_url: str | None = None,
) -> Response:
    """
    Handle streaming requests (SSE, NDJSON, etc.) with dedicated client.

    Uses a dedicated streaming client with longer timeouts for long-lived connections.
    Supports: text/event-stream (SSE), application/x-ndjson, application/stream+json.
    """
    # Determine the streaming content type from Accept header
    accept = request.headers.get("accept", "text/event-stream").lower()
    if "application/x-ndjson" in accept:
        media_type = "application/x-ndjson"
    elif "application/stream+json" in accept:
        media_type = "application/stream+json"
    else:
        media_type = "text/event-stream"

    logger.info(f"📡 STREAM PROXY → {request.method} {target_path} ({media_type})")

    # Read the body upfront since request.stream() can only be consumed once
    # and we need it inside the generator. Streaming requests typically have small bodies.
    body = await request.body()

    async def stream_generator() -> AsyncGenerator[bytes, None]:
        """Generate streaming events from backend."""
        async with get_streaming_client(backend_url) as client:
            try:
                async with client.stream(
                    method=request.method,
                    url=target_path,
                    headers=headers,
                    content=body if body else None,
                ) as response:
                    elapsed = (time.time() - start_time) * 1000
                    logger.info(
                        f"📡 STREAM CONNECTED ({elapsed:.0f}ms): {target_path}",
                        extra={"status_code": response.status_code},
                    )

                    async for chunk in response.aiter_bytes():
                        yield chunk

            except Exception as e:
                logger.error(f"📡 STREAM ERROR: {e}")
                # Send error in appropriate format
                if media_type == "text/event-stream":
                    yield f"event: error\ndata: {str(e)}\n\n".encode()
                else:
                    # NDJSON error format
                    yield f'{{"error": "{str(e)}"}}\n'.encode()

        logger.info(f"📡 STREAM CLOSED: {target_path}")

    # Strip internal token-lock headers defensively.
    # Currently streaming responses don't forward backend headers, but this
    # protects against future changes that might start forwarding them.
    streaming_headers = strip_internal_headers({
        "Cache-Control": "no-cache",
        "Connection": "keep-alive",
        "X-Accel-Buffering": "no",  # Disable nginx buffering
    })

    return StreamingResponse(
        stream_generator(),
        media_type=media_type,
        headers=streaming_headers,
    )
