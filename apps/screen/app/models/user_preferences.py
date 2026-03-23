from typing import Any

from sqlalchemy import Column, DateTime, Integer, String
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.sql import func

from app.database import Base


class UserPreferences(Base):
    """Generic user preferences storage with JSONB for flexibility"""

    __tablename__ = "user_preferences"

    id = Column(Integer, primary_key=True, index=True)
    keycloak_user_id = Column(String(255), nullable=False, unique=True, index=True)
    preferences = Column(JSONB, nullable=False, server_default="{}")
    created_at = Column(DateTime(timezone=True), nullable=False, server_default=func.now())
    updated_at = Column(DateTime(timezone=True), nullable=False, server_default=func.now(), onupdate=func.now())

    def __repr__(self):
        return f"<UserPreferences(keycloak_user_id='{self.keycloak_user_id}')>"

    def get_preference(self, category: str) -> dict[str, Any] | None:
        """Get preferences for a specific category"""
        if not self.preferences:
            return None
        return self.preferences.get(category)

    def set_preference(self, category: str, data: dict[str, Any]) -> None:
        """Set preferences for a specific category"""
        if not self.preferences:
            self.preferences = {}
        self.preferences[category] = data

    def get_ai_preferences(self) -> dict[str, Any] | None:
        """Convenience method to get AI preferences"""
        return self.get_preference("ai")

    def set_ai_preferences(self, data: dict[str, Any]) -> None:
        """Convenience method to set AI preferences"""
        self.set_preference("ai", data)
