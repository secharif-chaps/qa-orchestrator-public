from pydantic_settings import BaseSettings
from typing import Optional
from pydantic import ConfigDict
import os


class Settings(BaseSettings):
    # API settings
    API_HOST: str = "0.0.0.0"
    API_PORT: int = 8000
    BACKEND_BASE_URL: str = "http://localhost:8000"  # Default for local dev, override with env var

    # Logging settings
    LOG_LEVEL: str = "INFO"  # DEBUG, INFO, WARNING, ERROR, CRITICAL

    # Database settings
    DATABASE_URL: str = "postgresql://postgres:postgres@db:5432/mint_db"
    
    
    # Dify settings
    DIFY_API_KEY: str = "app-WpGZCTFDaBzCUS9M4LeoQHGa"  # Fallback API key for chat workflows
    DIFY_URL: str = "http://10.0.1.1/v1"
    
    # CORS settings
    CORS_ORIGIN: str = "http://localhost:3000"
    
    # Keycloak settings
    KEYCLOAK_SERVER_URL: str = "http://10.0.1.2:8080"
    KEYCLOAK_REALM: str = "mint-dev"
    KEYCLOAK_CLIENT_ID: str = "mint-back"
    KEYCLOAK_CLIENT_SECRET: Optional[str] = None
    KEYCLOAK_CALLBACK_URI: str = "http://localhost:8000/callback"
    KEYCLOAK_ADMIN_USERNAME: str = "admin"
    KEYCLOAK_ADMIN_PASSWORD: str = "admin"
    KEYCLOAK_ADMIN_CLIENT_ID: str = "admin-cli"
    KEYCLOAK_ADMIN_CLIENT_SECRET: str = "admin-cli-secret"
    
    # JWT settings
    JWT_ALGORITHM: str = "RS256"
    JWT_AUDIENCE: str = "account"
    
    # RabbitMQ and Celery settings
    RABBITMQ_URL: str = "amqp://guest:guest@rabbitmq:5672//"
    MAX_CONCURRENT_WORKFLOWS: int = 10

    model_config = ConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")


settings = Settings()

# 🔍 DEBUG: Log configuration on startup
import logging
logger = logging.getLogger(__name__)
logger.info(f"🔧 CONFIG DEBUG - BACKEND_BASE_URL loaded as: {settings.BACKEND_BASE_URL}")
logger.info(f"🔧 CONFIG DEBUG - ENVIRONMENT: {getattr(settings, 'ENVIRONMENT', 'not set')}")
logger.info(f"🔧 CONFIG DEBUG - .env file path: {os.path.abspath('.env') if os.path.exists('.env') else 'not found'}")
logger.info(f"🔧 STARTUP CONFIG - BACKEND_BASE_URL: {settings.BACKEND_BASE_URL}")