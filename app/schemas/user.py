"""Pydantic schemas for admin user management endpoints."""

from pydantic import BaseModel, field_validator


class AssignOrganizationRequest(BaseModel):
    """Request body for assigning user to organization."""

    organization_id: str


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


class ResetPasswordRequest(BaseModel):
    """Request body for resetting user password."""

    temporary_password: str | None = None
    send_email: bool = False

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
