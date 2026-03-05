from typing import Any, Optional

from sqlalchemy.orm import Session

from app.models.user_preferences import UserPreferences


class UserPreferencesService:
    def __init__(self, db: Session):
        self.db = db

    def get_user_preferences(self, keycloak_user_id: str) -> Optional[UserPreferences]:
        """Get full preferences object for a user"""
        return self.db.query(UserPreferences).filter(
            UserPreferences.keycloak_user_id == keycloak_user_id
        ).first()

    def get_ai_preferences(self, keycloak_user_id: str) -> Optional[dict[str, Any]]:
        """Get AI preferences for a user from JSONB"""
        user_prefs = self.get_user_preferences(keycloak_user_id)
        if not user_prefs or not user_prefs.preferences:
            return None
        return user_prefs.preferences.get('ai')

    def set_ai_preferences(self, keycloak_user_id: str, ai_data: dict[str, Any]) -> dict[str, Any]:
        """Create or update AI preferences for a user"""
        user_prefs = self.get_user_preferences(keycloak_user_id)

        if user_prefs:
            # Update existing preferences
            if not user_prefs.preferences:
                user_prefs.preferences = {}
            user_prefs.preferences['ai'] = ai_data
            # Mark as modified for SQLAlchemy to detect JSONB change
            from sqlalchemy.orm.attributes import flag_modified
            flag_modified(user_prefs, 'preferences')
        else:
            # Create new preferences
            user_prefs = UserPreferences(
                keycloak_user_id=keycloak_user_id,
                preferences={'ai': ai_data}
            )
            self.db.add(user_prefs)

        self.db.commit()
        self.db.refresh(user_prefs)
        return user_prefs.preferences.get('ai')

    def has_ai_preferences(self, keycloak_user_id: str) -> bool:
        """Check if user has AI preferences configured"""
        ai_prefs = self.get_ai_preferences(keycloak_user_id)
        return ai_prefs is not None

    def get_preference_category(self, keycloak_user_id: str, category: str) -> Optional[dict[str, Any]]:
        """Get preferences for any category (generic method for future use)"""
        user_prefs = self.get_user_preferences(keycloak_user_id)
        if not user_prefs or not user_prefs.preferences:
            return None
        return user_prefs.preferences.get(category)

    def set_preference_category(self, keycloak_user_id: str, category: str, data: dict[str, Any]) -> dict[str, Any]:
        """Set preferences for any category (generic method for future use)"""
        user_prefs = self.get_user_preferences(keycloak_user_id)

        if user_prefs:
            # Update existing
            if not user_prefs.preferences:
                user_prefs.preferences = {}
            user_prefs.preferences[category] = data
            from sqlalchemy.orm.attributes import flag_modified
            flag_modified(user_prefs, 'preferences')
        else:
            # Create new
            user_prefs = UserPreferences(
                keycloak_user_id=keycloak_user_id,
                preferences={category: data}
            )
            self.db.add(user_prefs)

        self.db.commit()
        self.db.refresh(user_prefs)
        return user_prefs.preferences.get(category)
