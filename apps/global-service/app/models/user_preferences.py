"""User preferences database model for global-service.

Stores user-specific preferences (e.g., AI preferences for Chapse Assist)
using a flexible JSONB structure. Each user has one preferences record
identified by their Keycloak user UUID.

Stored in global_schema as a global (non-module-specific) resource.
"""

from typing import Any

from sqlalchemy import JSON, Column, DateTime, Integer, String
from sqlalchemy.sql import func

from app.database import GLOBAL_SCHEMA, GlobalBase


class UserPreferences(GlobalBase):
    """User preferences storage with JSONB for flexibility.

    Uses a single JSONB column to store categorized preferences,
    allowing new preference categories to be added without schema changes.

    Structure: {"ai": {role, goals_text, ...}, "future_category": {...}}

    Attributes:
        id: Auto-incrementing primary key
        user_id: Keycloak user UUID (from JWT sub claim)
        preferences: JSONB blob containing categorized preferences
        created_at: Record creation timestamp
        updated_at: Last update timestamp
    """

    __tablename__ = "user_preferences"
    __table_args__ = {"schema": GLOBAL_SCHEMA}

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(String, nullable=False, unique=True, index=True)
    # JSON type maps to JSONB on PostgreSQL and JSON on SQLite (for tests)
    preferences = Column(JSON, nullable=False, server_default="{}")
    created_at = Column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at = Column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now()
    )

    def __repr__(self) -> str:
        return f"<UserPreferences(user_id='{self.user_id}')>"

    def get_preference(self, category: str) -> dict[str, Any] | None:
        """Get preferences for a specific category."""
        if not self.preferences:
            return None
        return self.preferences.get(category)

    def set_preference(self, category: str, data: dict[str, Any]) -> None:
        """Set preferences for a specific category."""
        if not self.preferences:
            self.preferences = {}
        self.preferences[category] = data

    def get_ai_preferences(self) -> dict[str, Any] | None:
        """Convenience method to get AI preferences."""
        return self.get_preference("ai")

    def set_ai_preferences(self, data: dict[str, Any]) -> None:
        """Convenience method to set AI preferences."""
        self.set_preference("ai", data)
