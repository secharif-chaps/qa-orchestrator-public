"""Shared authorization helpers for organization access control.

This module provides reusable authorization functions to verify user access
to organizations. These helpers are used across multiple API endpoints to
maintain consistent authorization logic (DRY principle).
"""

from fastapi import HTTPException, status

from app.core.keycloak import OIDCUser
from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext

logger = get_logger(__name__)


def verify_organization_access(
    org_id: str,
    org_context: OrganizationContext,
    user: OIDCUser,
    resource: str = "resource",
) -> None:
    """Verify user has access to the specified organization.

    Users can access their own organization's resources, or admins with
    admin.organizations role can access any organization.

    This function implements the standard organization access pattern:
    - Organization members can access their own organization
    - Admins with admin.organizations role can access any organization

    Args:
        org_id: Target organization ID to verify access for
        org_context: User's organization context extracted from JWT
        user: Current authenticated user from Keycloak
        resource: Resource name for error messages (e.g., "tokens", "modules")

    Raises:
        HTTPException: 403 Forbidden if user lacks access to the organization

    Examples:
        >>> verify_organization_access(org_id, org_context, user, "tokens")
        >>> verify_organization_access(org_id, org_context, user, "modules")
    """
    # Check if user is a member of the target organization
    is_org_member = org_context.organization_id == org_id

    # Check if user has admin role for cross-organization access
    is_admin = user.roles and "admin.organizations" in user.roles

    # User must be either a member OR an admin
    if not (is_org_member or is_admin):
        logger.warning(
            f"Access denied to organization {resource}",
            extra={
                "user": user.preferred_username,
                "organization_id": org_id,
                "user_org": org_context.organization_id,
                "resource": resource,
            },
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=f"Access denied to this organization's {resource}",
        )


def verify_any_role_access(user: OIDCUser, required_roles: list[str]) -> None:
    """Verify user has at least one of the required roles.

    Raises HTTPException 403 if user lacks all required roles.
    """
    if not user.roles or not any(role in user.roles for role in required_roles):
        logger.warning(
            "Role access denied",
            extra={
                "user": user.preferred_username,
                "required_roles": required_roles,
                "user_roles": user.roles,
            },
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=f"Access denied: requires one of {required_roles}",
        )
