from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    # API settings
    API_HOST: str = "0.0.0.0"
    API_PORT: int = 8000

    # Enable uvicorn hot-reload (file-watcher). Off by default; set DEV_MODE=true in local dev only.
    DEV_MODE: bool = False

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

    # Credit costs per channel type (ADR-0020)
    STREAM_COST_TEAMS: int = 5
    STREAM_COST_SLACK: int = 5
    STREAM_COST_WEBHOOK: int = 2

    # Newsletter / email delivery via NP6 (ADR-0021 Phase 1a — NP6 plumbing)
    # All default to empty so absence in dev does not crash boot. The integrations/email
    # module is responsible for raising clear errors when these are required at runtime.
    NP6_BASE_URL: str = ""
    NP6_API_KEY: str = ""
    NP6_FROM_EMAIL: str = ""

    # Id of the NP6 segment used as `testSegments` during phase-1 validation
    # (POST /actions/{id}/validation {fortest:true, testSegments:[id]}).
    # On tenant CHAP/02C the "BAT TEST" segment id is 1319.
    NP6_BAT_TEST_SEGMENT_ID: int = 1319

    # HMAC secret for signing one-click unsubscribe tokens (ADR-0021 §Security Model).
    STREAM_UNSUBSCRIBE_SECRET: str = ""

    # Cap on events folded into a single newsletter batch (ADR-0021 §Operational Constraints).
    # Default 50 keeps rendered MJML under Gmail's 102 KB clip threshold.
    STREAM_NEWSLETTER_MAX_EVENTS_PER_BATCH: int = 50

    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")


settings = Settings()
