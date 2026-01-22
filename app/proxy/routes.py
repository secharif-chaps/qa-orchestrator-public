"""
Proxy routes for forwarding /api/* requests to the monolith (Screen service).

This module implements a transparent proxy that:
- Forwards all /api/* requests to the backend
- Preserves headers, body, query params
- Preserves response status, headers, body
- Handles streaming responses (SSE)
- Logs requests/responses for debugging
"""

import time
from typing import AsyncGenerator

import httpx
from fastapi import APIRouter, Request, Response
from fastapi.responses import StreamingResponse

from app.proxy.client import get_proxy_client, get_streaming_client
from app.core.logging_config import get_logger

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


def is_sse_request(request: Request) -> bool:
    """Check if this is a request for Server-Sent Events."""
    accept = request.headers.get("accept", "")
    return "text/event-stream" in accept


def is_sse_response(response: httpx.Response) -> bool:
    """Check if response is Server-Sent Events."""
    content_type = response.headers.get("content-type", "")
    return "text/event-stream" in content_type


async def stream_response(response: httpx.Response) -> AsyncGenerator[bytes, None]:
    """Stream response body chunk by chunk."""
    async for chunk in response.aiter_bytes():
        yield chunk


async def stream_sse_response(
    client: httpx.AsyncClient,
    response: httpx.Response,
) -> AsyncGenerator[bytes, None]:
    """Stream SSE response and close client when done."""
    try:
        async for chunk in response.aiter_bytes():
            yield chunk
    finally:
        await response.aclose()


@router.api_route(
    "/{path:path}",
    methods=["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"],
    include_in_schema=False,
)
async def proxy_request(request: Request, path: str) -> Response:
    """
    Proxy all requests to the backend monolith.

    This endpoint forwards all HTTP methods to the backend service,
    preserving headers, body, query parameters, and handles
    streaming responses (SSE) appropriately.
    """
    start_time = time.time()

    # Build the target URL
    target_path = f"/api/{path}"
    if request.url.query:
        target_path = f"{target_path}?{request.url.query}"

    # Prepare headers
    headers = filter_request_headers(dict(request.headers))

    # Get request body
    body = await request.body()

    # Log the request
    logger.info(
        f"🔄 PROXY → {request.method} {target_path}",
        extra={
            "method": request.method,
            "path": target_path,
            "has_body": len(body) > 0,
            "is_sse": is_sse_request(request),
        },
    )

    try:
        # Handle SSE requests differently (need streaming client)
        if is_sse_request(request):
            return await _handle_sse_request(
                request.method,
                target_path,
                headers,
                body,
                start_time,
            )

        # Regular request
        client = await get_proxy_client()
        response = await client.request(
            method=request.method,
            url=target_path,
            headers=headers,
            content=body if body else None,
        )

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

        # Check if response is SSE (backend might send SSE even if not requested)
        if is_sse_response(response):
            return StreamingResponse(
                stream_response(response),
                status_code=response.status_code,
                headers=filter_response_headers(response.headers),
                media_type="text/event-stream",
            )

        # Return regular response
        return Response(
            content=response.content,
            status_code=response.status_code,
            headers=filter_response_headers(response.headers),
            media_type=response.headers.get("content-type"),
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


async def _handle_sse_request(
    method: str,
    target_path: str,
    headers: dict,
    body: bytes,
    start_time: float,
) -> Response:
    """Handle Server-Sent Events requests with streaming."""
    logger.info(f"📡 SSE PROXY → {method} {target_path}")

    async def sse_generator() -> AsyncGenerator[bytes, None]:
        """Generate SSE events from backend."""
        async with get_streaming_client() as client:
            try:
                async with client.stream(
                    method=method,
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
