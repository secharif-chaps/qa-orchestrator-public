import logging
import os
from enum import StrEnum

from pydantic import ConfigDict
from pydantic_settings import BaseSettings


class LLMProvider(StrEnum):
    AZURE = "azure"
    OPENAI = "openai"


class Settings(BaseSettings):
    # API settings
    API_HOST: str = "0.0.0.0"
    API_PORT: int = 8000

    # Logging settings
    LOG_LEVEL: str = "INFO"  # DEBUG, INFO, WARNING, ERROR, CRITICAL

    # Database settings
    DATABASE_URL: str = "postgresql://postgres:postgres@db:5432/screen_db"

    # CORS settings
    CORS_ORIGIN: str = "http://localhost"
    CORS_EXTRA_ORIGINS: str = ""  # Comma-separated extra origins (e.g. preprod IPs)

    # Keycloak settings
    KEYCLOAK_SERVER_URL: str = "https://keycloak.preprod.chapsmind.com"
    KEYCLOAK_REALM: str = "chapsmind"
    KEYCLOAK_CLIENT_ID: str = "chapsmind-global-service-back"
    KEYCLOAK_CLIENT_SECRET: str | None = None
    KEYCLOAK_CALLBACK_URI: str = "http://localhost/callback"
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

    # Global-service URL for internal API calls (token consumption)
    # In Docker/K8s, this is the internal service name
    GLOBAL_SERVICE_URL: str = "http://global-service:8000/api"

    # LLM settings (OpenAI-compatible: Azure AI Foundry, LiteLLM, etc.)
    LLM_PROVIDER: LLMProvider = LLMProvider.OPENAI
    LLM_API_KEY: str = ""
    LLM_BASE_URL: str = ""
    LLM_MODEL: str = "gpt-5.1-sweden"
    LLM_API_VERSION: str = ""  # Azure only: e.g. "2024-05-01-preview"

    # Task timeout settings
    # Tasks running longer than this are considered stale and will be marked as ERROR
    TASK_TIMEOUT_MINUTES: int = 5

    # SYSTRAN Translation API settings
    SYSTRAN_API_KEY: str | None = None
    SYSTRAN_API_URL: str = "https://api-translate.systran.net"

    # Rate limiting
    CHAPSE_CHAT_RATE_LIMIT: int = 15  # Max requests per minute per user on /chapse/chat

    model_config = ConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    # Encryption settings
    # Key used to encrypt sensitive data (API keys) in database
    # Generate with: python -c "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())"
    # WARNING: Default value is for dev/test only. Must be set via environment variable in production.
    ENCRYPTION_KEY: str = "8sFxzWzVK2M7d3-KN7TqPmzXH0Yw5FqGhL9Qx1Jb2c4="


settings = Settings()

logger = logging.getLogger(__name__)
logger.info(f"🔧 CONFIG DEBUG - .env file path: {os.path.abspath('.env') if os.path.exists('.env') else 'not found'}")
