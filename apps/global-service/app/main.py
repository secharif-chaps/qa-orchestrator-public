import os
from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from app.api import api_router
from app.core.config import settings
from app.core.correlation import CorrelationIdMiddleware
from app.core.keycloak import get_idp
from app.core.logging_config import get_logger, setup_logging
from app.core.openapi_merge import setup_merged_openapi
from app.grpc_server import create_grpc_server
from app.health import close_health_http_client, run_readiness_checks
from app.proxy.client import close_proxy_client
from app.proxy.routes import init_module_registry
from app.proxy.routes import router as proxy_router
from app.services.keycloak_admin import keycloak_admin_service

# Initialize logging with configured level
setup_logging(level=getattr(settings, "LOG_LEVEL", "INFO"))
logger = get_logger(__name__)
logger.debug(f".env file path: {os.path.abspath('.env') if os.path.exists('.env') else 'not found'}")


@asynccontextmanager
async def lifespan(app: FastAPI):
    # ── Startup ──
    grpc_server = None
    try:
        # Initialize Keycloak IDP eagerly (before accepting requests)
        # This ensures time.sleep() in retry logic doesn't block the event loop during user requests
        get_idp()
        logger.info("🔐 Keycloak IDP initialized")

        # Initialize gRPC server
        grpc_server = create_grpc_server()
        grpc_server.start()
        logger.info("🚀 gRPC server started")

        # Initialize module registry (discovers backend schemas via OpenAPI)
        registry = await init_module_registry()
        module_count = len(registry.modules)
        logger.info(f"🔗 Module registry initialized: {module_count} module(s) discovered")

        yield
    finally:
        # ── Shutdown — always runs, even if startup failed partially ──
        if grpc_server is not None:
            logger.info("🛑 Shutting down gRPC server")
            grpc_server.stop(grace=5)

        await close_proxy_client()
        logger.info("🔌 Proxy client closed")

        await close_health_http_client()
        await keycloak_admin_service.close()


# App initialization — disable docs endpoints when ENABLE_DOCS=false
app = FastAPI(
    title="Global Service",
    description="Centralized organization-scoped resources service",
    version="0.1.0",
    lifespan=lifespan,
    **({"docs_url": None, "redoc_url": None, "openapi_url": None} if not settings.ENABLE_DOCS else {}),
)
# Parse CORS origins from comma-separated config (no rebuild needed to change)
cors_origins = [origin.strip() for origin in settings.CORS_ORIGINS.split(",") if origin.strip()]
logger.info(f"CORS Origins: {cors_origins}")
logger.info(f"Screen Base URL: {settings.SCREEN_BASE_URL}")

app.add_middleware(
    CORSMiddleware,
    allow_origins=cors_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["Content-Type", "Authorization", "X-Correlation-ID"],
)


# Correlation ID middleware — generates or propagates X-Correlation-ID.
# Starlette executes middlewares in LIFO order, so this runs BEFORE CORS
# which is the desired behavior (correlation ID is set early).
app.add_middleware(CorrelationIdMiddleware)

# Health check endpoints
@app.get("/health/live", tags=["health"])
def health_live():
    """
    Liveness probe.
    Used to check if the service process is running.
    """
    return {"status": "alive"}


@app.get("/health/ready", tags=["health"])
async def health_ready():
    """Readiness probe. Verifies all critical dependencies before accepting traffic."""
    checks, all_ready = await run_readiness_checks(
        include_keycloak=settings.HEALTH_CHECK_KEYCLOAK_ENABLED,
    )
    return JSONResponse(
        content={"status": "ready" if all_ready else "not_ready", "checks": checks},
        status_code=200 if all_ready else 503,
    )


# Register token API endpoints - these are handled locally by global-service
# Must be registered BEFORE proxy router so they're matched first
app.include_router(api_router, prefix="/api")

# Register proxy router - forwards all /api/* requests to the backend monolith
# This MUST be registered last to act as a catch-all for /api/* routes
app.include_router(proxy_router, prefix="/api", tags=["proxy"])

# Activate unified OpenAPI docs (lazily fetches screen schema on first /docs access)
if settings.ENABLE_DOCS:
    setup_merged_openapi(app)
