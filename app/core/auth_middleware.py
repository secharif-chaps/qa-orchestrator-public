"""
Authentication middleware for the gateway.

This middleware validates JWT tokens at the gateway level and adds
internal trust headers for requests forwarded to the backend monolith.

Phase 1 Implementation:
- Validates JWT tokens using Keycloak's public key
- Returns 401 for invalid/missing tokens (except public routes)
- Adds X-Internal-Request header for trusted internal communication
- Forwards user info headers to backend
"""

from typing import Optional, Set, Any
import httpx
from jose import jwt, JWTError
from cachetools import TTLCache
from fastapi import Request
from pydantic import BaseModel
from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

# Cache for Keycloak public key (TTL: 1 hour)
_public_key_cache: TTLCache = TTLCache(maxsize=1, ttl=3600)


class GatewayUser(BaseModel):
    """User information extracted from JWT token."""
    sub: str
    preferred_username: Optional[str] = None
    email: Optional[str] = None
    realm_access: Optional[dict] = None
    organization: Optional[Any] = None


# Internal request header name and secret
INTERNAL_REQUEST_HEADER = "X-Internal-Request"
INTERNAL_REQUEST_SECRET = "gateway-internal-v1"  # TODO: Move to env var in production

# Headers to forward user info to backend
USER_ID_HEADER = "X-User-Id"
USER_NAME_HEADER = "X-User-Name"
USER_ROLES_HEADER = "X-User-Roles"
USER_ORG_HEADER = "X-User-Organization"

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

        return user

    except jwt.ExpiredSignatureError:
        logger.debug("JWT token has expired")
        return None
    except JWTError as e:
        logger.debug(f"JWT validation failed: {e}")
        return None
    except Exception as e:
        logger.error(f"Unexpected error validating JWT: {e}")
        return None


def build_internal_headers(user: Optional[GatewayUser]) -> dict:
    """
    Build internal request headers to forward to backend.

    These headers tell the backend:
    1. This is an internal request from the gateway (trusted)
    2. User info extracted from the validated JWT
    """
    headers = {
        INTERNAL_REQUEST_HEADER: INTERNAL_REQUEST_SECRET,
    }

    if user:
        headers[USER_ID_HEADER] = user.sub
        headers[USER_NAME_HEADER] = user.preferred_username or ""

        # Extract roles from realm_access
        roles = []
        if user.realm_access:
            roles = user.realm_access.get("roles", [])
        headers[USER_ROLES_HEADER] = ",".join(roles)

        # Extract organization info
        if user.organization:
            # Organization claim format: ["OrgName", {"OrgName": {"id": "uuid"}}]
            org_info = user.organization
            if isinstance(org_info, list) and len(org_info) >= 2:
                # Get the org name (first element) and id (from second element)
                org_name = org_info[0] if isinstance(org_info[0], str) else ""
                org_dict = org_info[1] if len(org_info) > 1 else {}
                if isinstance(org_dict, dict) and org_name in org_dict:
                    org_id = org_dict[org_name].get("id", "")
                    headers[USER_ORG_HEADER] = f"{org_name}:{org_id}"
                else:
                    headers[USER_ORG_HEADER] = org_name
            elif isinstance(org_info, str):
                headers[USER_ORG_HEADER] = org_info

    return headers


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
