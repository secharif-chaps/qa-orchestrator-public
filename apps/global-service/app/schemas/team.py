"""Team management schemas with permission tiers.

Provides schemas for team member management within an organization,
abstracting individual Keycloak roles into permission tiers.

Ported from the backend monolith (back/app/schemas/team.py).
"""

from pydantic import BaseModel, ConfigDict, Field, field_validator

from app.core.permissions import PermissionTier


class TeamMemberListItem(BaseModel):
    """Team member for list view without permission tier.

    Permissions are lazy-loaded via separate endpoint for performance.
    """

    id: str = Field(..., description="Keycloak user UUID")
    username: str
    email: str
    first_name: str | None = None
    last_name: str | None = None
    is_current_user: bool = False
    created_at: int | None = Field(None, description="Unix timestamp from Keycloak")

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "id": "550e8400-e29b-41d4-a716-446655440000",
                    "username": "jean.dupont",
                    "email": "jean.dupont@example.com",
                    "first_name": "Jean",
                    "last_name": "Dupont",
                    "is_current_user": False,
                    "created_at": 1704880000,
                },
            ],
        },
    )


class TeamMemberListResponse(BaseModel):
    """Paginated response for team members list."""

    data: list[TeamMemberListItem]
    pagination: dict = Field(..., description="Pagination metadata with total, page, limit, total_pages")


class TeamMemberPermissions(BaseModel):
    """Permission tier for a specific team member (lazy-loaded)."""

    user_id: str = Field(..., description="Keycloak user UUID")
    permission_tier: PermissionTier


class TeamMember(BaseModel):
    """Team member with permission tier.

    Abstracts individual Keycloak roles into a simple tier concept.
    """

    id: str = Field(..., description="Keycloak user UUID")
    username: str
    email: str
    first_name: str | None = None
    last_name: str | None = None
    avatar_url: str | None = None
    permission_tier: PermissionTier
    is_current_user: bool = False
    created_at: int | None = Field(None, description="Unix timestamp from Keycloak")

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "id": "550e8400-e29b-41d4-a716-446655440000",
                    "username": "jean.dupont",
                    "email": "jean.dupont@example.com",
                    "first_name": "Jean",
                    "last_name": "Dupont",
                    "avatar_url": None,
                    "permission_tier": "writer",
                    "is_current_user": False,
                    "created_at": 1704880000,
                },
            ],
        },
    )


class UpdateTeamMemberPermissions(BaseModel):
    """Update team member permission tier.

    Simple tier selection - backend handles role synchronization atomically.
    """

    permission_tier: PermissionTier

    @field_validator("permission_tier")
    @classmethod
    def validate_tier(cls, v: PermissionTier) -> PermissionTier:
        if v not in PermissionTier:
            raise ValueError(f"Invalid permission tier: {v}")
        return v


class UpdateTeamMember(BaseModel):
    """Update team member profile and/or permission tier.

    All fields are optional - only provided fields are updated.
    """

    first_name: str | None = Field(None, max_length=100)
    last_name: str | None = Field(None, max_length=100)
    email: str | None = Field(None)
    permission_tier: PermissionTier | None = None

    @field_validator("email")
    @classmethod
    def validate_email(cls, v: str | None) -> str | None:
        if v is None:
            return v
        if "@" not in v or "." not in v.split("@")[-1]:
            raise ValueError("Invalid email address")
        return v.strip().lower()


class InviteTeamMemberRequest(BaseModel):
    """Request body for inviting a new team member."""

    username: str = Field(..., min_length=3, max_length=50, description="Username for the new user")
    email: str = Field(..., description="Email address")
    first_name: str | None = Field(None, max_length=100)
    last_name: str | None = Field(None, max_length=100)
    temporary_password: str = Field(..., description="Temporary password for initial login")
    permission_tier: PermissionTier = Field(
        default=PermissionTier.READER,
        description="Initial permission tier",
    )

    @field_validator("email")
    @classmethod
    def validate_email(cls, v: str) -> str:
        if "@" not in v or "." not in v.split("@")[-1]:
            raise ValueError("Invalid email address")
        return v.strip().lower()

    @field_validator("username")
    @classmethod
    def validate_username(cls, v: str) -> str:
        if not v.replace("_", "").replace("-", "").replace(".", "").isalnum():
            raise ValueError("Username may only contain letters, digits, underscores, hyphens, and dots")
        return v.strip().lower()

    @field_validator("temporary_password")
    @classmethod
    def validate_invite_password(cls, v: str) -> str:
        if len(v) < 8:
            raise ValueError("Password must be at least 8 characters long")
        if not any(c.isupper() for c in v):
            raise ValueError("Password must contain at least one uppercase letter")
        if not any(c.islower() for c in v):
            raise ValueError("Password must contain at least one lowercase letter")
        if not any(c.isdigit() for c in v):
            raise ValueError("Password must contain at least one number")
        if not any(c in "!@#$%^&*()_+-=[]{}|;:,.<>?" for c in v):
            raise ValueError("Password must contain at least one special character")
        return v

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "username": "marie.martin",
                    "email": "marie.martin@example.com",
                    "first_name": "Marie",
                    "last_name": "Martin",
                    "temporary_password": "Welcome1!",
                    "permission_tier": "reader",
                },
            ],
        },
    )


class InviteTeamMemberResponse(BaseModel):
    """Response after inviting a new team member."""

    id: str = Field(..., description="Keycloak user UUID of the new member")
    username: str
    email: str
    first_name: str | None = None
    last_name: str | None = None
    permission_tier: PermissionTier
    temporary_password: str
    message: str = "Team member created. Share the temporary password with the user."


class ResetPasswordRequest(BaseModel):
    """Request body for resetting team member password."""

    temporary_password: str = Field(..., description="Temporary password to set")

    @field_validator("temporary_password")
    @classmethod
    def validate_password(cls, v: str) -> str:
        if len(v) < 8:
            raise ValueError("Password must be at least 8 characters long")

        if not any(c.isupper() for c in v):
            raise ValueError("Password must contain at least one uppercase letter")

        if not any(c.islower() for c in v):
            raise ValueError("Password must contain at least one lowercase letter")

        if not any(c.isdigit() for c in v):
            raise ValueError("Password must contain at least one number")

        if not any(c in "!@#$%^&*()_+-=[]{}|;:,.<>?" for c in v):
            raise ValueError("Password must contain at least one special character")

        return v


class TeamMemberPasswordReset(BaseModel):
    """Response after password reset."""

    temporary_password: str
    message: str = "Password reset successfully. User must change password on next login."
