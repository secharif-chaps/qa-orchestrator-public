"""Pydantic schemas for admin user management endpoints."""

import re

from pydantic import BaseModel, ConfigDict, EmailStr, field_validator


class OrganizationInfo(BaseModel):
    """Organization summary returned by Keycloak."""

    id: str
    name: str


class UserOrganizationResponse(BaseModel):
    """Response for user's current organization membership."""

    user_id: str
    username: str | None = None
    organization: OrganizationInfo | None = None

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "user_id": "550e8400-e29b-41d4-a716-446655440000",
                    "username": "jean.dupont",
                    "organization": {
                        "id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
                        "name": "Acme Corp",
                    },
                },
            ],
        },
    )


class UserPermissionsResponse(BaseModel):
    """Response for user's current permissions."""

    user_id: str
    username: str | None = None
    permissions: list[str]

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "user_id": "550e8400-e29b-41d4-a716-446655440000",
                    "username": "jean.dupont",
                    "permissions": [
                        "organization.read",
                        "organization.write",
                        "company.create",
                    ],
                },
            ],
        },
    )


class AssignOrganizationRequest(BaseModel):
    """Request body for assigning user to organization."""

    organization_id: str

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "organization_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
                },
            ],
        },
    )


class UpdatePermissionsRequest(BaseModel):
    """Request body for updating user permissions."""

    permissions: list[str]

    @field_validator("permissions")
    @classmethod
    def validate_permissions(cls, v: list[str]) -> list[str]:
        """Validate that all permissions are valid application permissions."""
        valid_permissions = {
            "company.create",
            "organization.read",
            "organization.write",
            "organization.manage",
            "admin.organizations",
        }

        invalid_perms = [p for p in v if p not in valid_permissions]
        if invalid_perms:
            raise ValueError(f"Invalid permissions: {', '.join(invalid_perms)}")

        return v

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "permissions": [
                        "organization.read",
                        "organization.write",
                        "company.create",
                    ],
                },
            ],
        },
    )


class ResetPasswordRequest(BaseModel):
    """Request body for resetting user password."""

    temporary_password: str | None = None
    send_email: bool = False  # TODO: implement email notification on password reset

    @field_validator("temporary_password")
    @classmethod
    def validate_password(cls, v: str | None) -> str | None:
        """Validate password meets complexity requirements if provided."""
        if v is None:
            return v

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
                    "temporary_password": "TempPass1!",
                    "send_email": False,
                },
            ],
        },
    )


# ── Bulk User Import Schemas ─────────────────────────────────────


class UserImportRow(BaseModel):
    """Single user row from CSV/Excel import.

    All fields except username and email are optional.
    Password is optional - if not provided and generate_passwords is True,
    a random password will be generated.
    """
    username: str
    email: EmailStr
    firstname: str | None = None
    lastname: str | None = None
    password: str | None = None

    @field_validator('username')
    @classmethod
    def validate_username(cls, v: str) -> str:
        """Validate username format."""
        v = v.strip()
        if not v:
            raise ValueError('Username cannot be empty')
        if len(v) < 3:
            raise ValueError('Username must be at least 3 characters')
        if len(v) > 50:
            raise ValueError('Username must be 50 characters or less')
        # Keycloak username pattern: alphanumeric, dots, underscores, hyphens
        if not re.match(r'^[a-zA-Z0-9._-]+$', v):
            raise ValueError('Username can only contain letters, numbers, dots, underscores, and hyphens')
        return v.lower()  # Normalize to lowercase

    @field_validator('firstname', 'lastname', mode='before')
    @classmethod
    def strip_whitespace(cls, v: str | None) -> str | None:
        """Strip whitespace from names."""
        if v is None:
            return None
        v = v.strip()
        return v if v else None

    @field_validator('password')
    @classmethod
    def validate_import_password(cls, v: str | None) -> str | None:
        """Validate password if provided."""
        if v is None or v == '':
            return None

        if len(v) < 8:
            raise ValueError('Password must be at least 8 characters')

        # Check complexity requirements
        has_upper = any(c.isupper() for c in v)
        has_lower = any(c.islower() for c in v)
        has_digit = any(c.isdigit() for c in v)
        has_special = any(c in '!@#$%^&*()_+-=[]{}|;:,.<>?' for c in v)

        if not (has_upper and has_lower and has_digit and has_special):
            raise ValueError(
                'Password must contain uppercase, lowercase, number, and special character'
            )

        return v


class BulkUserImportRequest(BaseModel):
    """Request body for bulk user import.

    Args:
        organization_id: Target Keycloak organization UUID
        users: List of user rows to import (max 100)
        generate_passwords: Whether to generate random passwords for users without passwords
    """
    organization_id: str
    users: list[UserImportRow]
    generate_passwords: bool = True

    @field_validator('organization_id')
    @classmethod
    def validate_organization_id(cls, v: str) -> str:
        """Validate organization_id is not empty."""
        v = v.strip()
        if not v:
            raise ValueError('Organization ID cannot be empty')
        return v

    @field_validator('users')
    @classmethod
    def validate_users_count(cls, v: list[UserImportRow]) -> list[UserImportRow]:
        """Validate user count limits."""
        if len(v) == 0:
            raise ValueError('At least one user is required')
        if len(v) > 100:
            raise ValueError('Maximum 100 users per import')
        return v

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "organization_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
                    "users": [
                        {
                            "username": "marie.martin",
                            "email": "marie.martin@example.com",
                            "firstname": "Marie",
                            "lastname": "Martin",
                            "password": None,
                        },
                        {
                            "username": "pierre.bernard",
                            "email": "pierre.bernard@example.com",
                            "firstname": "Pierre",
                            "lastname": "Bernard",
                            "password": "SecureP@ss1",
                        },
                    ],
                    "generate_passwords": True,
                },
            ],
        },
    )


class UserImportResult(BaseModel):
    """Result for a single user import attempt.

    Contains success status and error message if failed.
    For successful imports with generated passwords, includes the password.
    """
    row_index: int  # 0-based index in the original import array
    username: str
    email: str
    success: bool
    error_message: str | None = None
    user_id: str | None = None  # Keycloak user UUID if created
    generated_password: str | None = None  # Only if password was generated


class BulkUserImportResponse(BaseModel):
    """Response for bulk user import operation.

    Provides summary counts and detailed results per row.
    Supports partial success - some rows may fail while others succeed.
    """
    success_count: int
    error_count: int
    total_count: int
    results: list[UserImportResult]

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "success_count": 2,
                    "error_count": 1,
                    "total_count": 3,
                    "results": [
                        {
                            "row_index": 0,
                            "username": "marie.martin",
                            "email": "marie.martin@example.com",
                            "success": True,
                            "error_message": None,
                            "user_id": "b2c3d4e5-f6a7-8901-bcde-f12345678901",
                            "generated_password": "G3n$ratedPw!",
                        },
                        {
                            "row_index": 1,
                            "username": "pierre.bernard",
                            "email": "pierre.bernard@example.com",
                            "success": True,
                            "error_message": None,
                            "user_id": "c3d4e5f6-a7b8-9012-cdef-123456789012",
                            "generated_password": None,
                        },
                        {
                            "row_index": 2,
                            "username": "duplicate.user",
                            "email": "duplicate@example.com",
                            "success": False,
                            "error_message": "User with this username already exists",
                            "user_id": None,
                            "generated_password": None,
                        },
                    ],
                },
            ],
        },
    )
