"""API router aggregation for global-service endpoints."""

from fastapi import APIRouter

from app.api.endpoints import (
    account,
    ai_preferences,
    folder,
    internal,
    modules,
    organization,
    organizations,
    team,
    tokens,
    users,
)
from app.api.routes import target_proxy

api_router = APIRouter()

# Include routes from different modules
api_router.include_router(internal.router)  # Internal service-to-service API
api_router.include_router(tokens.router)  # Token balance management
api_router.include_router(organization.router)  # Organization context
api_router.include_router(modules.router)  # Module enablement
api_router.include_router(account.router)  # Account self-service (sessions) - before /users to avoid route shadowing
api_router.include_router(users.router)  # Admin user management
api_router.include_router(ai_preferences.router)  # AI preferences (Chapse Assist)
api_router.include_router(folder.router)  # Folder management
api_router.include_router(team.router)  # Team management
api_router.include_router(organizations.router)  # Admin organization management
# Target proxy — must be registered before the generic catch-all proxy router
api_router.include_router(target_proxy.router, prefix="/target", tags=["target"])
