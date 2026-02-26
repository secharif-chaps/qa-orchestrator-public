"""Pydantic schemas for account self-service endpoints.

Covers user session management responses.
"""

from datetime import datetime
from typing import Optional

from pydantic import BaseModel, Field


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


class SessionListResponse(BaseModel):
    """List of active sessions for the current user."""

    sessions: list[SessionResponse] = Field(..., description="Active sessions")
    current_session_id: Optional[str] = Field(
        None,
        description="Session ID extracted from the caller's JWT (if available)",
    )
