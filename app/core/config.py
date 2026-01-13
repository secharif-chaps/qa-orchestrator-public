from pydantic_settings import BaseSettings
from typing import Optional
from pydantic import ConfigDict
import os


class Settings(BaseSettings):
    # API settings
    API_HOST: str = "0.0.0.0"
    API_PORT: int = 8001
    BACKEND_BASE_URL: str = "http://localhost:8001"  # Default for local dev, override with env var

    # GRPC settings
    GRPC_PORT: int = 50051
    
    # Logging settings
    LOG_LEVEL: str = "INFO"  # DEBUG, INFO, WARNING, ERROR, CRITICAL

    # Database settings
    DATABASE_URL: str = "postgresql://postgres:postgres@db:5432/global_db"
    
    # CORS settings
    CORS_ORIGIN: str = "http://localhost:3000"
    
    # Keycloak settings
    
    KEYCLOAK_SERVER_URL: str = "https://keycloak.preprod.chapsmind.com"
    KEYCLOAK_REALM: str = "mint-preprod"
    KEYCLOAK_CLIENT_ID: str = "mint-back"
    KEYCLOAK_CLIENT_SECRET: Optional[str] = None
    KEYCLOAK_CALLBACK_URI: str = "http://localhost:8001/callback"
    KEYCLOAK_ADMIN_CLIENT_ID: str = "admin-cli"
    KEYCLOAK_ADMIN_CLIENT_SECRET: str = "admin-cli-secret"
    
    # SQLAlchemy tuning
    DB_POOL_SIZE: int = 10
    DB_MAX_OVERFLOW: int = 20
    DB_POOL_TIMEOUT: int = 30
    DB_POOL_RECYCLE: int = 1800

    model_config = ConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")


settings = Settings()

# 🔍 DEBUG: Log configuration on startup
import logging
logger = logging.getLogger(__name__)
logger.info(f"🔧 CONFIG DEBUG - BACKEND_BASE_URL loaded as: {settings.BACKEND_BASE_URL}")
logger.info(f"🔧 CONFIG DEBUG - ENVIRONMENT: {getattr(settings, 'ENVIRONMENT', 'not set')}")
logger.info(f"🔧 CONFIG DEBUG - .env file path: {os.path.abspath('.env') if os.path.exists('.env') else 'not found'}")
logger.info(f"🔧 STARTUP CONFIG - BACKEND_BASE_URL: {settings.BACKEND_BASE_URL}")