"""API router aggregation for global-service endpoints."""

from fastapi import APIRouter

from app.api.endpoints import modules, organization, tokens

api_router = APIRouter()

# Include routes from different modules
api_router.include_router(tokens.router)  # Token balance management
api_router.include_router(organization.router)  # Organization context
api_router.include_router(modules.router)  # Module enablement
