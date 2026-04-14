"""Internal JWT creation for outbound service-to-service calls.

Used by Stream service when calling global-service (e.g., token consumption).
Mirrors the pattern from apps/screen/app/core/internal_jwt.py.
"""

from datetime import UTC, datetime, timedelta

import jwt

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

ALGORITHM = "HS256"
ISSUER = "global-gateway"


class InternalJWTError(Exception):
    """Raised when internal JWT creation fails."""

    pass


def create_internal_token(
    user_id: str,
    username: str,
    org_id: str,
    org_name: str = "Unknown",
    roles: list[str] | None = None,
    email: str | None = None,
) -> str:
    """Create an internal JWT for service-to-service communication.

    Args:
        user_id: Keycloak user UUID (sub claim)
        username: User's preferred username
        org_id: Organization UUID from Keycloak
        org_name: Organization name (optional)
        roles: List of roles (optional, defaults to empty list)
        email: Optional user email

    Returns:
        Signed JWT string

    Raises:
        InternalJWTError: If INTERNAL_JWT_SECRET is not configured
    """
    if not settings.INTERNAL_JWT_SECRET:
        raise InternalJWTError("INTERNAL_JWT_SECRET not configured")

    now = datetime.now(UTC)
    expiry = now + timedelta(seconds=settings.INTERNAL_JWT_EXPIRY_SECONDS)

    payload = {
        "sub": user_id,
        "username": username,
        "email": email,
        "org_id": org_id,
        "org_name": org_name,
        "roles": roles or [],
        "iss": ISSUER,
        "iat": int(now.timestamp()),
        "exp": int(expiry.timestamp()),
    }

    token = jwt.encode(payload, settings.INTERNAL_JWT_SECRET, algorithm=ALGORITHM)

    logger.debug(
        "Created internal token for outbound call",
        extra={
            "user_id": user_id,
            "org_id": org_id,
            "expiry_seconds": settings.INTERNAL_JWT_EXPIRY_SECONDS,
        },
    )

    return token
