#!/usr/bin/env python3
"""
Run script for the Global Service Microservice
"""
import uvicorn
from app.core.config import settings

# Start FastAPI / gRPC app
if __name__ == "__main__":
    # Configure uvicorn logging to use our log level
    log_level = settings.LOG_LEVEL.lower()

    uvicorn.run(
        "app.main:app",
        host=settings.API_HOST,
        port=settings.API_PORT,
        reload=settings.DEV_MODE,
        log_level=log_level,
        access_log=True,  # Enable access logs
        log_config=None,  # Use default logging config (our setup_logging takes precedence)
    ) 