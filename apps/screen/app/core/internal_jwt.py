"""
Internal JWT for service-to-service communication.

This module provides both creation and verification of internal JWTs:
- create_internal_token: Create a token when calling other internal services
- verify_internal_token: Verify a token received from another service

The tokens use a shared secret (INTERNAL_JWT_SECRET) and contain user context.

Security layers:
1. JWT signature verification (always)
2. IP allowlist validation (optional, if INTERNAL_ALLOWED_IPS is set)
"""

import ipaddress
from datetime import datetime, timedelta, timezone
from functools import lru_cache
from typing import Optional

import jwt
from fastapi import Request
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
    email: Optional[str] = None  # User email
    org_id: str  # Organization UUID
    org_name: str  # Organization name
    roles: list[str]  # User roles from realm_access
    iss: str = ISSUER  # Issuer
    iat: int = 0  # Issued at
    exp: int = 0  # Expiration


class InternalJWTError(Exception):
    """Base exception for internal JWT errors."""

    pass


class TokenExpiredError(InternalJWTError):
    """Token has expired."""

    pass


class TokenInvalidError(InternalJWTError):
    """Token is invalid (bad signature, malformed, etc.)."""

    pass


class IPNotAllowedError(InternalJWTError):
    """Source IP is not in the allowlist."""

    pass


def create_internal_token(
    user_id: str,
    username: str,
    org_id: str,
    org_name: str = "Unknown",
    roles: list[str] | None = None,
    email: str | None = None,
) -> str:
    """
    Create an internal JWT for service-to-service communication.

    Called by the monolith when making requests to global-service or other
    internal services. The internal token contains necessary user context.

    Args:
        user_id: Keycloak user UUID (sub claim)
        username: User's preferred username
        org_id: Organization UUID from Keycloak
        org_name: Organization name (optional, defaults to "Unknown")
        roles: List of roles (optional, defaults to empty list)
        email: Optional user email

    Returns:
        Signed JWT string

    Raises:
        InternalJWTError: If INTERNAL_JWT_SECRET is not configured
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
        "roles": roles or [],
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


@lru_cache(maxsize=1)
def _get_allowed_networks() -> tuple[ipaddress.IPv4Network | ipaddress.IPv6Network, ...]:
    """
    Parse and cache the allowed IP networks from config.

    Returns empty tuple if INTERNAL_ALLOWED_IPS is not set (IP validation disabled).
    Uses @lru_cache for thread-safe caching without global mutable state.
    """
    if not settings.INTERNAL_ALLOWED_IPS:
        logger.info(
            "INTERNAL_ALLOWED_IPS not set - IP validation disabled for internal auth"
        )
        return ()

    networks = []
    for cidr in settings.INTERNAL_ALLOWED_IPS.split(","):
        cidr = cidr.strip()
        if not cidr:
            continue
        try:
            network = ipaddress.ip_network(cidr, strict=False)
            networks.append(network)
            logger.debug(f"Added allowed network for internal auth: {cidr}")
        except ValueError as e:
            logger.error(f"Invalid CIDR in INTERNAL_ALLOWED_IPS: {cidr} - {e}")

    logger.info(
        f"Internal auth IP allowlist configured with {len(networks)} network(s)"
    )
    return tuple(networks)


def _get_client_ip(request: Request) -> str:
    """
    Extract the client IP from the request.

    Checks X-Forwarded-For header first (for proxied requests),
    then falls back to the direct client IP.
    """
    # Check X-Forwarded-For header (set by nginx/load balancer)
    forwarded_for = request.headers.get("X-Forwarded-For")
    if forwarded_for:
        # Take the first IP (original client)
        # Format: "client, proxy1, proxy2"
        client_ip = forwarded_for.split(",")[0].strip()
        return client_ip

    # Check X-Real-IP header (alternative)
    real_ip = request.headers.get("X-Real-IP")
    if real_ip:
        return real_ip.strip()

    # Fall back to direct connection IP
    if request.client:
        return request.client.host

    return ""


def validate_source_ip(request: Request) -> None:
    """
    Validate that the request comes from an allowed IP range.

    Only performs validation if INTERNAL_ALLOWED_IPS is configured.
    If not configured, this function does nothing (validation disabled).

    Args:
        request: FastAPI request object

    Raises:
        IPNotAllowedError: If IP validation is enabled and IP is not allowed
    """
    allowed_networks = _get_allowed_networks()

    # If no networks configured, IP validation is disabled
    if not allowed_networks:
        return

    client_ip_str = _get_client_ip(request)

    if not client_ip_str:
        logger.warning("Could not determine client IP for internal auth validation")
        raise IPNotAllowedError("Could not determine source IP")

    try:
        client_ip = ipaddress.ip_address(client_ip_str)
    except ValueError:
        logger.warning(f"Invalid client IP format: {client_ip_str}")
        raise IPNotAllowedError(f"Invalid IP format: {client_ip_str}")

    # Check if IP is in any allowed network
    for network in allowed_networks:
        if client_ip in network:
            logger.debug(
                f"Internal auth IP {client_ip_str} allowed (matches {network})"
            )
            return

    logger.warning(
        f"Internal auth rejected: IP {client_ip_str} not in allowlist",
        extra={
            "client_ip": client_ip_str,
            "allowed_networks": [str(n) for n in allowed_networks],
        },
    )
    raise IPNotAllowedError(f"IP {client_ip_str} not in allowed ranges")


def is_internal_request(request: Request) -> bool:
    """Check if request has internal JWT authorization header."""
    auth = request.headers.get("Authorization", "")
    return auth.startswith(INTERNAL_AUTH_PREFIX)


def verify_internal_token(token: str) -> InternalTokenPayload:
    """
    Verify an internal JWT and extract the payload.

    Called by backend services to validate requests from the gateway.
    Note: Call validate_source_ip() separately before this for IP validation.

    Args:
        token: JWT string (without "Internal " prefix)

    Returns:
        InternalTokenPayload with user context

    Raises:
        TokenExpiredError: If token has expired
        TokenInvalidError: If token is invalid
    """
    if not settings.INTERNAL_JWT_SECRET:
        raise InternalJWTError("INTERNAL_JWT_SECRET not configured")

    try:
        payload = jwt.decode(
            token,
            settings.INTERNAL_JWT_SECRET,
            algorithms=[ALGORITHM],
            issuer=ISSUER,
            options={
                "require": ["sub", "org_id", "roles", "exp", "iat", "iss"],
            },
        )

        logger.debug(
            "Verified internal token",
            extra={"user_id": payload.get("sub"), "org_id": payload.get("org_id")},
        )

        return InternalTokenPayload(**payload)

    except jwt.ExpiredSignatureError:
        logger.warning("Internal token expired")
        raise TokenExpiredError("Internal token has expired")

    except jwt.InvalidIssuerError:
        logger.warning("Internal token has invalid issuer")
        raise TokenInvalidError("Invalid token issuer")

    except jwt.InvalidTokenError as e:
        logger.warning(f"Internal token invalid: {e}")
        raise TokenInvalidError(f"Invalid internal token: {e}")


def verify_internal_request(request: Request) -> InternalTokenPayload:
    """
    Full verification of an internal request: IP check + JWT validation.

    Convenience function that performs both security checks.

    Args:
        request: FastAPI request object

    Returns:
        InternalTokenPayload with user context

    Raises:
        IPNotAllowedError: If IP validation fails
        TokenExpiredError: If token has expired
        TokenInvalidError: If token is invalid
    """
    # Layer 1: IP validation (if enabled)
    validate_source_ip(request)

    # Layer 2: JWT validation
    auth = request.headers.get("Authorization", "")
    if not auth.startswith(INTERNAL_AUTH_PREFIX):
        raise TokenInvalidError("Missing Internal authorization header")

    token = auth[len(INTERNAL_AUTH_PREFIX) :]
    return verify_internal_token(token)
