"""Pydantic schemas for organization modules.

These schemas are for module configuration (enabled/disabled state) only.
Token management has been moved to global token balance in schemas/token.py.
"""

from datetime import datetime
from typing import List

from pydantic import BaseModel

from app.models.organization import ModuleName


class OrganizationModuleResponse(BaseModel):
    """Response model for organization module configuration.

    Note: token_count has been removed - tokens are now managed globally
    at the organization level via /organizations/{id}/tokens endpoints.
    """

    name: ModuleName
    enabled: bool
    created_at: datetime
    updated_at: datetime | None = None

    class Config:
        from_attributes = True


class OrganizationModulesResponse(BaseModel):
    """Response model for list of organization modules."""

    modules: List[OrganizationModuleResponse]


class ModuleUpdateRequest(BaseModel):
    """Request model for updating module configuration.

    Note: token_count has been removed - use /organizations/{id}/tokens
    endpoints for token management.
    """

    enabled: bool | None = None


class ModuleToggleResponse(BaseModel):
    """Response model for module toggle operation."""

    module: ModuleName
    enabled: bool
    created_at: datetime
    updated_at: datetime | None = None

    class Config:
        from_attributes = True
