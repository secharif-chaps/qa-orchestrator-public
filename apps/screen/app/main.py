import asyncio
import hashlib
import json
from contextlib import asynccontextmanager

import httpx
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api.endpoints.health import router as health_router
from app.api.router import api_router
from app.core.config import settings
from app.core.database_security import setup_database_security
from app.core.internal_jwt import create_internal_token
from app.core.logging_config import get_logger, setup_logging
from app.core.middleware import JSONValidationMiddleware, SecurityMiddleware
from app.database import engine

# Initialize logging with configured level
setup_logging(level=getattr(settings, "LOG_LEVEL", "INFO"))
logger = get_logger(__name__)


async def _announce_to_gateway(app: FastAPI) -> None:
    """Notify the global-service gateway that screen is ready.

    Non-blocking: if the announce fails, log a warning and continue.
    The gateway's healthcheck will detect the change later.

    Args:
        app: The FastAPI application instance (needed for openapi() schema).
    """
    try:
        gateway_url = settings.GLOBAL_SERVICE_URL
        if not gateway_url:
            logger.info("GLOBAL_SERVICE_URL not set, skipping registry announce")
            return

        # Calculate our own OpenAPI schema hash
        schema = app.openapi()
        schema_hash = hashlib.sha256(json.dumps(schema, sort_keys=True).encode()).hexdigest()

        # Create internal JWT for authentication
        token = create_internal_token(
            user_id="screen-service",
            username="screen",
            org_id="system",
            org_name="system",
            roles=["service"],
        )

        async with httpx.AsyncClient(timeout=5) as client:
            resp = await client.post(
                f"{gateway_url.rstrip('/')}/internal/registry/announce/screen",
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
    """Manage async resource lifecycle (checkpoint pool, graph compilation, outbox relay)."""
    # Startup: pre-compile graph and open checkpoint pool
    from app.agents.graph import get_analysis_graph
    from app.services.outbox_relay import OutboxRelay

    await get_analysis_graph()
    logger.info("Analysis graph compiled and checkpoint pool opened")

    # Announce to global-service registry (fire-and-forget, non-blocking)
    # Keep a strong reference to prevent GC from collecting the task mid-execution.
    _announce_task = asyncio.create_task(_announce_to_gateway(app))
    _announce_task.add_done_callback(lambda t: t.exception() if not t.cancelled() else None)

    relay = OutboxRelay()
    await relay.start()
    logger.info("Outbox relay started")

    yield

    # Shutdown: stop relay, close checkpoint pool
    await relay.stop()
    logger.info("Outbox relay stopped")

    from app.agents.checkpoint import _pool

    if _pool:
        await _pool.close()
        logger.info("Checkpoint pool closed")


app = FastAPI(
    title="ChapsMind Screen API",
    description="API for company data and workflow integration",
    version="0.1.0",
    lifespan=lifespan,
)

# Initialize database security monitoring
setup_database_security(engine)

# Debug logging for CORS settings
logger.info(f"CORS Origin setting: {settings.CORS_ORIGIN}")

# Add CORS middleware FIRST (to handle preflight requests properly)
# Allow common development origins for local testing
allowed_origins = [
    settings.CORS_ORIGIN,
    "http://localhost",
    "http://127.0.0.1",
    "http://10.0.1.2",  # Direct access to preprod server
]
# Add extra origins from env (comma-separated), e.g. for preprod IPs
if settings.CORS_EXTRA_ORIGINS:
    allowed_origins.extend(origin.strip() for origin in settings.CORS_EXTRA_ORIGINS.split(",") if origin.strip())

app.add_middleware(
    CORSMiddleware,
    allow_origins=allowed_origins,
    allow_credentials=True,
    allow_methods=["*"],  # Allow all methods including PATCH
    allow_headers=["*"],  # Allow all headers
    expose_headers=["Content-Type", "Authorization", "Retry-After"],
)

# Add security middleware AFTER CORS (so CORS headers are set before security checks)
app.add_middleware(
    SecurityMiddleware,
    max_request_size=2097152,  # 2MB
    rate_limit_requests=300,  # 300 requests per minute (increased for lazy-load patterns)
    rate_limit_window=60,
)

app.add_middleware(JSONValidationMiddleware)

# Health check endpoints (registered before /api to avoid auth middleware)
app.include_router(health_router)

# Include API routers
app.include_router(api_router, prefix="/api")

if __name__ == "__main__":
    import uvicorn

    uvicorn.run(
        "app.main:app",
        host=settings.API_HOST,
        port=settings.API_PORT,
        reload=settings.DEV_MODE,
    )
