"""Generic role verification helper functions.

This module provides reusable helper functions for verifying user roles
in endpoint bodies when dependency injection alone is not sufficient.

Note: For most use cases, prefer using dependency injection directly:
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin"]))

Use these helpers only when you need conditional role checks or dynamic
role verification within endpoint logic.
"""

from fastapi_keycloak import OIDCUser
from app.core.exceptions import AuthorizationError
from app.core.logging_config import get_logger

logger = get_logger(__name__)


def verify_role_access(user: OIDCUser, required_role: str) -> OIDCUser:
    """Verify user has required role.

    Args:
        user: OIDC user from JWT token
        required_role: Role string (e.g., "admin", "workspace_admin")

    Returns:
        OIDCUser if authorized

    Raises:
        AuthorizationError: If user lacks role

    Example:
        user = Depends(idp.get_current_user())
        verify_role_access(user, "admin")  # Raises if not admin
    """
    if not user.roles or required_role not in user.roles:
        logger.warning(
            "Role access denied",
            extra={
                "user": user.preferred_username,
                "required_role": required_role,
                "user_roles": user.roles,
            },
        )
        raise AuthorizationError(
            f"Access denied: requires {required_role} role",
            details={"required_role": required_role, "user_roles": user.roles},
        )

    logger.debug(
        "Role access granted",
        extra={
            "user": user.preferred_username,
            "required_role": required_role,
        },
    )
    return user


def verify_any_role_access(user: OIDCUser, required_roles: list[str]) -> OIDCUser:
    """Verify user has at least one of the required roles.

    Args:
        user: OIDC user from JWT token
        required_roles: List of role strings (user needs ANY one)

    Returns:
        OIDCUser if authorized

    Raises:
        AuthorizationError: If user has none of the required roles

    Example:
        user = Depends(idp.get_current_user())
        verify_any_role_access(user, ["admin", "workspace_admin"])
    """
    if not user.roles or not any(role in user.roles for role in required_roles):
        logger.warning(
            "Role access denied (any)",
            extra={
                "user": user.preferred_username,
                "required_roles": required_roles,
                "user_roles": user.roles,
            },
        )
        raise AuthorizationError(
            f"Access denied: requires one of {required_roles}",
            details={"required_roles": required_roles, "user_roles": user.roles},
        )

    # Find which role(s) the user has
    matched_roles = [role for role in required_roles if role in user.roles]
    logger.debug(
        "Role access granted (any)",
        extra={
            "user": user.preferred_username,
            "matched_roles": matched_roles,
        },
    )
    return user
