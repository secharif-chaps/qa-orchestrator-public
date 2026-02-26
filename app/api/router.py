"""API router aggregation for global-service endpoints."""

from fastapi import APIRouter

from app.api.endpoints import ai_preferences, internal, modules, organization, tokens, folder, users

api_router = APIRouter()

# Include routes from different modules
api_router.include_router(internal.router)  # Internal service-to-service API
api_router.include_router(tokens.router)  # Token balance management
api_router.include_router(organization.router)  # Organization context
api_router.include_router(modules.router)  # Module enablement
api_router.include_router(users.router)  # Admin user management
api_router.include_router(ai_preferences.router)  # AI preferences (Chapse Assist)
api_router.include_router(folder.router)  # Folder management
