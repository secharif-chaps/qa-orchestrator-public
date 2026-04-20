"""Pydantic schemas for account self-service endpoints.

Covers user session management and activity event responses.
"""

from datetime import datetime

from pydantic import BaseModel, ConfigDict, Field


class SessionResponse(BaseModel):
    """A single active user session."""

    id: str = Field(..., description="Keycloak session UUID")
    ip_address: str = Field(..., description="IP address of the session")
    started_at: datetime = Field(..., description="When the session started")
    last_access: datetime = Field(..., description="Last activity timestamp")
    clients: dict[str, str] = Field(
        default_factory=dict,
        description="Map of client IDs to client names",
    )
    is_current: bool = Field(..., description="Whether this is the caller's session")

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
                    "ip_address": "192.168.1.42",
                    "started_at": "2025-03-15T08:30:00Z",
                    "last_access": "2025-03-15T10:45:00Z",
                    "clients": {
                        "chapsmind-front": "ChapsMind Frontend",
                    },
                    "is_current": True,
                },
            ],
        },
    )


class SessionListResponse(BaseModel):
    """List of active sessions for the current user."""

    sessions: list[SessionResponse] = Field(..., description="Active sessions")
    current_session_id: str | None = Field(
        None,
        description="Session ID extracted from the caller's JWT (if available)",
    )


class ActivityEventResponse(BaseModel):
    """A single activity event from Keycloak."""

    id: str = Field(..., description="Event identifier (timestamp-based)")
    type: str = Field(..., description="Raw Keycloak event type (e.g. LOGIN, LOGOUT)")
    display_type: str = Field(..., description="Category: login, security, or update")
    icon: str = Field(..., description="Font Awesome icon class")
    title: str = Field(..., description="User-friendly event title")
    description: str = Field(..., description="Human-readable event description")
    ip_address: str | None = Field(None, description="IP address of the event")
    timestamp: datetime = Field(..., description="When the event occurred")

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "id": "1710496200000-LOGIN",
                    "type": "LOGIN",
                    "display_type": "login",
                    "icon": "fa-right-to-bracket",
                    "title": "Successful Login",
                    "description": "Logged in from 192.168.1.42 using Chrome on Windows",
                    "ip_address": "192.168.1.42",
                    "timestamp": "2025-03-15T10:30:00Z",
                },
            ],
        },
    )


class ActivityEventsResponse(BaseModel):
    """Paginated list of activity events."""

    events: list[ActivityEventResponse] = Field(..., description="Activity events")
    page: int = Field(..., description="Current page number (1-indexed)")
    size: int = Field(..., description="Page size")
    has_more: bool = Field(..., description="Whether more events exist beyond this page")
