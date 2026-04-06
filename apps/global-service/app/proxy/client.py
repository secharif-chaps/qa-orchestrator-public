"""
HTTP client pool for proxying requests to backend services.

Provides a pool of async httpx clients keyed by backend URL.
Each backend gets its own connection pool for optimal performance.
Also provides a streaming client factory for SSE/NDJSON connections.
"""

from collections.abc import AsyncGenerator
from contextlib import asynccontextmanager

import httpx

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

# Default timeout and pool settings
_DEFAULT_TIMEOUT = httpx.Timeout(connect=10.0, read=60.0, write=10.0, pool=10.0)
_DEFAULT_LIMITS = httpx.Limits(max_keepalive_connections=20, max_connections=100, keepalive_expiry=30.0)
_STREAMING_TIMEOUT = httpx.Timeout(connect=10.0, read=None, write=10.0, pool=10.0)
_POOL_EXHAUSTION_THRESHOLD = 0.8  # Warn when pool usage exceeds 80%


class ProxyClientPool:
    """Pool of httpx clients keyed by backend base URL.

    Each backend gets a dedicated client with its own connection pool.
    Clients are created lazily on first use and reused for subsequent requests.
    """

    def __init__(self) -> None:
        self._clients: dict[str, httpx.AsyncClient] = {}

    async def get_client(self, backend_url: str) -> httpx.AsyncClient:
        """Get or create a pooled client for the given backend URL."""
        base_url = backend_url.rstrip("/")
        if base_url not in self._clients:
            self._clients[base_url] = httpx.AsyncClient(
                base_url=base_url,
                timeout=_DEFAULT_TIMEOUT,
                limits=_DEFAULT_LIMITS,
                follow_redirects=False,
            )
            logger.info("Proxy client created", extra={"base_url": base_url})
        return self._clients[base_url]

    def health_check(self) -> tuple[bool, dict]:
        """Check pool health: client state and connection pool usage.

        Returns:
            Tuple of (is_healthy, diagnostics dict)
        """
        # No clients yet — lazy init, not an error
        if not self._clients:
            return True, {"status": "idle", "clients": 0}

        diagnostics: dict = {"clients": {}}
        healthy = True

        for base_url, client in self._clients.items():
            client_info: dict = {}

            if client.is_closed:
                client_info["status"] = "closed"
                healthy = False
            else:
                client_info["status"] = "open"

                # Inspect httpcore connection pool
                try:
                    pool = client._transport._pool  # type: ignore[attr-defined]
                    connections = pool.connections
                    total = len(connections)
                    idle = sum(1 for c in connections if c.is_idle)
                    active = total - idle
                    client_info["connections"] = {
                        "active": active,
                        "idle": idle,
                        "total": total,
                        "max": _DEFAULT_LIMITS.max_connections,
                    }
                    # Warn if pool usage > 80%
                    if total > 0 and active / _DEFAULT_LIMITS.max_connections > _POOL_EXHAUSTION_THRESHOLD:
                        client_info["warning"] = "pool near exhaustion"
                        logger.warning(
                            "Proxy client pool near exhaustion",
                            extra={"base_url": base_url, "active": active, "max": _DEFAULT_LIMITS.max_connections},
                        )
                except Exception:
                    client_info["connections"] = "unavailable"

            diagnostics["clients"][base_url] = client_info

        diagnostics["status"] = "healthy" if healthy else "degraded"
        return healthy, diagnostics

    async def close_all(self) -> None:
        """Close all pooled clients."""
        for base_url, client in self._clients.items():
            await client.aclose()
            logger.info("Proxy client closed", extra={"base_url": base_url})
        self._clients.clear()


# Global pool instance
_pool = ProxyClientPool()


def get_screen_base_url() -> str:
    """Get the screen backend base URL from settings."""
    return settings.SCREEN_BASE_URL.rstrip("/")


async def get_proxy_client(backend_url: str | None = None) -> httpx.AsyncClient:
    """Get a pooled client for the given backend URL.

    If no URL is provided, defaults to SCREEN_BASE_URL for backwards compatibility.
    """
    url = backend_url or get_screen_base_url()
    return await _pool.get_client(url)


def is_client_ready() -> bool:
    """Check if at least one proxy client is initialized and open."""
    return bool(_pool._clients) and all(not c.is_closed for c in _pool._clients.values())


async def close_proxy_client() -> None:
    """Close all pooled proxy clients."""
    await _pool.close_all()


@asynccontextmanager
async def get_streaming_client(
    backend_url: str | None = None,
) -> AsyncGenerator[httpx.AsyncClient, None]:
    """Get a client configured for streaming responses (SSE, NDJSON).

    Creates a new client per streaming request to avoid blocking the pool.
    If no URL is provided, defaults to SCREEN_BASE_URL.
    """
    base_url = (backend_url or get_screen_base_url()).rstrip("/")
    client = httpx.AsyncClient(
        base_url=base_url,
        timeout=_STREAMING_TIMEOUT,
        follow_redirects=False,
    )
    try:
        yield client
    finally:
        await client.aclose()
