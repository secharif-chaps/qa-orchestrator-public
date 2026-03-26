from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api.endpoints.health import router as health_router
from app.api.router import api_router
from app.core.config import settings
from app.core.database_security import setup_database_security
from app.core.logging_config import get_logger, setup_logging
from app.core.middleware import JSONValidationMiddleware, SecurityMiddleware
from app.database import engine

# Initialize logging with configured level
setup_logging(level=getattr(settings, "LOG_LEVEL", "INFO"))
logger = get_logger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Manage async resource lifecycle (checkpoint pool, graph compilation)."""
    # Startup: pre-compile graph and open checkpoint pool
    from app.agents.graph import get_analysis_graph

    await get_analysis_graph()
    logger.info("Analysis graph compiled and checkpoint pool opened")
    yield
    # Shutdown: close checkpoint pool
    from app.agents.checkpoint import _pool

    if _pool:
        await _pool.close()
        logger.info("Checkpoint pool closed")


app = FastAPI(
    title="Mint Backend API",
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
    expose_headers=["Content-Type", "Authorization"],
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
        reload=True,
    )
