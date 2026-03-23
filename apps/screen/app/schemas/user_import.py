"""Schemas for bulk user import functionality."""

import re

from pydantic import BaseModel, EmailStr, field_validator


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

    @field_validator("username")
    @classmethod
    def validate_username(cls, v: str) -> str:
        """Validate username format."""
        v = v.strip()
        if not v:
            raise ValueError("Username cannot be empty")
        if len(v) < 3:
            raise ValueError("Username must be at least 3 characters")
        if len(v) > 50:
            raise ValueError("Username must be 50 characters or less")
        # Keycloak username pattern: alphanumeric, dots, underscores, hyphens
        if not re.match(r"^[a-zA-Z0-9._-]+$", v):
            raise ValueError("Username can only contain letters, numbers, dots, underscores, and hyphens")
        return v.lower()  # Normalize to lowercase

    @field_validator("firstname", "lastname", mode="before")
    @classmethod
    def strip_whitespace(cls, v: str | None) -> str | None:
        """Strip whitespace from names."""
        if v is None:
            return None
        v = v.strip()
        return v if v else None

    @field_validator("password")
    @classmethod
    def validate_password(cls, v: str | None) -> str | None:
        """Validate password if provided."""
        if v is None or v == "":
            return None

        if len(v) < 8:
            raise ValueError("Password must be at least 8 characters")

        # Check complexity requirements
        has_upper = any(c.isupper() for c in v)
        has_lower = any(c.islower() for c in v)
        has_digit = any(c.isdigit() for c in v)
        has_special = any(c in "!@#$%^&*()_+-=[]{}|;:,.<>?" for c in v)

        if not (has_upper and has_lower and has_digit and has_special):
            raise ValueError("Password must contain uppercase, lowercase, number, and special character")

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

    @field_validator("organization_id")
    @classmethod
    def validate_organization_id(cls, v: str) -> str:
        """Validate organization_id is not empty."""
        v = v.strip()
        if not v:
            raise ValueError("Organization ID cannot be empty")
        return v

    @field_validator("users")
    @classmethod
    def validate_users_count(cls, v: list[UserImportRow]) -> list[UserImportRow]:
        """Validate user count limits."""
        if len(v) == 0:
            raise ValueError("At least one user is required")
        if len(v) > 100:
            raise ValueError("Maximum 100 users per import")
        return v


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


class DuplicateCheckResult(BaseModel):
    """Result of duplicate email/username check.

    Used internally before import to identify duplicates.
    """

    username: str
    email: str
    row_index: int
    is_duplicate_email: bool = False
    is_duplicate_username: bool = False
    existing_user_id: str | None = None
