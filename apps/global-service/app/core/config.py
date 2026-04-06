import os

from pydantic import ConfigDict, model_validator
from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    # Server
    API_HOST: str = "0.0.0.0"
    API_PORT: int = 8000
    SCREEN_BASE_URL: str = "http://screen:8000"
    STREAM_BASE_URL: str = "http://stream:8000"

    # Token lock timeout (seconds) for the lock/unlock pattern
    TOKEN_LOCK_TIMEOUT_SECONDS: int = 30

    # GRPC settings
    GRPC_PORT: int = 50051

    # Logging
    LOG_LEVEL: str = "INFO"

    # Database
    DATABASE_URL: str = "postgresql://postgres:postgres@db:5432/global_db"
    DB_POOL_SIZE: int = 10
    DB_MAX_OVERFLOW: int = 20
    DB_POOL_TIMEOUT: int = 30
    DB_POOL_RECYCLE: int = 1800

    # CORS
    CORS_ORIGINS: str = "http://localhost"

    # Trusted proxies — CIDR ranges that are allowed to set X-Forwarded-* headers.
    # Same format as Symfony TRUSTED_PROXIES (comma-separated CIDRs).
    TRUSTED_PROXIES: str = "127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16"

    # Trusted hosts regex for X-Forwarded-Host validation.
    # Same format as Symfony TRUSTED_HOSTS: ^(host1|host2\.example\.com)$
    # Used by the proxy to validate forwarded hosts and prevent open redirects.
    TRUSTED_HOSTS: str = "^localhost$"

    # Keycloak
    KEYCLOAK_SERVER_URL: str = "http://localhost:8080"
    KEYCLOAK_PUBLIC_URL: str = "http://localhost:8080"
    KEYCLOAK_REALM: str = "chapsmind"
    KEYCLOAK_CLIENT_ID: str = "chapsmind-global-service-back"
    KEYCLOAK_CLIENT_SECRET: str | None = None
    KEYCLOAK_CALLBACK_URI: str = "http://localhost/callback"
    KEYCLOAK_ADMIN_CLIENT_ID: str = "admin-cli"
    KEYCLOAK_ADMIN_CLIENT_SECRET: str = "admin-cli-secret"

    # Health checks
    HEALTH_CHECK_KEYCLOAK_ENABLED: bool = False

    # OpenAPI docs — active by default, disable with ENABLE_DOCS=false if needed
    ENABLE_DOCS: bool = True

    # Internal JWT for service-to-service communication
    # Must be the same value in all services (gateway + backends)
    # Generate with: openssl rand -base64 32
    INTERNAL_JWT_SECRET: str = ""
    INTERNAL_JWT_EXPIRY_SECONDS: int = 60

    model_config = ConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    @model_validator(mode="after")
    def validate_internal_jwt_secret(self) -> "Settings":
        """Fail fast if INTERNAL_JWT_SECRET is not configured (unless in test/CI mode)."""
        is_test = (
            os.environ.get("SKIP_KEYCLOAK_INIT", "").lower() in ("1", "true", "yes")
            or "PYTEST_CURRENT_TEST" in os.environ  # Running under pytest
            or os.environ.get("CI", "").lower() in ("1", "true", "yes")  # CI pipeline
        )
        if not self.INTERNAL_JWT_SECRET and not is_test:
            raise ValueError("INTERNAL_JWT_SECRET must be set. Generate one with: openssl rand -base64 32")
        return self


settings = Settings()
