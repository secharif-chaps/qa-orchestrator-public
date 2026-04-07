from datetime import datetime
from typing import Any

from pydantic import BaseModel, ConfigDict, Field, field_validator


class EventIngest(BaseModel):
    event_type: str = Field(..., description="Event type in source.resource.action format")
    folder_id: str = Field(..., description="Folder ID for event scoping")
    payload: dict[str, Any] = Field(default_factory=dict, description="Event payload")
    entity_id: str | None = Field(None, description="Reference entity ID")
    entity_type: str | None = Field(None, description="Reference entity type")
    summary: str | None = Field(None, description="Human-readable summary")

    @field_validator("event_type")
    @classmethod
    def validate_event_type_format(cls, v: str) -> str:
        parts = v.split(".")
        if len(parts) != 3:
            raise ValueError("event_type must follow source.resource.action format (3 dot-separated parts)")
        if not all(part.strip() for part in parts):
            raise ValueError("Each part of event_type must be non-empty")
        return v


class EventRead(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    event_type: str
    folder_id: str
    source: str
    payload: dict[str, Any]
    organization_id: str
    is_deleted: bool
    created_at: datetime
    entity_id: str | None
    entity_type: str | None
    summary: str | None
