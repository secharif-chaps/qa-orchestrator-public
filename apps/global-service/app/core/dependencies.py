# app/core/dependencies.py
"""
Dependencies for FastAPI endpoints.

Provides dependencies for:
1. Organization context extraction
2. Service instances (TokenManager, KeycloakAdmin, UserPreferences)

Note: Authentication is handled via `idp.get_current_user()` from app.core.keycloak.
"""

from fastapi import Depends
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.organization import get_user_organization
from app.database import get_global_db
from app.services.keycloak_admin import KeycloakAdminService, keycloak_admin_service
from app.services.token_manager import TokenManager
from app.services.user_preferences import UserPreferencesService

# Re-export organization dependencies
# Directly use the function from organization.py
get_organization_context = get_user_organization


# TokenManager dependency
async def get_token_manager(db: AsyncSession = Depends(get_global_db)) -> TokenManager:
    """FastAPI dependency to get TokenManager instance.

    Args:
        db: Async SQLAlchemy session for global_schema database.

    Returns:
        TokenManager instance configured with the async database session.
    """
    return TokenManager(db=db)


# KeycloakAdminService dependency
def get_keycloak_admin() -> KeycloakAdminService:
    """FastAPI dependency to get the KeycloakAdminService singleton.

    Wrapping the singleton in a dependency makes it overridable in tests
    via ``app.dependency_overrides``.
    """
    return keycloak_admin_service


# UserPreferencesService dependency
async def get_user_preferences_service(
    db: AsyncSession = Depends(get_global_db),
) -> UserPreferencesService:
    """FastAPI dependency to get UserPreferencesService instance.

    Args:
        db: Async SQLAlchemy session for global_schema database.

    Returns:
        UserPreferencesService instance configured with the async database session.
    """
    return UserPreferencesService(db=db)
