from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    # API settings
    API_HOST: str = "0.0.0.0"
    API_PORT: int = 8000

    # Logging settings
    LOG_LEVEL: str = "INFO"

    # Database settings
    DATABASE_URL: str = "postgresql://postgres:postgres@db:5432/stream_db"

    # Frontend base URL for building links in notifications
    APP_BASE_URL: str = "http://localhost"

    # Internal JWT for gateway communication
    INTERNAL_JWT_SECRET: str = ""
    INTERNAL_JWT_EXPIRY_SECONDS: int = 60
    INTERNAL_ALLOWED_IPS: str = ""

    # Global Service URL for service-to-service calls (token consumption, etc.)
    GLOBAL_SERVICE_URL: str = "http://global-service:8000/api"

    # Credit costs per channel type (ADR-0016)
    STREAM_COST_TEAMS: int = 5
    STREAM_COST_SLACK: int = 5
    STREAM_COST_WEBHOOK: int = 2

    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")


settings = Settings()
