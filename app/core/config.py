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
    DIFY_API_KEY: str = "app-jGJl5PAPQnAE0IzFAfkV3XjO"  # Fallback API key for chat workflows
    DIFY_URL: str = "http://10.6.1.10/v1"
    DIFY_CHAT_API_KEY: str = "app-jGJl5PAPQnAE0IzFAfkV3XjO"  # API key for quick actions chat app

    # CORS settings
    CORS_ORIGIN: str = "http://localhost:3000"

    # Keycloak settings

    KEYCLOAK_SERVER_URL: str = "https://keycloak.preprod.chapsmind.com"
    KEYCLOAK_REALM: str = "mint-preprod"
    KEYCLOAK_CLIENT_ID: str = "mint-back"
    KEYCLOAK_CLIENT_SECRET: Optional[str] = None
    KEYCLOAK_CALLBACK_URI: str = "http://localhost:8000/callback"
    KEYCLOAK_ADMIN_CLIENT_ID: str = "admin-cli"
    KEYCLOAK_ADMIN_CLIENT_SECRET: str = "admin-cli-secret"

    # JWT settings
    JWT_ALGORITHM: str = "RS256"
    JWT_AUDIENCE: str = "account"

    # Internal JWT for gateway communication
    # Must be the same value as in global-service (gateway)
    # Generate with: openssl rand -base64 32
    INTERNAL_JWT_SECRET: str = ""
    INTERNAL_JWT_EXPIRY_SECONDS: int = 60
    # Optional: Comma-separated list of allowed IP ranges (CIDR notation)
    # for internal authentication. If empty, IP validation is disabled.
    # Example: "10.244.0.0/16,172.16.0.0/12"
    INTERNAL_ALLOWED_IPS: str = ""

    # RabbitMQ and Celery settings
    RABBITMQ_URL: str = "amqp://guest:guest@rabbitmq:5672//"
    MAX_CONCURRENT_WORKFLOWS: int = 10

    # Task timeout settings
    # Tasks running longer than this are considered stale and will be marked as ERROR
    TASK_TIMEOUT_MINUTES: int = 5

    # SYSTRAN Translation API settings
    SYSTRAN_API_KEY: Optional[str] = None
    SYSTRAN_API_URL: str = "https://api-translate.systran.net"

    model_config = ConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    # Encryption settings
    # Key used to encrypt sensitive data (API keys) in database
    # Generate with: python -c "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())"
    # WARNING: Default value is for dev/test only. Must be set via environment variable in production.
    ENCRYPTION_KEY: str = "8sFxzWzVK2M7d3-KN7TqPmzXH0Yw5FqGhL9Qx1Jb2c4="


settings = Settings()

# 🔍 DEBUG: Log configuration on startup
import logging
logger = logging.getLogger(__name__)
logger.info(f"🔧 CONFIG DEBUG - BACKEND_BASE_URL loaded as: {settings.BACKEND_BASE_URL}")
logger.info(f"🔧 CONFIG DEBUG - ENVIRONMENT: {getattr(settings, 'ENVIRONMENT', 'not set')}")
logger.info(f"🔧 CONFIG DEBUG - .env file path: {os.path.abspath('.env') if os.path.exists('.env') else 'not found'}")
logger.info(f"🔧 STARTUP CONFIG - BACKEND_BASE_URL: {settings.BACKEND_BASE_URL}")
