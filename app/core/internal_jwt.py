"""
Internal JWT for secure service-to-service communication.

The gateway creates short-lived JWTs containing user context extracted from
the original Keycloak JWT. Backend services verify these tokens using a
shared secret, avoiding the need to re-validate with Keycloak.

Security properties:
- Tokens expire in 60 seconds (configurable)
- HMAC-SHA256 signature prevents tampering
- Contains full user context (no separate headers needed)
- Issuer claim identifies the gateway
"""

import jwt
from datetime import datetime, timedelta, timezone
from typing import Optional
from pydantic import BaseModel

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

ALGORITHM = "HS256"
ISSUER = "global-gateway"


class InternalTokenPayload(BaseModel):
    """Payload structure for internal JWT tokens."""

    sub: str  # User ID (Keycloak sub)
    username: str  # Preferred username
    email: Optional[str] = None  # User email
    org_id: str  # Organization UUID
    org_name: str  # Organization name
    roles: list[str]  # User roles from realm_access
    iss: str = ISSUER  # Issuer
    iat: int = 0  # Issued at (set automatically)
    exp: int = 0  # Expiration (set automatically)


class InternalJWTError(Exception):
    """Base exception for internal JWT errors."""

    pass


class TokenExpiredError(InternalJWTError):
    """Token has expired."""

    pass


class TokenInvalidError(InternalJWTError):
    """Token is invalid (bad signature, malformed, etc.)."""

    pass


def create_internal_token(
    user_id: str,
    username: str,
    org_id: str,
    org_name: str,
    roles: list[str],
    email: Optional[str] = None,
) -> str:
    """
    Create an internal JWT for service-to-service communication.

    Called by the gateway after validating the user's Keycloak JWT.
    The internal token contains all necessary user context for the backend.

    Args:
        user_id: Keycloak user UUID (sub claim)
        username: User's preferred username
        org_id: Organization UUID from Keycloak
        org_name: Organization name
        roles: List of roles from realm_access
        email: Optional user email

    Returns:
        Signed JWT string

    Raises:
        InternalJWTError: If token creation fails
    """
    if not settings.INTERNAL_JWT_SECRET:
        raise InternalJWTError("INTERNAL_JWT_SECRET not configured")

    now = datetime.now(timezone.utc)
    expiry = now + timedelta(seconds=settings.INTERNAL_JWT_EXPIRY_SECONDS)

    payload = {
        "sub": user_id,
        "username": username,
        "email": email,
        "org_id": org_id,
        "org_name": org_name,
        "roles": roles,
        "iss": ISSUER,
        "iat": int(now.timestamp()),
        "exp": int(expiry.timestamp()),
    }

    token = jwt.encode(payload, settings.INTERNAL_JWT_SECRET, algorithm=ALGORITHM)

    logger.debug(
        "Created internal token",
        extra={
            "user_id": user_id,
            "org_id": org_id,
            "expiry_seconds": settings.INTERNAL_JWT_EXPIRY_SECONDS,
        },
    )

    return token
