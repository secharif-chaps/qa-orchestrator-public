from pydantic import ConfigDict
from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    # API settings
    API_HOST: str = "0.0.0.0"
    API_PORT: int = 8001
    SCREEN_BASE_URL: str = "http://screen:8000"  # Internal Docker service name for screen backend

    # Public Keycloak URL for Swagger UI OAuth flows
    KEYCLOAK_PUBLIC_URL: str = "http://localhost:8080"

    # Token lock timeout (seconds) for the lock/unlock pattern
    TOKEN_LOCK_TIMEOUT_SECONDS: int = 30

    # GRPC settings
    GRPC_PORT: int = 50051

    # Logging settings
    LOG_LEVEL: str = "INFO"  # DEBUG, INFO, WARNING, ERROR, CRITICAL

    # Database settings
    DATABASE_URL: str = "postgresql://postgres:postgres@db:5432/global_db"

    # CORS settings (comma-separated list of allowed origins)
    CORS_ORIGINS: str = "http://localhost"

    # Keycloak settings

    KEYCLOAK_SERVER_URL: str = "https://keycloak.preprod.chapsmind.com"
    KEYCLOAK_REALM: str = "mint-preprod"
    KEYCLOAK_CLIENT_ID: str = "mint-back"
    KEYCLOAK_CLIENT_SECRET: str | None = None
    KEYCLOAK_CALLBACK_URI: str = "http://localhost/callback"
    KEYCLOAK_ADMIN_CLIENT_ID: str = "admin-cli"
    KEYCLOAK_ADMIN_CLIENT_SECRET: str = "admin-cli-secret"

    # SQLAlchemy tuning
    DB_POOL_SIZE: int = 10
    DB_MAX_OVERFLOW: int = 20
    DB_POOL_TIMEOUT: int = 30
    DB_POOL_RECYCLE: int = 1800

    # Health check settings
    HEALTH_CHECK_KEYCLOAK_ENABLED: bool = False

    # OpenAPI docs — active by default, disable with ENABLE_DOCS=false if needed
    ENABLE_DOCS: bool = True

    # Internal JWT for service-to-service communication
    # Must be the same value in all services (gateway + backends)
    # Generate with: openssl rand -base64 32
    INTERNAL_JWT_SECRET: str = ""
    INTERNAL_JWT_EXPIRY_SECONDS: int = 60

    model_config = ConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")


settings = Settings()
