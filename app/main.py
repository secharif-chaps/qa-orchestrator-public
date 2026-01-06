from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy import text
from app.database import engine

# ------------------------------------------------------------------------------
# App initialization
# ------------------------------------------------------------------------------
app = FastAPI(
    title="Global Service",
    description="Centralized organization-scoped resources service",
    version="0.1.0",
)

# ------------------------------------------------------------------------------
# CORS configuration
# ------------------------------------------------------------------------------
# Adjust origins as needed (frontend + internal services)
development_origins = [
    "http://localhost:3000",
    "http://localhost:5173",
    "http://127.0.0.1:3000",
    "http://127.0.0.1:5173",
]

app.add_middleware(
    CORSMiddleware,
    allow_origins=development_origins,
    allow_credentials=True,
    allow_methods=["*"],  # Allow all methods including PATCH
    allow_headers=["*"],  # Allow all headers
    expose_headers=["Content-Type", "Authorization"],
)

# ------------------------------------------------------------------------------
# Health check endpoints
# ------------------------------------------------------------------------------

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
