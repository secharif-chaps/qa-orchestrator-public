"""
HTTP client for proxying requests to the monolith (Screen service).

This module provides an async httpx client configured for proxying
all requests to the backend service.
"""

from collections.abc import AsyncGenerator
from contextlib import asynccontextmanager

import httpx

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

# Global client instance for connection pooling
_client: httpx.AsyncClient | None = None


def get_screen_base_url() -> str:
    """Get the backend base URL from settings."""
    return settings.SCREEN_BASE_URL.rstrip("/")


async def get_proxy_client() -> httpx.AsyncClient:
    """Get or create the shared httpx client for proxying requests.

    Uses connection pooling for better performance.
    """
    global _client
    if _client is None:
        _client = httpx.AsyncClient(
            base_url=get_screen_base_url(),
            timeout=httpx.Timeout(
                connect=10.0,
                read=60.0,  # Longer read timeout for slow endpoints
                write=10.0,
                pool=10.0,
            ),
            limits=httpx.Limits(
                max_keepalive_connections=20,
                max_connections=100,
                keepalive_expiry=30.0,
            ),
            follow_redirects=False,  # Let the client handle redirects
        )
        logger.info(f"🔗 Proxy client initialized with base URL: {get_screen_base_url()}")
    return _client


async def close_proxy_client() -> None:
    """Close the shared httpx client."""
    global _client
    if _client is not None:
        await _client.aclose()
        _client = None
        logger.info("🔌 Proxy client closed")


@asynccontextmanager
async def get_streaming_client() -> AsyncGenerator[httpx.AsyncClient, None]:
    """Get a client configured for streaming responses (SSE).

    Creates a new client for each streaming request to avoid
    blocking the connection pool.
    """
    client = httpx.AsyncClient(
        base_url=get_screen_base_url(),
        timeout=httpx.Timeout(
            connect=10.0,
            read=None,  # No read timeout for streaming
            write=10.0,
            pool=10.0,
        ),
        follow_redirects=False,
    )
    try:
        yield client
    finally:
        await client.aclose()
