from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy import text
import logging
from app.core.config import settings
from app.core.logging_config import setup_logging, get_logger
from app.database import engine
from app.grpc_server import create_grpc_server

# Initialize logging with configured level
setup_logging(level=getattr(settings, "LOG_LEVEL", "INFO"))
logger = get_logger(__name__)

# App initialization
app = FastAPI(
    title="Global Service",
    description="Centralized organization-scoped resources service",
    version="0.1.0",
)
# Debug logging for CORS settings
logger.info(f"CORS Origin setting: {settings.CORS_ORIGIN}")
logger.info(f"Backend Base URL: {settings.BACKEND_BASE_URL}")

# CORS configuration
development_origins = [
    settings.CORS_ORIGIN,
    "http://localhost:3000",
    "http://localhost:5173",
    "http://127.0.0.1:3000",
    "http://127.0.0.1:5173",
]

app.add_middleware(
    CORSMiddleware,
    allow_origins=development_origins,
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
    grpc_server = create_grpc_server()
    grpc_server.start()
    logger.info("🚀 gRPC server started")


@app.on_event("shutdown")
async def shutdown_event():
    if grpc_server:
        logger.info("🛑 Shutting down gRPC server")
        grpc_server.stop(grace=5)

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
