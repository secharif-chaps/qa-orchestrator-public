"""
Target Mercure proxy — routes /.well-known/mercure to FrankenPHP's built-in Mercure hub.

FrankenPHP (the Target app server) embeds Mercure natively — no separate hub service needed.
TARGET_MERCURE_URL points to the Target container itself (http://target:8000).

Authentication exception:
    Mercure uses its own subscriber/publisher JWT (signed by TARGET_MERCURE_JWT_SECRET),
    completely separate from Keycloak JWTs. The gateway does NOT validate Keycloak auth
    on this route — FrankenPHP validates the Mercure JWT independently.
    Topics act as the access control boundary: each subscriber only receives events
    for the topics their JWT authorizes.

Flows:
    GET  /.well-known/mercure?topic=...  → Subscribe (long-lived SSE stream)
    POST /.well-known/mercure            → Publish (short request)

Security:
    - Mercure subscriber JWT is validated by FrankenPHP, not by this gateway
    - No Keycloak token required — EventSource() in browsers cannot set Authorization headers
    - Module exclusivity: Target only starts with --profile target
"""

from __future__ import annotations

from collections.abc import AsyncGenerator

import httpx
from fastapi import APIRouter, Request, Response
from fastapi.responses import StreamingResponse

from app.core.config import settings
from app.core.logging_config import get_logger
from app.proxy.client import get_proxy_client, get_streaming_client
from app.proxy.routes import filter_request_headers, filter_response_headers

logger = get_logger(__name__)

router = APIRouter(tags=["mercure"])


def _build_mercure_path(request: Request) -> str:
    """Build the downstream path preserving query string (topics, Last-Event-ID)."""
    path = "/.well-known/mercure"
    if request.url.query:
        path = f"{path}?{request.url.query}"
    return path


@router.api_route(
    "/.well-known/mercure",
    methods=["GET", "POST"],
    include_in_schema=False,
)
async def mercure_proxy(request: Request) -> Response:
    """Transparent proxy to FrankenPHP's built-in Mercure hub.

    GET  → Subscribe: opens a long-lived SSE stream.
    POST → Publish:   forwards a publication to the hub.

    No Keycloak JWT validation — auth is delegated to FrankenPHP via Mercure JWT.
    """
    # Preserve Authorization (Mercure JWT), Last-Event-ID, Accept and other headers.
    # filter_request_headers removes hop-by-hop headers but keeps Authorization.
    headers = filter_request_headers(dict(request.headers))

    backend_path = _build_mercure_path(request)

    logger.debug(
        "MERCURE PROXY → %s %s",
        request.method,
        backend_path,
        extra={"method": request.method},
    )

    if request.method == "GET":
        return await _subscribe(request, backend_path, headers)
    return await _publish(request, backend_path, headers)


async def _subscribe(
    request: Request,
    backend_path: str,
    headers: dict,
) -> Response:
    """Forward a GET subscription request — long-lived SSE connection.

    Opens the connection to FrankenPHP's Mercure hub eagerly so we can propagate its HTTP
    status code (401 bad JWT, 403 forbidden topic, …) before starting the stream.
    The client and response context managers are kept alive for the duration of
    the stream via a captured reference in the generator closure.
    """
    client_ctx = get_streaming_client(settings.TARGET_MERCURE_URL)
    client = await client_ctx.__aenter__()

    try:
        response_ctx = client.stream("GET", backend_path, headers=headers)
        response = await response_ctx.__aenter__()
    except httpx.ConnectError:
        await client_ctx.__aexit__(None, None, None)
        logger.error("MERCURE SUBSCRIBE: Mercure hub (FrankenPHP) unreachable")
        return Response(
            content=b'{"detail": "Mercure hub unavailable"}',
            status_code=503,
            media_type="application/json",
        )
    except Exception as exc:
        await client_ctx.__aexit__(None, None, None)
        logger.exception("MERCURE SUBSCRIBE ERROR: %s", exc)
        return Response(
            content=b'{"detail": "Proxy error"}',
            status_code=502,
            media_type="application/json",
        )

    # Propagate non-200 responses (401 bad JWT, 403 forbidden topic, …)
    if response.status_code != 200:
        body = await response.aread()
        await response_ctx.__aexit__(None, None, None)
        await client_ctx.__aexit__(None, None, None)
        logger.warning("MERCURE SUBSCRIBE rejected: %s", response.status_code)
        return Response(
            content=body,
            status_code=response.status_code,
            media_type=response.headers.get("content-type", "application/json"),
        )

    logger.debug("MERCURE SUBSCRIBE connected: %s", backend_path)

    async def event_stream() -> AsyncGenerator[bytes, None]:
        try:
            async for chunk in response.aiter_bytes():
                yield chunk
        except Exception as exc:
            logger.error("MERCURE SUBSCRIBE stream error: %s", exc)
            yield b"event: error\ndata: Proxy error\n\n"
        finally:
            await response_ctx.__aexit__(None, None, None)
            await client_ctx.__aexit__(None, None, None)
            logger.debug("MERCURE SUBSCRIBE closed")

    return StreamingResponse(
        event_stream(),
        status_code=200,
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "X-Accel-Buffering": "no",  # Disable nginx buffering for SSE
        },
    )


async def _publish(
    request: Request,
    backend_path: str,
    headers: dict,
) -> Response:
    """Forward a POST publication request — regular short-lived request."""
    body = await request.body()
    # The streamed response is explicitly closed in stream_body()'s finally block.
    client = await get_proxy_client(settings.TARGET_MERCURE_URL)

    try:
        backend_request = client.build_request(
            method="POST",
            url=backend_path,
            headers=headers,
            content=body,
        )
        response = await client.send(backend_request, stream=True)

        logger.debug("MERCURE PUBLISH ← %s", response.status_code)

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
    except httpx.ConnectError:
        logger.error("MERCURE PUBLISH: Mercure hub (FrankenPHP) unreachable")
        return Response(
            content=b'{"detail": "Mercure hub unavailable"}',
            status_code=503,
            media_type="application/json",
        )
    except Exception as exc:
        logger.exception("MERCURE PUBLISH ERROR: %s", exc)
        return Response(
            content=b'{"detail": "Proxy error"}',
            status_code=502,
            media_type="application/json",
        )
