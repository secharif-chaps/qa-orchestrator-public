from datetime import datetime
from typing import Any

from pydantic import BaseModel, ConfigDict, Field, HttpUrl, field_validator, model_validator

from app.models.stream import ChannelType, StreamMode, StreamStatus

# --- Dispatch schemas ---

# --- Channel config models (for API validation) ---


class TeamsConfig(BaseModel):
    workflow_url: HttpUrl = Field(..., description="Microsoft Teams Power Automate workflow URL")


class SlackWebhookConfig(BaseModel):
    webhook_url: HttpUrl = Field(..., description="Slack incoming webhook URL")


class WebhookConfig(BaseModel):
    url: HttpUrl = Field(..., description="Target webhook URL")
    method: str = Field("POST", description="HTTP method (POST or PUT)")
    headers: dict[str, str] = Field(default_factory=dict, description="Custom headers to include")
    secret: str | None = Field(None, description="Shared secret for HMAC signature verification")


CHANNEL_CONFIG_MAP: dict[ChannelType, type[BaseModel]] = {
    ChannelType.TEAMS: TeamsConfig,
    ChannelType.SLACK_WEBHOOK: SlackWebhookConfig,
    ChannelType.WEBHOOK: WebhookConfig,
}

# --- Stream schemas ---


class StreamCreate(BaseModel):
    name: str = Field(..., min_length=1, max_length=255, description="Stream name")
    description: str | None = Field(None, description="Optional description")
    channel_type: ChannelType
    channel_config: dict[str, Any] = Field(..., description="Channel-specific configuration")
    mode: StreamMode
    cron_expression: str | None = Field(None, description="Cron expression (required for recurrence mode)")
    subscribed_events: list[str] = Field(default_factory=list, description="Event types to subscribe to")

    @field_validator("subscribed_events", mode="before")
    @classmethod
    def validate_subscribed_events(cls, v: list) -> list:
        for item in v:
            parts = str(item).split(".")
            if len(parts) != 3 or not all(p.strip() for p in parts):
                raise ValueError(f"subscribed_events item '{item}' must follow source.resource.action format")
        return v

    @model_validator(mode="after")
    def validate_mode_and_channel(self) -> "StreamCreate":
        if self.mode == StreamMode.RECURRENCE and not self.cron_expression:
            raise ValueError("cron_expression is required for recurrence mode")
        if self.mode == StreamMode.LIVE and self.cron_expression:
            raise ValueError("cron_expression is forbidden for live mode")

        config_validators = {
            ChannelType.TEAMS: TeamsConfig,
            ChannelType.SLACK_WEBHOOK: SlackWebhookConfig,
            ChannelType.WEBHOOK: WebhookConfig,
        }
        validator = config_validators.get(self.channel_type)
        if validator:
            try:
                validator(**self.channel_config)
            except Exception as exc:
                raise ValueError(f"channel_config is invalid for {self.channel_type}: {exc}") from exc
        return self


class StreamUpdate(BaseModel):
    name: str | None = Field(None, min_length=1, max_length=255)
    description: str | None = None
    channel_config: dict[str, Any] | None = None
    cron_expression: str | None = None
    subscribed_events: list[str] | None = None


class StreamStatusUpdate(BaseModel):
    status: StreamStatus = Field(..., description="New status for the stream")


class StreamRead(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    name: str
    description: str | None
    folder_id: str
    channel_type: ChannelType
    channel_config: dict[str, Any]
    mode: StreamMode
    cron_expression: str | None
    status: StreamStatus
    organization_id: str
    owner_id: str
    owner_username: str | None
    subscribed_events: list[str]
    created_at: datetime
    updated_at: datetime


# --- Test connection / dispatch schemas ---


class TestConnectionRequest(BaseModel):
    channel_type: ChannelType
    channel_config: dict[str, Any] = Field(..., description="Channel-specific configuration to test")


class TestConnectionResponse(BaseModel):
    success: bool
    error: str | None = None


class DispatchResponse(BaseModel):
    dispatched_count: int
    failed_count: int
