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

import re
import time
from collections.abc import AsyncGenerator

import httpx
from fastapi import APIRouter, Request, Response
from fastapi.responses import StreamingResponse

from app.core.auth_middleware import auth_middleware, extract_organization_info
from app.core.config import settings
from app.core.correlation import CORRELATION_HEADER
from app.core.logging_config import get_logger
from app.proxy.client import get_proxy_client, get_streaming_client
from app.proxy.registry import ModuleDefinition, ModuleName, ModuleRegistry

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
    backends = {"screen": settings.SCREEN_BASE_URL}
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


def _resolve_backend(registry: ModuleRegistry | None, path: str, method: str) -> tuple[ModuleDefinition, str]:
    """Resolve which backend should handle this request.

    Tries the registry first. If no match, falls back to screen
    (catch-all during transition period).
    """
    if registry:
        result = registry.resolve(path, method)
        if result:
            return result

    return _get_screen_fallback(), f"/api/{path}"


router = APIRouter()

# Headers to exclude from proxying
EXCLUDED_REQUEST_HEADERS = {
    # Hop-by-hop headers (RFC 2616) - must not be forwarded by proxies
    "host",
    "connection",
    "keep-alive",
    "proxy-authenticate",
    "proxy-authorization",
    "te",
    "trailers",
    "transfer-encoding",
    "upgrade",
    "content-length",  # httpx will recalculate this
    # Security: Prevent client from spoofing forwarding headers
    # Gateway sets these explicitly if needed via internal headers
    "x-forwarded-for",
    "x-forwarded-host",
    "x-forwarded-proto",
    "x-forwarded-port",
    "x-real-ip",
    "forwarded",  # RFC 7239 standard forwarding header
    # Security: Prevent proxy chain info leakage
    "via",
}

EXCLUDED_RESPONSE_HEADERS = {
    "connection",
    "keep-alive",
    "proxy-authenticate",
    "proxy-authorization",
    "te",
    "trailers",
    "transfer-encoding",
    "upgrade",
    "content-encoding",  # Let FastAPI handle compression
    "content-length",  # Will be recalculated
}


def filter_request_headers(headers: dict) -> dict:
    """Filter out hop-by-hop headers from request."""
    return {key: value for key, value in headers.items() if key.lower() not in EXCLUDED_REQUEST_HEADERS}


def filter_response_headers(headers: httpx.Headers) -> dict:
    """Filter out hop-by-hop headers from response."""
    return {key: value for key, value in headers.items() if key.lower() not in EXCLUDED_RESPONSE_HEADERS}


def has_request_body(request: Request) -> bool:
    """Check if request has a body using headers (without reading it)."""
    content_length = request.headers.get("content-length")
    transfer_encoding = request.headers.get("transfer-encoding")
    # Has body if content-length > 0 or chunked transfer encoding
    if content_length:
        try:
            return int(content_length) > 0
        except ValueError:
            return False
    return transfer_encoding == "chunked"


# Content types that require streaming with dedicated client (long-lived connections)
STREAMING_CONTENT_TYPES = {
    "text/event-stream",  # Server-Sent Events (SSE)
    "application/x-ndjson",  # Newline Delimited JSON (used by OpenAI, etc.)
    "application/stream+json",  # JSON streaming
}


def is_streaming_request(request: Request) -> bool:
    """
    Check if this request expects a streaming response.

    Detects requests that need long-lived connections with dedicated client:
    - SSE (text/event-stream)
    - NDJSON streaming (application/x-ndjson)
    - JSON streaming (application/stream+json)

    Limitations:
    - Detection is based on Accept header only (client must explicitly request streaming)
    - Backend may still return streaming response even if not requested (handled by
      bidirectional streaming in regular requests)
    """
    accept = request.headers.get("accept", "")
    accept_lower = accept.lower()
    return any(ct in accept_lower for ct in STREAMING_CONTENT_TYPES)


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
    module, backend_path = _resolve_backend(_registry, path, request.method)
    backend_url = module.backend_url

    # Phase 1: Validate JWT at gateway
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

    # Log authenticated request
    username = user.preferred_username if user else "anonymous"
    logger.info(
        f"🔐 AUTH OK: {username} → {request.method} /api/{path} → {module.name}",
        extra={"path": path, "method": request.method, "user": username, "backend": module.name},
    )

    # Folder-based access control at the gateway (before proxying to screen)
    denied = await _check_company_folder_access(path, request.method, user)
    if denied:
        return denied

    # Build the target URL
    target_path = backend_path
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
        },
    )

    try:
        if is_streaming:
            return await _handle_streaming_request(
                request=request,
                target_path=target_path,
                headers=headers,
                start_time=start_time,
                backend_url=backend_url,
            )

        return await _handle_regular_request(
            request=request,
            target_path=target_path,
            headers=headers,
            start_time=start_time,
            backend_url=backend_url,
        )

    except httpx.TimeoutException as e:
        elapsed = (time.time() - start_time) * 1000
        logger.error(
            f"⏱️ PROXY TIMEOUT after {elapsed:.0f}ms: {target_path}",
            extra={"path": target_path, "elapsed_ms": elapsed, "error": str(e)},
        )
        return Response(
            content=b'{"detail": "Backend service timeout"}',
            status_code=504,
            media_type="application/json",
        )

    except httpx.ConnectError as e:
        elapsed = (time.time() - start_time) * 1000
        logger.error(
            f"🔌 PROXY CONNECTION ERROR: {target_path}",
            extra={"path": target_path, "elapsed_ms": elapsed, "error": str(e)},
        )
        return Response(
            content=b'{"detail": "Backend service unavailable"}',
            status_code=503,
            media_type="application/json",
        )

    except Exception as e:
        elapsed = (time.time() - start_time) * 1000
        logger.exception(
            f"❌ PROXY ERROR: {target_path}",
            extra={"path": target_path, "elapsed_ms": elapsed, "error": str(e)},
        )
        return Response(
            content=b'{"detail": "Proxy error"}',
            status_code=502,
            media_type="application/json",
        )


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

    return StreamingResponse(
        stream_response_body(),
        status_code=response.status_code,
        headers=filter_response_headers(response.headers),
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

    return StreamingResponse(
        stream_generator(),
        media_type=media_type,
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
            "X-Accel-Buffering": "no",  # Disable nginx buffering
        },
    )
