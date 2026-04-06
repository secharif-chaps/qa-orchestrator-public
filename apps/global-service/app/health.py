"""Readiness health checks for Kubernetes probes.

Each check function verifies a single dependency and returns True/False.
All checks run in parallel with a 2-second timeout to avoid blocking
the readiness probe response.
"""

import asyncio
import socket

import httpx
from sqlalchemy import text

from app.core.config import settings
from app.core.logging_config import get_logger
from app.database import engine
from app.proxy.client import _pool

logger = get_logger(__name__)

# Maximum time (seconds) each individual check is allowed to take
CHECK_TIMEOUT_SECONDS = 2.0

# Shared HTTP client for health checks (avoids creating a new connection pool per call)
_health_http_client: httpx.AsyncClient | None = None


def _get_health_http_client() -> httpx.AsyncClient:
    global _health_http_client
    if _health_http_client is None or _health_http_client.is_closed:
        _health_http_client = httpx.AsyncClient(timeout=httpx.Timeout(CHECK_TIMEOUT_SECONDS))
    return _health_http_client


async def check_database() -> bool:
    """Verify PostgreSQL connectivity via SELECT 1.

    Wraps both pool acquisition and query execution in a single timeout
    to prevent blocking when the connection pool is saturated.
    """
    try:
        async with asyncio.timeout(CHECK_TIMEOUT_SECONDS):
            async with engine.connect() as conn:
                await conn.execute(text("SELECT 1"))
        return True
    except Exception as exc:
        logger.warning("Health check failed: database (%s)", type(exc).__name__)
        return False


async def check_grpc() -> bool:
    """Verify that the gRPC server is accepting connections on its port.

    Uses a simple TCP connect check rather than a full gRPC health call,
    keeping the check lightweight and dependency-free.
    Tries IPv6 first, then falls back to IPv4.
    """
    loop = asyncio.get_running_loop()
    for family, host in [(socket.AF_INET6, "::1"), (socket.AF_INET, "127.0.0.1")]:
        try:
            sock = socket.socket(family, socket.SOCK_STREAM)
            sock.setblocking(False)
            try:
                await asyncio.wait_for(
                    loop.sock_connect(sock, (host, settings.GRPC_PORT)),
                    timeout=CHECK_TIMEOUT_SECONDS,
                )
                return True
            finally:
                sock.close()
        except Exception:
            continue
    logger.warning("Health check failed: grpc (port %s)", settings.GRPC_PORT)
    return False


async def check_proxy_client() -> bool:
    """Verify that the proxy client pool is healthy."""
    try:
        healthy, diagnostics = _pool.health_check()
        if not healthy:
            logger.warning("Health check failed: proxy_client", extra=diagnostics)
        return healthy
    except Exception:
        logger.warning("Health check failed: proxy_client", exc_info=True)
        return False


async def check_keycloak() -> bool:
    """Verify Keycloak connectivity by fetching OpenID configuration.

    This check is optional and controlled by HEALTH_CHECK_KEYCLOAK_ENABLED.
    Uses a short timeout to avoid blocking other checks.
    """
    url = f"{settings.KEYCLOAK_SERVER_URL}/realms/{settings.KEYCLOAK_REALM}/.well-known/openid-configuration"
    try:
        client = _get_health_http_client()
        response = await client.get(url)
        response.raise_for_status()
        return True
    except Exception:
        logger.warning("Health check failed: keycloak (%s)", url)
        return False


async def close_health_http_client() -> None:
    """Close the shared HTTP client. Call during application shutdown."""
    global _health_http_client
    if _health_http_client is not None and not _health_http_client.is_closed:
        await _health_http_client.aclose()
        _health_http_client = None


async def run_readiness_checks(
    include_keycloak: bool = False,
) -> tuple[dict[str, bool], bool]:
    """Execute all readiness checks in parallel.

    Args:
        include_keycloak: Whether to include the Keycloak connectivity check.

    Returns:
        Tuple of (checks_dict, all_ready) where checks_dict maps check names
        to their boolean results and all_ready is True only if all checks passed.
    """
    check_names = ["database", "grpc", "proxy_client"]
    check_coros = [check_database(), check_grpc(), check_proxy_client()]

    if include_keycloak:
        check_names.append("keycloak")
        check_coros.append(check_keycloak())

    results = await asyncio.gather(*check_coros, return_exceptions=True)

    checks: dict[str, bool] = {}
    for name, result in zip(check_names, results, strict=True):
        if isinstance(result, Exception):
            logger.warning("Health check %s raised exception: %s", name, result)
            checks[name] = False
        else:
            checks[name] = bool(result)

    all_ready = all(checks.values())
    return checks, all_ready
