"""Internal JWT verification for Stream service.

All requests come through the global-service gateway which:
1. Validates the Keycloak JWT
2. Creates an Internal JWT (HS256, 60s TTL)
3. Forwards `Authorization: Internal {token}` to Stream

This module verifies those Internal JWTs and extracts user context.
No Keycloak SDK needed — only shared-secret JWT verification.
"""

import jwt
from fastapi import Depends, HTTPException, Request, status
from pydantic import BaseModel

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

ALGORITHM = "HS256"
ISSUER = "global-gateway"
INTERNAL_AUTH_PREFIX = "Internal "


class InternalTokenPayload(BaseModel):
    """Payload structure for internal JWT tokens."""

    sub: str  # User ID (Keycloak sub)
    username: str  # Preferred username
    email: str | None = None
    org_id: str  # Organization UUID
    org_name: str  # Organization name
    roles: list[str]  # User roles from realm_access
    iss: str = ISSUER
    iat: int = 0
    exp: int = 0


class OrganizationContext(BaseModel):
    """Organization context extracted from Internal JWT."""

    org_id: str
    org_name: str
    user_id: str
    username: str


def verify_internal_jwt(request: Request) -> InternalTokenPayload:
    """Verify the Internal JWT from the gateway and extract payload.

    Args:
        request: FastAPI request object

    Returns:
        InternalTokenPayload with user context

    Raises:
        HTTPException 401: If token is missing, expired, or invalid
        HTTPException 500: If INTERNAL_JWT_SECRET is not configured
    """
    if not settings.INTERNAL_JWT_SECRET:
        logger.error("INTERNAL_JWT_SECRET not configured")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal auth not configured",
        )

    auth = request.headers.get("Authorization", "")
    if not auth.startswith(INTERNAL_AUTH_PREFIX):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing Internal authorization header",
        )

    token = auth[len(INTERNAL_AUTH_PREFIX) :]

    try:
        payload = jwt.decode(
            token,
            settings.INTERNAL_JWT_SECRET,
            algorithms=[ALGORITHM],
            issuer=ISSUER,
            options={
                "require": ["sub", "username", "org_id", "org_name", "roles", "exp", "iat", "iss"],
            },
        )
        logger.debug(
            "Verified internal token",
            extra={"user_id": payload.get("sub"), "org_id": payload.get("org_id")},
        )
        return InternalTokenPayload(**payload)

    except jwt.ExpiredSignatureError:
        logger.warning("Internal token expired")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Internal token has expired",
        )
    except jwt.InvalidIssuerError:
        logger.warning("Internal token has invalid issuer")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid token issuer",
        )
    except jwt.InvalidTokenError as e:
        logger.warning(f"Internal token invalid: {e}")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid internal token",
        )
    except Exception as e:
        logger.warning(f"Internal token payload malformed: {e}")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid internal token",
        )


def get_current_user(
    required_roles: list[str] | None = None,
):
    """FastAPI dependency factory that verifies Internal JWT and optionally checks roles.

    Args:
        required_roles: If provided, user must have at least one of these roles (OR logic)

    Returns:
        FastAPI dependency function returning InternalTokenPayload
    """

    def _dependency(
        payload: InternalTokenPayload = Depends(verify_internal_jwt),
    ) -> InternalTokenPayload:
        if required_roles:
            user_roles = set(payload.roles)
            if not user_roles.intersection(required_roles):
                logger.warning(
                    "Insufficient permissions",
                    extra={
                        "user_id": payload.sub,
                        "required": required_roles,
                        "actual": payload.roles,
                    },
                )
                raise HTTPException(
                    status_code=status.HTTP_403_FORBIDDEN,
                    detail="Insufficient permissions",
                )
        return payload

    return _dependency


def get_user_organization(
    payload: InternalTokenPayload = Depends(verify_internal_jwt),
) -> OrganizationContext:
    """FastAPI dependency that extracts organization context from Internal JWT.

    Returns:
        OrganizationContext with org_id, org_name, user_id, username
    """
    return OrganizationContext(
        org_id=payload.org_id,
        org_name=payload.org_name,
        user_id=payload.sub,
        username=payload.username,
    )
