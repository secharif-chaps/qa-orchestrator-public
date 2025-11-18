from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api.router import api_router
from app.core.config import settings
from app.core.logging_config import setup_logging, get_logger
from app.core.middleware import SecurityMiddleware, JSONValidationMiddleware
from app.core.database_security import setup_database_security
from app.database import engine

# Initialize logging with configured level
setup_logging(level=getattr(settings, "LOG_LEVEL", "INFO"))
logger = get_logger(__name__)

app = FastAPI(
    title="Mint Backend API",
    description="API for company data and workflow integration",
    version="0.1.0",
)

# Initialize database security monitoring
setup_database_security(engine)

# Debug logging for CORS settings
logger.info(f"CORS Origin setting: {settings.CORS_ORIGIN}")
logger.info(f"Backend Base URL: {settings.BACKEND_BASE_URL}")

# Keycloak configuration logging (temporary for debugging)
logger.info(f"KEYCLOAK_SERVER_URL: {settings.KEYCLOAK_SERVER_URL}")
logger.info(f"KEYCLOAK_REALM: {settings.KEYCLOAK_REALM}")
logger.info(f"KEYCLOAK_ADMIN_CLIENT_ID: {settings.KEYCLOAK_ADMIN_CLIENT_ID}")
logger.info(f"KEYCLOAK_ADMIN_CLIENT_SECRET: {settings.KEYCLOAK_ADMIN_CLIENT_SECRET}")

# Add CORS middleware FIRST (to handle preflight requests properly)
# Allow common development origins for local testing
development_origins = [
    settings.CORS_ORIGIN,
    "http://localhost:3000",
    "http://localhost:5173",  # Vite dev server default
    "http://127.0.0.1:3000",
    "http://127.0.0.1:5173",
    "http://10.0.1.2",       # Direct access to preprod server
    "http://10.0.1.2:3000",
    "http://10.0.1.2:5173",
    "http://10.0.1.2:8000",  # Backend on preprod server
]

logger.info(f"CORS allowed origins: {development_origins}")
logger.info("CORS allowed methods: GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD")

app.add_middleware(
    CORSMiddleware,
    allow_origins=development_origins,
    allow_credentials=True,
    allow_methods=["*"],  # Allow all methods including PATCH
    allow_headers=["*"],  # Allow all headers
    expose_headers=["Content-Type", "Authorization"],
)

# Add security middleware AFTER CORS (so CORS headers are set before security checks)
app.add_middleware(
    SecurityMiddleware,
    max_request_size=2097152,  # 2MB
    rate_limit_requests=100,   # 100 requests per minute
    rate_limit_window=60
)

app.add_middleware(JSONValidationMiddleware)

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