"""Pydantic schemas for organization modules.

These schemas are for module configuration (enabled/disabled state) only.
Token management uses global token balance in schemas/token.py.
"""

from datetime import datetime

from pydantic import BaseModel, ConfigDict

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

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "name": "screen",
                    "enabled": True,
                    "created_at": "2025-01-10T09:00:00Z",
                    "updated_at": "2025-03-15T14:20:00Z",
                },
            ],
        },
    )


class OrganizationModulesResponse(BaseModel):
    """Response model for list of organization modules."""

    modules: list[OrganizationModuleResponse]


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

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "module": "target",
                    "enabled": False,
                    "created_at": "2025-01-10T09:00:00Z",
                    "updated_at": "2025-03-15T16:45:00Z",
                },
            ],
        },
    )
