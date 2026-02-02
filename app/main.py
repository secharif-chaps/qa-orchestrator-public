import os

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy import text
from app.core.config import settings
from app.core.logging_config import setup_logging, get_logger
from app.core.keycloak import get_idp
from app.database import engine
from app.grpc_server import create_grpc_server
from app.proxy.client import get_proxy_client, close_proxy_client
from app.proxy.routes import router as proxy_router

# Initialize logging with configured level
setup_logging(level=getattr(settings, "LOG_LEVEL", "INFO"))
logger = get_logger(__name__)
logger.debug(f".env file path: {os.path.abspath('.env') if os.path.exists('.env') else 'not found'}")

# App initialization
app = FastAPI(
    title="Global Service",
    description="Centralized organization-scoped resources service",
    version="0.1.0",
)
# Parse CORS origins from comma-separated config (no rebuild needed to change)
cors_origins = [origin.strip() for origin in settings.CORS_ORIGINS.split(",") if origin.strip()]
logger.info(f"CORS Origins: {cors_origins}")
logger.info(f"Backend Base URL: {settings.BACKEND_BASE_URL}")

app.add_middleware(
    CORSMiddleware,
    allow_origins=cors_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["Content-Type", "Authorization"],
)

# gRPC server lifecycle
grpc_server = None

@app.on_event("startup")
async def startup_event():
    global grpc_server

    # Initialize Keycloak IDP eagerly at startup (before accepting requests)
    # This ensures time.sleep() in retry logic doesn't block the event loop during user requests
    get_idp()
    logger.info("🔐 Keycloak IDP initialized")

    # Initialize gRPC server
    grpc_server = create_grpc_server()
    grpc_server.start()
    logger.info("🚀 gRPC server started")

    # Initialize proxy client (warm up connection pool)
    await get_proxy_client()
    logger.info(f"🔗 Proxy client initialized → {settings.BACKEND_BASE_URL}")


@app.on_event("shutdown")
async def shutdown_event():
    # Shutdown gRPC server
    if grpc_server:
        logger.info("🛑 Shutting down gRPC server")
        grpc_server.stop(grace=5)

    # Close proxy client
    await close_proxy_client()
    logger.info("🔌 Proxy client closed")

# Health check endpoints
@app.get("/health/live", tags=["health"])
def health_live():
    """
    Liveness probe.
    Used to check if the service process is running.
    """
    return {"status": "alive"}

@app.get("/health/ready", tags=["health"])
def health_ready():
    try:
        with engine.connect() as conn:
            conn.execute(text("SELECT 1"))
        return {"status": "ready"}
    except Exception:
        return {"status": "not_ready"}


# Register proxy router - forwards all /api/* requests to the backend monolith
# This MUST be registered last to act as a catch-all for /api/* routes
app.include_router(proxy_router, prefix="/api", tags=["proxy"])
