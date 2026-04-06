from contextlib import asynccontextmanager

from fastapi import FastAPI

from app.api.endpoints.health import router as health_router
from app.api.router import api_router
from app.core.config import settings
from app.core.logging_config import get_logger, setup_logging

setup_logging(level=getattr(settings, "LOG_LEVEL", "INFO"))
logger = get_logger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Manage async resource lifecycle."""
    logger.info("Stream service started")
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

# API routes
app.include_router(api_router, prefix="/api")
