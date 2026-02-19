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
from fastapi import Header, HTTPException, status

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


def verify_internal_token(token: str) -> InternalTokenPayload:
    """
    Verify and decode an internal JWT token.

    Called by backend services to validate tokens from the gateway.
    Checks signature, expiration, and issuer.

    Args:
        token: JWT string to verify

    Returns:
        Decoded token payload with user context

    Raises:
        TokenExpiredError: If token has expired
        TokenInvalidError: If token is invalid or signature doesn't match
        InternalJWTError: If secret is not configured
    """
    if not settings.INTERNAL_JWT_SECRET:
        raise InternalJWTError("INTERNAL_JWT_SECRET not configured")

    try:
        # Decode and verify token
        payload = jwt.decode(
            token,
            settings.INTERNAL_JWT_SECRET,
            algorithms=[ALGORITHM],
            options={
                "verify_signature": True,
                "verify_exp": True,
                "verify_iss": True,
                "require": ["sub", "username", "org_id", "iss", "iat", "exp"],
            },
            issuer=ISSUER,
        )

        # Convert to validated model
        return InternalTokenPayload(**payload)

    except jwt.ExpiredSignatureError as e:
        logger.warning("Internal token expired", extra={"error": str(e)})
        raise TokenExpiredError("Internal token has expired") from e

    except jwt.InvalidTokenError as e:
        logger.warning(
            "Invalid internal token", extra={"error": str(e), "error_type": type(e).__name__}
        )
        raise TokenInvalidError(f"Invalid internal token: {str(e)}") from e


async def get_internal_token(authorization: str = Header(...)) -> InternalTokenPayload:
    """
    FastAPI dependency to extract and verify internal JWT from Authorization header.

    Validates the token format, extracts the bearer token, and verifies its signature.
    Use as a dependency in internal API endpoints that require service-to-service auth.

    Args:
        authorization: Authorization header value (format: "Bearer <token>")

    Returns:
        Validated token payload with user context

    Raises:
        HTTPException 401: If token is missing, invalid, or expired
    """
    if not authorization:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing authorization header",
        )

    # Extract bearer token
    parts = authorization.split()
    if len(parts) != 2 or parts[0].lower() != "bearer":
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid authorization header format. Expected: Bearer <token>",
        )

    token = parts[1]

    try:
        payload = verify_internal_token(token)
        logger.debug(
            "Internal token verified",
            extra={
                "user_id": payload.sub,
                "org_id": payload.org_id,
            },
        )
        return payload

    except TokenExpiredError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Internal token has expired",
        )

    except (TokenInvalidError, InternalJWTError) as e:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=f"Invalid internal token: {str(e)}",
        )
