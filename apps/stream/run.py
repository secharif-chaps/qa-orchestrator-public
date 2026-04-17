#!/usr/bin/env python3
"""Run script for the Stream service."""
import uvicorn

from app.core.config import settings

if __name__ == "__main__":
    log_level = settings.LOG_LEVEL.lower()

    uvicorn.run(
        "app.main:app",
        host=settings.API_HOST,
        port=settings.API_PORT,
        reload=settings.DEV_MODE,
        log_level=log_level,
        access_log=True,
        log_config=None,
    )
