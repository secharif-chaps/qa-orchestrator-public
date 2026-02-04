"""
Authentication middleware for the gateway.

This middleware validates JWT tokens at the gateway level and creates
internal JWTs for secure service-to-service communication with backends.

Security:
- Validates external JWT tokens using Keycloak's public key
- Creates short-lived internal JWTs (60s) for backend communication
- Internal JWTs contain user context (no separate headers needed)
- HMAC-SHA256 signature prevents tampering
"""

from typing import Optional, Set, Any
import httpx
from jose import jwt, JWTError
from cachetools import TTLCache
from fastapi import Request
from pydantic import BaseModel
from app.core.config import settings
from app.core.logging_config import get_logger
from app.core.internal_jwt import create_internal_token, InternalJWTError

logger = get_logger(__name__)

# Cache for Keycloak public key (TTL: 1 hour)
_public_key_cache: TTLCache = TTLCache(maxsize=1, ttl=3600)


class GatewayUser(BaseModel):
    """User information extracted from JWT token."""
    sub: str
    preferred_username: Optional[str] = None
    email: Optional[str] = None
    realm_access: Optional[dict[str, Any]] = None
    organization: Optional[Any] = None


# Public routes that don't require authentication
# These paths are relative to /api/
PUBLIC_ROUTES: Set[str] = {
    "health",
    "health/live",
    "health/ready",
}

# Routes that use different auth (e.g., API keys, webhooks)
WEBHOOK_PREFIXES = ("webhooks/",)


def is_public_route(path: str) -> bool:
    """Check if the path is a public route that doesn't require JWT auth."""
    # Remove leading slash if present
    clean_path = path.lstrip("/")

    # Check exact matches
    if clean_path in PUBLIC_ROUTES:
        return True

    # Check webhook prefixes (use different auth mechanism)
    if clean_path.startswith(WEBHOOK_PREFIXES):
        return True

    return False


async def get_keycloak_public_key() -> str:
    """
    Fetch Keycloak's public key for JWT verification.

    The key is cached for 1 hour to avoid repeated requests.
    """
    cache_key = "keycloak_public_key"

    if cache_key in _public_key_cache:
        return _public_key_cache[cache_key]

    try:
        # Fetch the realm's public key from Keycloak
        url = f"{settings.KEYCLOAK_SERVER_URL}/realms/{settings.KEYCLOAK_REALM}"

        async with httpx.AsyncClient(timeout=10.0) as client:
            response = await client.get(url)
            response.raise_for_status()
            realm_info = response.json()

        public_key = realm_info.get("public_key")
        if not public_key:
            raise ValueError("No public key found in realm info")

        # Format as PEM
        pem_key = f"-----BEGIN PUBLIC KEY-----\n{public_key}\n-----END PUBLIC KEY-----"

        # Cache it
        _public_key_cache[cache_key] = pem_key
        logger.info("Keycloak public key fetched and cached")

        return pem_key

    except Exception as e:
        logger.error(f"Failed to fetch Keycloak public key: {e}")
        raise


async def extract_token(request: Request) -> Optional[str]:
    """Extract JWT token from Authorization header."""
    auth_header = request.headers.get("Authorization", "")
    if auth_header.startswith("Bearer "):
        return auth_header[7:]
    return None


async def validate_jwt_token(token: str) -> Optional[GatewayUser]:
    """
    Validate JWT token against Keycloak's public key.

    Returns GatewayUser if valid, None otherwise.
    """
    try:
        # Get Keycloak's public key
        public_key = await get_keycloak_public_key()

        # Decode and verify the token
        payload = jwt.decode(
            token,
            public_key,
            algorithms=["RS256"],
            options={
                "verify_signature": True,
                "verify_exp": True,
                "verify_aud": False,  # Keycloak tokens may not have audience
            }
        )

        # Extract user info from payload
        user = GatewayUser(
            sub=payload.get("sub", ""),
            preferred_username=payload.get("preferred_username"),
            email=payload.get("email"),
            realm_access=payload.get("realm_access"),
            organization=payload.get("organization"),
        )

        logger.debug(f"JWT validated successfully for user: {user.preferred_username}")
        return user

    except jwt.ExpiredSignatureError:
        logger.warning("JWT token has expired")
        return None
    except JWTError as e:
        logger.warning(f"JWT validation failed: {e}")
        return None
    except Exception as e:
        logger.error(f"Unexpected error validating JWT: {e}")
        return None


def extract_organization_info(user: GatewayUser) -> tuple[str, str]:
    """
    Extract organization ID and name from user's organization claim.

    Args:
        user: GatewayUser with organization claim

    Returns:
        Tuple of (org_id, org_name), empty strings if not found
    """
    if not user.organization:
        return "", ""

    org_info = user.organization

    # Organization claim format: ["OrgName", {"OrgName": {"id": "uuid"}}]
    if isinstance(org_info, list) and len(org_info) >= 2:
        org_name = org_info[0] if isinstance(org_info[0], str) else ""
        org_dict = org_info[1] if len(org_info) > 1 else {}
        if isinstance(org_dict, dict) and org_name in org_dict:
            org_id = org_dict[org_name].get("id", "")
            return org_id, org_name
        return "", org_name
    elif isinstance(org_info, str):
        return "", org_info

    return "", ""


def build_internal_headers(user: Optional[GatewayUser]) -> dict[str, str]:
    """
    Build internal request headers with signed JWT.

    The internal JWT contains all user context, replacing the previous
    approach of multiple X-User-* headers. This provides:
    - Cryptographic signature (tamper-proof)
    - Short expiration (60 seconds)
    - Single header instead of multiple

    Args:
        user: GatewayUser if authenticated, None for public routes

    Returns:
        Dict with Authorization header containing internal JWT
    """
    if not user:
        return {}

    # Check if internal JWT is configured
    if not settings.INTERNAL_JWT_SECRET:
        logger.warning("INTERNAL_JWT_SECRET not configured - skipping internal JWT")
        return {}

    # Extract roles from realm_access
    roles: list[str] = []
    if user.realm_access:
        roles = user.realm_access.get("roles", [])

    # Extract organization info
    org_id, org_name = extract_organization_info(user)

    try:
        # Create signed internal token
        internal_token = create_internal_token(
            user_id=user.sub,
            username=user.preferred_username or "",
            org_id=org_id,
            org_name=org_name,
            roles=roles,
            email=user.email,
        )

        return {
            "Authorization": f"Internal {internal_token}",
        }

    except InternalJWTError as e:
        logger.error(f"Failed to create internal JWT: {e}")
        return {}


class GatewayAuthMiddleware:
    """
    Middleware for JWT validation at the gateway.

    This is used by the proxy routes to validate tokens before forwarding.
    """

    async def validate_request(
        self, request: Request, path: str
    ) -> tuple[bool, Optional[GatewayUser], dict]:
        """
        Validate the request and return auth status.

        Args:
            request: FastAPI request object
            path: The API path being accessed (without /api/ prefix)

        Returns:
            Tuple of (is_valid, user, internal_headers)
            - is_valid: True if request should proceed
            - user: GatewayUser if authenticated, None otherwise
            - internal_headers: Headers to add to proxied request
        """
        # Check if this is a public route
        if is_public_route(path):
            logger.debug(f"Public route accessed: {path}")
            return True, None, build_internal_headers(None)

        # Extract token
        token = await extract_token(request)

        if not token:
            logger.debug(f"No token provided for protected route: {path}")
            return False, None, {}

        # Validate token
        user = await validate_jwt_token(token)

        if not user:
            logger.warning(f"Invalid token for route: {path}")
            return False, None, {}

        logger.debug(f"Authenticated user {user.preferred_username} for route: {path}")
        return True, user, build_internal_headers(user)


# Global middleware instance
auth_middleware = GatewayAuthMiddleware()
