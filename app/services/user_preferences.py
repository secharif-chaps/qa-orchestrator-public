"""User preferences service for managing user-specific settings.

Provides async CRUD operations for user preferences stored in JSONB format.
Currently supports AI preferences for Chapse Assist, designed to be extensible
for future preference categories.
"""

from typing import Any, Dict, Optional

from sqlalchemy import select
from sqlalchemy.dialects.postgresql import insert
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm.attributes import flag_modified

from app.core.logging_config import get_logger
from app.models.user_preferences import UserPreferences

logger = get_logger(__name__)


class UserPreferencesService:
    """Service for managing user preferences.

    Attributes:
        db: Async SQLAlchemy database session
    """

    def __init__(self, db: AsyncSession):
        self.db = db

    async def _get_user_preferences(self, user_id: str) -> Optional[UserPreferences]:
        """Get the full preferences record for a user.

        Args:
            user_id: Keycloak user UUID

        Returns:
            UserPreferences record or None if not found
        """
        result = await self.db.execute(
            select(UserPreferences).filter(UserPreferences.user_id == user_id)
        )
        return result.scalar_one_or_none()

    async def get_ai_preferences(self, user_id: str) -> Optional[Dict[str, Any]]:
        """Get AI preferences for a user.

        Args:
            user_id: Keycloak user UUID

        Returns:
            AI preferences dict or None if not configured
        """
        user_prefs = await self._get_user_preferences(user_id)
        if not user_prefs or not user_prefs.preferences:
            return None
        return user_prefs.preferences.get("ai")

    async def set_ai_preferences(
        self, user_id: str, ai_data: Dict[str, Any]
    ) -> Dict[str, Any]:
        """Create or update AI preferences for a user.

        Uses get-then-upsert pattern: fetches existing record, then either
        updates the JSONB field or creates a new record.

        Args:
            user_id: Keycloak user UUID
            ai_data: AI preferences data to store

        Returns:
            The stored AI preferences dict

        Raises:
            SQLAlchemyError: If database operation fails
        """
        user_prefs = await self._get_user_preferences(user_id)

        if user_prefs:
            # Update existing preferences
            if not user_prefs.preferences:
                user_prefs.preferences = {}
            user_prefs.preferences["ai"] = ai_data
            # Mark as modified for SQLAlchemy to detect JSONB change
            flag_modified(user_prefs, "preferences")
        else:
            # Create new preferences record
            user_prefs = UserPreferences(
                user_id=user_id,
                preferences={"ai": ai_data},
            )
            self.db.add(user_prefs)

        try:
            await self.db.commit()
            await self.db.refresh(user_prefs)
        except SQLAlchemyError as e:
            await self.db.rollback()
            logger.error(
                "Failed to set AI preferences",
                extra={
                    "user_id": user_id,
                    "error": str(e),
                },
            )
            raise

        logger.info(
            "AI preferences updated",
            extra={"user_id": user_id},
        )

        return user_prefs.preferences.get("ai")

    async def has_ai_preferences(self, user_id: str) -> bool:
        """Check if user has AI preferences configured.

        Args:
            user_id: Keycloak user UUID

        Returns:
            True if AI preferences exist
        """
        ai_prefs = await self.get_ai_preferences(user_id)
        return ai_prefs is not None
