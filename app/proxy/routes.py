"""
Proxy routes for forwarding /api/* requests to the monolith (Screen service).

This module implements a transparent proxy that:
- Validates user JWT tokens at the gateway using Keycloak
- Creates short-lived internal JWTs for secure backend communication
- Forwards all /api/* requests with internal Authorization header
- Preserves headers, body, query params
- Preserves response status, headers, body
- Streams request AND response bodies (bidirectional streaming, memory-efficient)
- Handles Server-Sent Events (SSE) with dedicated streaming client
- Logs requests/responses for debugging

Security:
- External JWT validated against Keycloak public key
- Internal JWT (60s TTL) signed with shared secret
- Backend verifies internal JWT, no re-validation with Keycloak needed
"""

import time
from typing import AsyncGenerator

import httpx
from fastapi import APIRouter, Request, Response
from fastapi.responses import StreamingResponse

from app.proxy.client import get_proxy_client, get_streaming_client
from app.core.logging_config import get_logger
from app.core.auth_middleware import auth_middleware

logger = get_logger(__name__)

router = APIRouter()

# Headers to exclude from proxying (hop-by-hop headers)
EXCLUDED_REQUEST_HEADERS = {
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
    return {
        key: value
        for key, value in headers.items()
        if key.lower() not in EXCLUDED_REQUEST_HEADERS
    }


def filter_response_headers(headers: httpx.Headers) -> dict:
    """Filter out hop-by-hop headers from response."""
    return {
        key: value
        for key, value in headers.items()
        if key.lower() not in EXCLUDED_RESPONSE_HEADERS
    }


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


def is_sse_request(request: Request) -> bool:
    """Check if this is a request for Server-Sent Events."""
    accept = request.headers.get("accept", "")
    return "text/event-stream" in accept


@router.api_route(
    "/{path:path}",
    methods=["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"],
    include_in_schema=False,
)
async def proxy_request(request: Request, path: str) -> Response:
    """
    Proxy all requests to the backend monolith.

    This endpoint:
    1. Validates JWT token at the gateway (returns 401 if invalid)
    2. Adds internal trust headers for the backend
    3. Forwards all HTTP methods to the backend service
    4. Preserves headers, body, query parameters
    5. Handles streaming responses (SSE)
    """
    start_time = time.time()

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

    # Log authenticated request
    username = user.preferred_username if user else "anonymous"
    logger.info(
        f"🔐 AUTH OK: {username} → {request.method} /api/{path}",
        extra={"path": path, "method": request.method, "user": username},
    )

    # Build the target URL
    target_path = f"/api/{path}"
    if request.url.query:
        target_path = f"{target_path}?{request.url.query}"

    # Prepare headers: filter hop-by-hop + add internal trust headers
    headers = filter_request_headers(dict(request.headers))
    # Remove any existing Authorization header (case-insensitive) before adding internal JWT
    # Python dicts are case-sensitive, but HTTP headers are case-insensitive
    for key in list(headers.keys()):
        if key.lower() == "authorization":
            del headers[key]
    headers.update(internal_headers)  # Add gateway internal headers with correct case

    # Check if request has body (without reading it into memory)
    request_has_body = has_request_body(request)

    # Log the request
    logger.info(
        f"🔄 PROXY → {request.method} {target_path}",
        extra={
            "method": request.method,
            "path": target_path,
            "has_body": request_has_body,
            "is_sse": is_sse_request(request),
        },
    )

    try:
        # Handle SSE requests with dedicated client (long-lived connection)
        if is_sse_request(request):
            return await _handle_sse_request(
                request=request,
                target_path=target_path,
                headers=headers,  # Already includes internal headers
                start_time=start_time,
            )

        # Regular request with bidirectional streaming (request + response bodies streamed)
        return await _handle_regular_request(
            request=request,
            target_path=target_path,
            headers=headers,
            start_time=start_time,
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
) -> Response:
    """
    Handle regular requests with full bidirectional streaming.

    Both request and response bodies are streamed without loading into RAM.
    This is memory-efficient for large file uploads AND large file downloads.
    """
    client = await get_proxy_client()

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


async def _handle_sse_request(
    request: Request,
    target_path: str,
    headers: dict,
    start_time: float,
) -> Response:
    """Handle Server-Sent Events requests with streaming."""
    logger.info(f"📡 SSE PROXY → {request.method} {target_path}")

    # For SSE, we need to read the body first since request.stream() can only be consumed once
    # and we need it inside the generator. SSE requests typically have small bodies (if any).
    body = await request.body()

    async def sse_generator() -> AsyncGenerator[bytes, None]:
        """Generate SSE events from backend."""
        async with get_streaming_client() as client:
            try:
                async with client.stream(
                    method=request.method,
                    url=target_path,
                    headers=headers,
                    content=body if body else None,
                ) as response:
                    elapsed = (time.time() - start_time) * 1000
                    logger.info(
                        f"📡 SSE CONNECTED ({elapsed:.0f}ms): {target_path}",
                        extra={"status_code": response.status_code},
                    )

                    async for chunk in response.aiter_bytes():
                        yield chunk

            except Exception as e:
                logger.error(f"📡 SSE ERROR: {e}")
                # Send error event to client
                yield f"event: error\ndata: {str(e)}\n\n".encode()

        logger.info(f"📡 SSE CLOSED: {target_path}")

    return StreamingResponse(
        sse_generator(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
            "X-Accel-Buffering": "no",  # Disable nginx buffering
        },
    )
