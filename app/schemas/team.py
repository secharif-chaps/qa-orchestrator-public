"""Team management schemas with permission tiers.

Provides simplified schemas for team member management,
abstracting individual Keycloak roles into permission tiers.
"""

from typing import Optional
from pydantic import BaseModel, Field, field_validator

from app.core.permissions import PermissionTier


class TeamMember(BaseModel):
    """Team member representation with permission tier.

    Abstracts away individual Keycloak roles in favor of a simple tier concept,
    making it easier for managers to understand and modify permissions.
    """

    id: str = Field(..., description="Keycloak user UUID")
    username: str
    email: str
    first_name: Optional[str] = None
    last_name: Optional[str] = None
    avatar_url: Optional[str] = None
    permission_tier: PermissionTier
    is_current_user: bool = False
    created_at: Optional[int] = Field(None, description="Unix timestamp from Keycloak")

    class Config:
        from_attributes = True


class UpdateTeamMemberPermissions(BaseModel):
    """Update team member permission tier.

    Simple tier selection - backend handles role synchronization atomically.
    """

    permission_tier: PermissionTier

    @field_validator('permission_tier')
    @classmethod
    def validate_tier(cls, v: PermissionTier) -> PermissionTier:
        """Validate permission tier is valid enum value."""
        if v not in PermissionTier:
            raise ValueError(f"Invalid permission tier: {v}")
        return v


class TeamMemberPasswordReset(BaseModel):
    """Response after password reset containing temporary password.

    Password is set as temporary in Keycloak, forcing user to change it on next login.
    """

    temporary_password: str
    message: str = "Password reset successfully. User must change password on next login."
