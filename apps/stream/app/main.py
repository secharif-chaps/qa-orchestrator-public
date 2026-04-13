import asyncio
import hashlib
import json
from contextlib import asynccontextmanager

import httpx
import jwt as pyjwt
from fastapi import FastAPI

from app.api.endpoints.health import router as health_router
from app.api.endpoints.internal import router as internal_router
from app.api.router import api_router
from app.core.config import settings
from app.core.logging_config import get_logger, setup_logging

setup_logging(level=getattr(settings, "LOG_LEVEL", "INFO"))
logger = get_logger(__name__)

ALGORITHM = "HS256"
ISSUER = "global-gateway"


def _create_internal_token() -> str:
    """Create a short-lived internal JWT for service-to-service auth."""
    from datetime import UTC, datetime, timedelta

    now = datetime.now(UTC)
    payload = {
        "sub": "stream-service",
        "username": "stream",
        "org_id": "system",
        "org_name": "system",
        "roles": ["service"],
        "iss": ISSUER,
        "iat": int(now.timestamp()),
        "exp": int((now + timedelta(seconds=60)).timestamp()),
    }
    return pyjwt.encode(payload, settings.INTERNAL_JWT_SECRET, algorithm=ALGORITHM)


async def _announce_to_gateway(app: FastAPI) -> None:
    """Notify the global-service gateway that stream is ready.

    Non-blocking: if the announce fails, log a warning and continue.
    The gateway's healthcheck will detect the change later.
    """
    try:
        gateway_url = settings.GLOBAL_SERVICE_URL
        if not gateway_url:
            logger.info("GLOBAL_SERVICE_URL not set, skipping registry announce")
            return

        schema = app.openapi()
        schema_hash = hashlib.sha256(json.dumps(schema, sort_keys=True).encode()).hexdigest()

        token = _create_internal_token()

        async with httpx.AsyncClient(timeout=5) as client:
            resp = await client.post(
                f"{gateway_url.rstrip('/')}/internal/registry/announce/stream",
                json={"openapi_hash": schema_hash},
                headers={"Authorization": f"Internal {token}"},
            )
            resp.raise_for_status()
            result = resp.json()
            logger.info(
                "Registry announce completed",
                extra={"action": result.get("action")},
            )
    except Exception as e:
        logger.warning(
            "Registry announce failed (gateway will detect via healthcheck)",
            extra={"error": str(e)},
        )


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Manage async resource lifecycle."""
    logger.info("Stream service started")

    # Announce to global-service registry (fire-and-forget, non-blocking)
    _announce_task = asyncio.create_task(_announce_to_gateway(app))
    _announce_task.add_done_callback(lambda t: t.exception() if not t.cancelled() else None)

    yield
    logger.info("Stream service shutting down")


app = FastAPI(
    title="Stream Service API",
    description="Multi-channel event distribution service",
    version="0.1.0",
    lifespan=lifespan,
)

# Health check endpoints (before /api to avoid auth)
app.include_router(health_router)

# API routes (visible in OpenAPI → auto-discovered by gateway)
app.include_router(api_router, prefix="/api")

# Internal routes (NOT in OpenAPI → invisible to gateway discovery)
app.include_router(internal_router, prefix="/internal", include_in_schema=False)

if __name__ == "__main__":
    import uvicorn

    uvicorn.run(
        "app.main:app",
        host=settings.API_HOST,
        port=settings.API_PORT,
        reload=True,
    )
