"""
Target proxy — routes /api/target/* requests to the Target Symfony backend.

Pipeline per request:
1. Validate Keycloak JWT at the gateway (401 if missing/invalid)
2. Extract org_id from the JWT organization claim
3. Check OrganizationModule(org_id, TARGET, enabled=True) with 60s TTL cache (403 if disabled)
4. Emit an Internal JWT with user context (carried inside internal_headers)
5. Strip the /target/ prefix (handled by FastAPI routing) and forward to TARGET_BASE_URL/api/{path}
6. Stream the response back to the client

Security notes:
- The Keycloak JWT is validated against the Keycloak public key (RS256)
- The Internal JWT is short-lived (60s, HS256) and signed with INTERNAL_JWT_SECRET
- The module check fails closed: if the DB is unreachable, access is denied
- /api/target/* is never in PUBLIC_ROUTES, so user is always set when is_valid=True
"""

from __future__ import annotations

import time
from collections.abc import AsyncGenerator

import httpx
from cachetools import TTLCache
from fastapi import APIRouter, Request, Response
from fastapi.responses import StreamingResponse
from sqlalchemy import select

from app.core.auth_middleware import auth_middleware, extract_organization_info
from app.core.config import settings
from app.core.correlation import CORRELATION_HEADER
from app.core.logging_config import get_logger
from app.database import get_global_db_context
from app.models.organization import ModuleName, OrganizationModule
from app.proxy.client import get_proxy_client, get_streaming_client
from app.proxy.utils import filter_request_headers, filter_response_headers, is_streaming_request

logger = get_logger(__name__)

router = APIRouter()

# In-memory TTL cache for module-enabled checks (per org_id, TTL 60s)
_module_cache: TTLCache = TTLCache(maxsize=1000, ttl=60)


async def _is_target_enabled_for_org(org_id: str) -> bool:
    """Return True if the TARGET module is enabled for the given organization.

    Result is cached per org_id for 60 seconds to avoid repeated DB queries.
    Fails closed: returns False if the database is unreachable or org_id is empty.
    """
    if not org_id:
        return False

    cache_key = f"target_module_{org_id}"
    if cache_key in _module_cache:
        return _module_cache[cache_key]

    try:
        async with get_global_db_context() as db:
            result = await db.execute(
                select(OrganizationModule).where(
                    OrganizationModule.organization_id == org_id,
                    OrganizationModule.module_name == ModuleName.TARGET,
                    OrganizationModule.enabled.is_(True),
                )
            )
            enabled = result.scalar_one_or_none() is not None
    except Exception as e:
        logger.error("Target module check DB error for org %s: %s", org_id, e)
        return False

    _module_cache[cache_key] = enabled
    return enabled


@router.api_route(
    "/{path:path}",
    methods=["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"],
    include_in_schema=False,
)
async def target_proxy(request: Request, path: str) -> Response:
    """Forward /api/target/* to the Target Symfony backend at TARGET_BASE_URL.

    The /target/ prefix is already stripped by FastAPI routing (router is mounted
    at /api/target), so `path` contains only the downstream path segment.
    Example: GET /api/target/watch_files  →  forward to http://target:8000/api/watch_files
    """
    start_time = time.time()

    # Step 1 — Validate Keycloak JWT
    is_valid, user, internal_headers = await auth_middleware.validate_request(
        request, f"target/{path}"
    )
    if not is_valid:
        logger.warning(
            "TARGET AUTH REJECTED: %s /api/target/%s",
            request.method,
            path,
            extra={"path": path, "method": request.method},
        )
        return Response(
            content=b'{"detail": "Not authenticated"}',
            status_code=401,
            media_type="application/json",
        )

    if user is None:
        return Response(content=b'{"detail": "Unauthorized"}', status_code=401, media_type="application/json")

    # Step 2 — Module check: TARGET must be enabled for the organization
    org_id, _ = extract_organization_info(user)
    if not await _is_target_enabled_for_org(org_id):
        logger.warning(
            "TARGET MODULE DISABLED: %s (org=%s) → %s /api/target/%s",
            user.preferred_username,
            org_id,
            request.method,
            path,
            extra={"org_id": org_id, "path": path, "method": request.method},
        )
        return Response(
            content=b'{"detail": "Module not enabled for your organization"}',
            status_code=403,
            media_type="application/json",
        )

    logger.info(
        "TARGET AUTH OK: %s (org=%s) → %s /api/target/%s",
        user.preferred_username,
        org_id,
        request.method,
        path,
        extra={"user": user.preferred_username, "org_id": org_id, "path": path, "method": request.method},
    )

    # Step 3 — Build downstream path (prefix already stripped by FastAPI routing)
    backend_path = f"/api/{path}"
    if request.url.query:
        backend_path = f"{backend_path}?{request.url.query}"

    # Step 4 — Prepare headers: strip hop-by-hop + replace Authorization with Internal JWT
    headers = filter_request_headers(dict(request.headers))
    for key in list(headers.keys()):
        if key.lower() == "authorization":
            del headers[key]
    headers.update(internal_headers)

    correlation_id = getattr(request.state, "correlation_id", None)
    if correlation_id:
        headers[CORRELATION_HEADER] = correlation_id

    logger.info(
        "TARGET PROXY → %s %s",
        request.method,
        backend_path,
        extra={"method": request.method, "path": backend_path, "backend_url": settings.TARGET_BASE_URL},
    )

    # Step 5 — Forward to Target
    try:
        if is_streaming_request(request):
            return await _handle_streaming(request, backend_path, headers, start_time)
        return await _handle_regular(request, backend_path, headers, start_time)

    except httpx.TimeoutException:
        elapsed = (time.time() - start_time) * 1000
        logger.error(
            "TARGET PROXY TIMEOUT after %.0fms: %s",
            elapsed,
            backend_path,
            extra={"elapsed_ms": elapsed, "path": backend_path},
        )
        return Response(
            content=b'{"detail": "Backend service timeout"}',
            status_code=504,
            media_type="application/json",
        )
    except httpx.ConnectError:
        logger.error(
            "TARGET CONNECTION ERROR: %s",
            backend_path,
            extra={"path": backend_path},
        )
        return Response(
            content=b'{"detail": "Backend service unavailable"}',
            status_code=503,
            media_type="application/json",
        )
    except Exception as e:
        logger.exception(
            "TARGET PROXY ERROR: %s — %s",
            backend_path,
            e,
            extra={"path": backend_path},
        )
        return Response(
            content=b'{"detail": "Proxy error"}',
            status_code=502,
            media_type="application/json",
        )


async def _handle_regular(
    request: Request,
    target_path: str,
    headers: dict,
    start_time: float,
) -> Response:
    """Forward a regular (non-streaming) request to Target with bidirectional body streaming."""
    client = await get_proxy_client(settings.TARGET_BASE_URL)

    backend_request = client.build_request(
        method=request.method,
        url=target_path,
        headers=headers,
        content=request.stream(),
    )
    response = await client.send(backend_request, stream=True)

    elapsed = (time.time() - start_time) * 1000
    logger.info(
        "TARGET PROXY ← %s (%.0fms)",
        response.status_code,
        elapsed,
        extra={"status_code": response.status_code, "elapsed_ms": elapsed, "path": target_path},
    )

    async def stream_body() -> AsyncGenerator[bytes, None]:
        try:
            async for chunk in response.aiter_bytes():
                yield chunk
        finally:
            await response.aclose()

    return StreamingResponse(
        stream_body(),
        status_code=response.status_code,
        headers=filter_response_headers(response.headers),
        media_type=response.headers.get("content-type"),
    )


async def _handle_streaming(
    request: Request,
    target_path: str,
    headers: dict,
    start_time: float,
) -> Response:
    """Forward a streaming request (SSE / NDJSON) to Target using a dedicated long-lived client."""
    accept = request.headers.get("accept", "text/event-stream").lower()
    if "application/x-ndjson" in accept:
        media_type = "application/x-ndjson"
    elif "application/stream+json" in accept:
        media_type = "application/stream+json"
    else:
        media_type = "text/event-stream"

    # Read body upfront: request.stream() can only be consumed once
    body = await request.body()

    async def stream_generator() -> AsyncGenerator[bytes, None]:
        async with get_streaming_client(settings.TARGET_BASE_URL) as client:
            try:
                async with client.stream(
                    method=request.method,
                    url=target_path,
                    headers=headers,
                    content=body if body else None,
                ) as response:
                    elapsed = (time.time() - start_time) * 1000
                    logger.info(
                        "TARGET STREAM CONNECTED (%.0fms): %s",
                        elapsed,
                        target_path,
                        extra={"elapsed_ms": elapsed, "path": target_path},
                    )
                    async for chunk in response.aiter_bytes():
                        yield chunk
            except Exception as e:
                logger.error("TARGET STREAM ERROR: %s", e, extra={"path": target_path})
                if media_type == "text/event-stream":
                    yield b"event: error\ndata: Proxy error\n\n"
                else:
                    yield b'{"error": "Proxy error"}\n'

        logger.info("TARGET STREAM CLOSED: %s", target_path, extra={"path": target_path})

    return StreamingResponse(
        stream_generator(),
        media_type=media_type,
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
            "X-Accel-Buffering": "no",  # Disable nginx buffering for SSE
        },
    )
