from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api.router import api_router
from app.core.config import settings

app = FastAPI(
    title="Mint Backend API",
    description="API for company data and n8n workflow integration",
    version="0.1.0",
)

# Debug logging for CORS settings
print(f"CORS Origin setting: {settings.CORS_ORIGIN}")

# Add CORS middleware with specific origin
app.add_middleware(
    CORSMiddleware,
    allow_origins=[settings.CORS_ORIGIN],  # Use the specific origin from settings
    allow_credentials=True,
    allow_methods=["*"],  # Allow all methods
    allow_headers=["*"],  # Allow all headers
    expose_headers=["*"],  # Expose all headers
    max_age=3600,
)

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