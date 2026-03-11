from datetime import datetime
from typing import Optional
from uuid import UUID

from pydantic import BaseModel, ConfigDict, EmailStr


class UserBase(BaseModel):
    username: str
    email: EmailStr
    first_name: Optional[str] = None
    last_name: Optional[str] = None
    is_active: bool = True


class UserCreate(UserBase):
    keycloak_id: str


class UserUpdate(BaseModel):
    first_name: Optional[str] = None
    last_name: Optional[str] = None
    is_active: Optional[bool] = None


class User(UserBase):
    id: UUID
    keycloak_id: str
    roles: Optional[list[str]] = None
    created_at: datetime
    updated_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)


class UserInDB(User):
    pass


# Auth-related schemas
class Token(BaseModel):
    access_token: str
    refresh_token: Optional[str] = None
    token_type: str = "bearer"
    expires_in: int


class TokenData(BaseModel):
    """Legacy token data schema.

    NOTE: This schema is deprecated and kept only for backward compatibility
    with old authentication code. New code should use fastapi-keycloak's OIDCUser
    and OrganizationContext from app.core.organization instead.
    """
    username: Optional[str] = None
    sub: Optional[str] = None
    roles: Optional[list[str]] = None
    organization_id: Optional[str] = None  # Keycloak organization UUID


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
    organization_id: Optional[str] = None  # Keycloak organization UUID
    organization_name: Optional[str] = None
    status: str  # "active" or "revoked"
    created_at: str  # ISO timestamp
    permissions: list[str]  # Application permission roles from Keycloak