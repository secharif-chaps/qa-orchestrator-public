from datetime import datetime
from uuid import UUID

from pydantic import BaseModel, ConfigDict, EmailStr


class UserBase(BaseModel):
    username: str
    email: EmailStr
    first_name: str | None = None
    last_name: str | None = None
    is_active: bool = True


class UserCreate(UserBase):
    keycloak_id: str


class UserUpdate(BaseModel):
    first_name: str | None = None
    last_name: str | None = None
    is_active: bool | None = None


class User(UserBase):
    id: UUID
    keycloak_id: str
    roles: list[str] | None = None
    created_at: datetime
    updated_at: datetime | None = None

    model_config = ConfigDict(from_attributes=True)


class UserInDB(User):
    pass


# Auth-related schemas
class Token(BaseModel):
    access_token: str
    refresh_token: str | None = None
    token_type: str = "bearer"
    expires_in: int


class TokenData(BaseModel):
    """Legacy token data schema.

    NOTE: This schema is deprecated and kept only for backward compatibility
    with old authentication code. New code should use fastapi-keycloak's OIDCUser
    and OrganizationContext from app.core.organization instead.
    """

    username: str | None = None
    sub: str | None = None
    roles: list[str] | None = None
    organization_id: str | None = None  # Keycloak organization UUID


class LoginRequest(BaseModel):
    username: str
    password: str


class RefreshTokenRequest(BaseModel):
    refresh_token: str


# Admin user management schemas
class AdminUserResponse(BaseModel):
    """Response schema for admin user list endpoint.

    This schema represents user data returned from the admin users endpoint,
    including organization membership and permissions from Keycloak.
    """

    user_id: str  # Keycloak user UUID
    username: str
    email: str
    organization_id: str | None = None  # Keycloak organization UUID
    organization_name: str | None = None
    status: str  # "active" or "revoked"
    created_at: str  # ISO timestamp
    permissions: list[str]  # Application permission roles from Keycloak


# Global user management response schemas
class UserDataItem(BaseModel):
    """Single user item in admin user list."""

    user_id: str | None = None
    username: str | None = None
    email: str | None = None
    first_name: str | None = None
    last_name: str | None = None
    status: str | None = None
    created_at: str | None = None
    organization_id: str | None = None
    organization_name: str | None = None
    permission_tier: str | None = None


class UserPagination(BaseModel):
    """Pagination metadata for user list."""

    page: int
    limit: int
    total: int
    total_pages: int


class UserListResponse(BaseModel):
    """Paginated response for admin user list."""

    data: list[UserDataItem]
    pagination: UserPagination


class UserPermissionsResponse(BaseModel):
    """Response for user permissions endpoint."""

    user_id: str
    username: str | None = None
    permissions: list[str]


class UserOrganizationResponse(BaseModel):
    """Response for user organization endpoint."""

    user_id: str
    username: str | None = None
    organization: dict | None = None


class AssignOrganizationResponse(BaseModel):
    """Response for assign user to organization endpoint."""

    success: bool
    message: str
    user_id: str
    organization_id: str


class UserDetailResponse(BaseModel):
    """Detailed user response with permissions."""

    user_id: str
    username: str | None = None
    email: str | None = None
    organization_id: str | None = None
    organization_name: str | None = None
    status: str | None = None
    created_at: str | None = None
    permissions: list[str] = []


class PasswordResetResponse(BaseModel):
    """Response for password reset endpoint."""

    success: bool
    method: str
    message: str
