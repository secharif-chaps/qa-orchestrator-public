
from pydantic import ConfigDict
from pydantic_settings import BaseSettings


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
    
    # CORS settings (comma-separated list of allowed origins)
    CORS_ORIGINS: str = "http://localhost:3000,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:5173"
    
    # Keycloak settings
    
    KEYCLOAK_SERVER_URL: str = "https://keycloak.preprod.chapsmind.com"
    KEYCLOAK_REALM: str = "mint-preprod"
    KEYCLOAK_CLIENT_ID: str = "mint-back"
    KEYCLOAK_CLIENT_SECRET: str | None = None
    KEYCLOAK_CALLBACK_URI: str = "http://localhost:8001/callback"
    KEYCLOAK_ADMIN_CLIENT_ID: str = "admin-cli"
    KEYCLOAK_ADMIN_CLIENT_SECRET: str = "admin-cli-secret"
    
    # SQLAlchemy tuning
    DB_POOL_SIZE: int = 10
    DB_MAX_OVERFLOW: int = 20
    DB_POOL_TIMEOUT: int = 30
    DB_POOL_RECYCLE: int = 1800

    # Internal JWT for service-to-service communication
    # Must be the same value in all services (gateway + backends)
    # Generate with: openssl rand -base64 32
    INTERNAL_JWT_SECRET: str = ""
    INTERNAL_JWT_EXPIRY_SECONDS: int = 60

    model_config = ConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")


settings = Settings()