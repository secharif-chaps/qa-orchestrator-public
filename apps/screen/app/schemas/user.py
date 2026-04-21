"""Auth-related Pydantic schemas.

Contains schemas for authentication endpoints (login, token refresh, etc.)
and the legacy TokenData schema for backward compatibility.
"""

from pydantic import BaseModel


class Token(BaseModel):
    access_token: str
    refresh_token: str | None = None
    token_type: str = "bearer"
    expires_in: int


class TokenData(BaseModel):
    """Legacy token data schema.

    NOTE: This schema is deprecated and kept only for backward compatibility
    with old authentication code. New code should use AuthenticatedUser from
    app.core.auth and OrganizationContext from app.core.organization_context.
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
