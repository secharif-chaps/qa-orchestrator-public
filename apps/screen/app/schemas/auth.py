"""Pydantic response schemas for authentication endpoints."""

from typing import Any

from pydantic import BaseModel, Field


class LogoutResponse(BaseModel):
    """Response for logout endpoint."""

    message: str = Field(..., description="Logout status message")


class VerifyResponse(BaseModel):
    """Response for token verification endpoint."""

    valid: bool = Field(..., description="Whether the token is valid")
    username: str = Field(..., description="Username from token")
    sub: str = Field(..., description="Subject (user ID) from token")
    roles: list[str] = Field(default_factory=list, description="User roles from token")


class IntrospectResponse(BaseModel):
    """Response for token introspection endpoint (RFC 7662).

    Returns Keycloak token introspection data. Fields vary by token type,
    so we type the common fields and allow extras.
    """

    active: bool = Field(..., description="Whether the token is active")
    sub: str | None = Field(None, description="Subject (user ID)")
    username: str | None = Field(None, description="Username")
    exp: int | None = Field(None, description="Token expiration timestamp")
    iat: int | None = Field(None, description="Token issued-at timestamp")
    scope: str | None = Field(None, description="Token scope")
    client_id: str | None = Field(None, description="Client ID the token was issued to")
    token_type: str | None = Field(None, description="Token type (e.g. Bearer)")
    realm_access: dict[str, Any] | None = Field(None, description="Realm-level access roles")

    model_config = {"extra": "allow"}


class UserInfoResponse(BaseModel):
    """Response for /me endpoint (OIDC UserInfo).

    Returns Keycloak userinfo data. Fields vary by realm configuration,
    so we type the common fields and allow extras.
    """

    sub: str = Field(..., description="Subject (user ID)")
    preferred_username: str | None = Field(None, description="Preferred username")
    email: str | None = Field(None, description="User email")
    email_verified: bool | None = Field(None, description="Whether email is verified")
    name: str | None = Field(None, description="Full name")
    given_name: str | None = Field(None, description="First name")
    family_name: str | None = Field(None, description="Last name")

    model_config = {"extra": "allow"}
