"""Authentication and authorization for Stream backend.

All requests come through the global-service gateway which:
1. Validates the Keycloak JWT
2. Creates an Internal JWT (HS256, 60s TTL)
3. Forwards `Authorization: Internal {token}` to Stream

This module verifies those Internal JWTs and extracts user context.
No Keycloak SDK needed — only shared-secret JWT verification.
"""

from collections.abc import Awaitable, Callable
from typing import Any

from fastapi import Depends, HTTPException, Request, status
from pydantic import BaseModel

from app.core.internal_jwt import (
    InternalJWTError,
    IPNotAllowedError,
    TokenExpiredError,
    TokenInvalidError,
    is_internal_request,
    verify_internal_request,
)
from app.core.logging_config import get_logger

logger = get_logger(__name__)


class AuthorizationError(Exception):
    """Raised when an authenticated user lacks the required role."""

    def __init__(self, message: str, details: dict[str, Any] | None = None) -> None:
        super().__init__(message)
        self.details = details or {}


class AuthenticatedUser(BaseModel):
    """Authenticated user extracted from Internal JWT.

    Fields are flat (mirroring the gateway's InternalTokenPayload):
    no Keycloak-shaped ``organization`` claim reconstruction.
    """

    sub: str
    preferred_username: str
    email: str | None = None
    given_name: str | None = None
    roles: list[str] = []
    org_id: str | None = None
    org_name: str | None = None
    iat: int = 0
    exp: int = 0
    iss: str = ""


def verify_internal_jwt(request: Request) -> AuthenticatedUser:
    """Verify the Internal JWT from the gateway and build an AuthenticatedUser.

    Designed to be wired in as a FastAPI dependency:
    ``user: AuthenticatedUser = Depends(verify_internal_jwt)``. FastAPI caches
    dependency results by callable identity within a single request, so any
    number of dependents (``get_current_user`` factory output,
    ``get_user_organization``, …) trigger a single verification per request.

    Args:
        request: FastAPI request object

    Returns:
        AuthenticatedUser with user context

    Raises:
        HTTPException 401: If token is missing, expired, or invalid
        HTTPException 403: If source IP is not allowed
        HTTPException 500: If internal auth is misconfigured
    """
    if not is_internal_request(request):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing Internal authorization header",
        )

    try:
        payload = verify_internal_request(request)
    except IPNotAllowedError as e:
        logger.warning(f"Internal request rejected: IP not allowed - {e}")
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied: IP not in allowed range",
        )
    except TokenExpiredError as e:
        logger.warning(f"Internal request rejected: token expired - {e}")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Internal token has expired",
        )
    except TokenInvalidError as e:
        logger.warning(f"Internal request rejected: invalid token - {e}")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid internal token",
        )
    except InternalJWTError as e:
        logger.error(f"Internal JWT error: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal authentication error",
        )

    return AuthenticatedUser(
        sub=payload.sub,
        preferred_username=payload.username,
        email=payload.email,
        roles=payload.roles,
        org_id=payload.org_id,
        org_name=payload.org_name,
        iat=payload.iat,
        exp=payload.exp,
        iss=payload.iss,
    )


def get_current_user(
    required_roles: list[str] | None = None,
) -> Callable[[AuthenticatedUser], Awaitable[AuthenticatedUser]]:
    """FastAPI dependency factory that verifies Internal JWT and optionally checks roles.

    Args:
        required_roles: If provided, user must have at least one of these roles (OR logic).
            Pass ``None`` to skip role checking. An empty list is rejected to
            prevent accidentally permissive routes when the list is computed
            dynamically and happens to be empty.

    Returns:
        FastAPI dependency function returning AuthenticatedUser

    Raises:
        ValueError: If ``required_roles`` is an empty list (use ``None`` instead).

    Example:
        @router.get("/admin/users")
        def list_users(
            user: AuthenticatedUser = Depends(get_current_user(required_roles=["admin"]))
        ):
            return {"users": [...]}
    """
    if required_roles is not None and not required_roles:
        raise ValueError(
            "required_roles must be None (skip role check) or a non-empty list. "
            "An empty list would silently allow any authenticated user."
        )

    async def _dependency(
        user: AuthenticatedUser = Depends(verify_internal_jwt),
    ) -> AuthenticatedUser:
        if required_roles:
            user_roles = set(user.roles)
            if not user_roles.intersection(required_roles):
                logger.warning(
                    "Insufficient permissions",
                    extra={
                        "user_id": user.sub,
                        "username": user.preferred_username,
                        "required": required_roles,
                        "actual": user.roles,
                    },
                )
                raise HTTPException(
                    status_code=status.HTTP_403_FORBIDDEN,
                    detail="Insufficient permissions",
                )

        logger.debug(f"Internal JWT auth OK: user={user.preferred_username}")
        return user

    return _dependency


# --- Role verification helpers ---
# For conditional role checks within endpoint logic when dependency injection
# alone is not sufficient. For most use cases, prefer:
#     user: AuthenticatedUser = Depends(get_current_user(required_roles=["admin"]))


def verify_role_access(user: AuthenticatedUser, required_role: str) -> AuthenticatedUser:
    """Verify user has required role.

    Args:
        user: Authenticated user from Internal JWT
        required_role: Role string (e.g., "admin", "admin.organizations")

    Returns:
        AuthenticatedUser if authorized

    Raises:
        AuthorizationError: If user lacks role

    Example:
        user = Depends(get_current_user())
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


def verify_any_role_access(user: AuthenticatedUser, required_roles: list[str]) -> AuthenticatedUser:
    """Verify user has at least one of the required roles.

    Args:
        user: Authenticated user from Internal JWT
        required_roles: List of role strings (user needs ANY one)

    Returns:
        AuthenticatedUser if authorized

    Raises:
        AuthorizationError: If user has none of the required roles

    Example:
        user = Depends(get_current_user())
        verify_any_role_access(user, ["admin", "admin.organizations"])
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

    matched_roles = [role for role in required_roles if role in user.roles]
    logger.debug(
        "Role access granted (any)",
        extra={
            "user": user.preferred_username,
            "matched_roles": matched_roles,
        },
    )
    return user
