from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api.router import api_router
from app.core.config import settings
from app.core.middleware import SecurityMiddleware, JSONValidationMiddleware
from app.core.database_security import setup_database_security
from app.database import engine

app = FastAPI(
    title="Mint Backend API",
    description="API for company data and n8n workflow integration",
    version="0.1.0",
)

# Initialize database security monitoring
setup_database_security(engine)

# Debug logging for CORS settings
print(f"CORS Origin setting: {settings.CORS_ORIGIN}")

# Add CORS middleware FIRST (to handle preflight requests properly)
app.add_middleware(
    CORSMiddleware,
    allow_origins=[settings.CORS_ORIGIN, "http://localhost:3000"],  # Allow both origins
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"],
    allow_headers=["Content-Type", "Authorization", "Accept", "Origin", "X-Requested-With"],  # More restrictive
    expose_headers=["Content-Type", "Authorization"],  # More restrictive
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