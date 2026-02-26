# Internal JWT Authentication - Service-to-Service Communication

## Overview

This document specifies the internal JWT authentication mechanism for secure communication between the Global Service (gateway) and backend services (Screen, Target, etc.).

**Problem**: The current implementation uses a static secret header (`X-Internal-Request: gateway-internal-v1`) which:
- Never expires
- Cannot be revoked without redeployment
- Has no cryptographic signature
- Is vulnerable if compromised

**Solution**: Gateway-signed JWTs with short expiration (60 seconds), containing user context from the original request, with optional IP allowlist validation.

---

## Architecture

```
┌──────────┐      1. User JWT        ┌─────────────┐
│ Frontend │ ─────────────────────▶  │   Gateway   │
└──────────┘                         │  (global)   │
                                     └──────┬──────┘
                                            │
                    2. Validate user JWT    │
                       via Keycloak         │
                                            │
                    3. Create internal JWT  │
                       (signed, 60s TTL)    │
                                            │
                                            ▼
                                     ┌─────────────┐
                                     │   Backend   │
                                     │  (screen)   │
                                     └──────┬──────┘
                                            │
                    4. Verify source IP     │
                       (if allowlist set)   │
                                            │
                    5. Verify internal JWT  │
                       (shared secret)      │
                                            │
                    6. Extract user context │
                       from JWT payload     │
                                            ▼
                                     ┌─────────────┐
                                     │  Response   │
                                     └─────────────┘
```

---

## Security Layers

### Layer 1: Internal JWT Signature
- HMAC-SHA256 signed tokens
- Shared secret between gateway and backends
- 60 second expiration

### Layer 2: IP Allowlist (Optional)
- Only accepts `Authorization: Internal` from configured IP ranges
- Disabled by default (empty `INTERNAL_ALLOWED_IPS`)
- When enabled, adds defense-in-depth against token theft

---

## Internal JWT Specification

### Token Structure

```json
{
  "sub": "user-uuid-from-keycloak",
  "username": "john.doe",
  "email": "john.doe@example.com",
  "org_id": "organization-uuid",
  "org_name": "Acme Corp",
  "roles": ["company.view", "company.create", "organization.read"],
  "iss": "global-gateway",
  "iat": 1706540123,
  "exp": 1706540183
}
```

### Token Properties

| Property | Value | Description |
|----------|-------|-------------|
| Algorithm | HS256 | HMAC-SHA256 symmetric signing |
| Expiration | 60 seconds | Short-lived for transit only |
| Issuer | `global-gateway` | Identifies the gateway as issuer |
| Secret | 32+ chars | Shared between gateway and backends |

### Header Format

```
Authorization: Internal eyJhbGciOiJIUzI1NiIs...
```

The `Internal` prefix (instead of `Bearer`) distinguishes internal tokens from user JWTs.

---

## Implementation Changes by MR

### MR 1: chapsmind-global-service

#### 1. Add Environment Variables

**File**: `app/core/config.py`

```python
class Settings(BaseSettings):
    # ... existing settings ...

    # Internal JWT for service-to-service communication
    # Must be the same value in all services (gateway + backends)
    # Generate with: openssl rand -base64 32
    INTERNAL_JWT_SECRET: str = ""
    INTERNAL_JWT_EXPIRY_SECONDS: int = 60
```

#### 2. Create Internal JWT Module

**File**: `app/core/internal_jwt.py` (NEW)

```python
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
    sub: str                          # User ID (Keycloak sub)
    username: str                     # Preferred username
    email: Optional[str] = None       # User email
    org_id: str                       # Organization UUID
    org_name: str                     # Organization name
    roles: list[str]                  # User roles from realm_access
    iss: str = ISSUER                 # Issuer
    iat: int = 0                      # Issued at (set automatically)
    exp: int = 0                      # Expiration (set automatically)


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
        extra={"user_id": user_id, "org_id": org_id, "expiry_seconds": settings.INTERNAL_JWT_EXPIRY_SECONDS}
    )

    return token
```

#### 3. Update Auth Middleware

**File**: `app/core/auth_middleware.py`

Remove the static secret and use internal JWT:

```python
# REMOVE these lines:
# INTERNAL_REQUEST_HEADER = "X-Internal-Request"
# INTERNAL_REQUEST_SECRET = "gateway-internal-v1"

# REMOVE these lines:
# USER_ID_HEADER = "X-User-Id"
# USER_NAME_HEADER = "X-User-Name"
# USER_ROLES_HEADER = "X-User-Roles"
# USER_ORG_HEADER = "X-User-Organization"

# UPDATE build_internal_headers function:
from app.core.internal_jwt import create_internal_token

def build_internal_headers(user: Optional[GatewayUser], org_id: str, org_name: str) -> dict:
    """
    Build internal request headers with signed JWT.

    The internal JWT contains all user context, replacing the previous
    approach of multiple X-User-* headers.
    """
    if not user:
        return {}

    # Extract roles from realm_access
    roles = []
    if user.realm_access:
        roles = user.realm_access.get("roles", [])

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
```

#### 4. Update Proxy Routes

**File**: `app/proxy/routes.py`

Update to extract organization info and pass to `build_internal_headers`:

```python
from app.core.organization import extract_organization_from_token

@router.api_route("/{path:path}", methods=["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"])
async def proxy_to_backend(request: Request, path: str):
    # Validate JWT and get user
    is_valid, user, _ = await auth_middleware.validate_request(request, path)

    if not is_valid:
        return JSONResponse(status_code=401, content={"detail": "Unauthorized"})

    # Extract organization from validated user
    org_id, org_name = "", ""
    if user and user.organization:
        org_info = extract_organization_from_token({"organization": user.organization})
        if org_info:
            org_id, org_name = org_info

    # Build headers with internal JWT
    internal_headers = build_internal_headers(user, org_id, org_name)

    # Forward request with internal auth
    # ... rest of proxy logic
```

---

### MR 7: infra

#### 1. Add Environment Variables to Docker Compose

**File**: `docker-compose.yml`

```yaml
global-service:
  environment:
    # ... existing env vars ...
    INTERNAL_JWT_SECRET: ${INTERNAL_JWT_SECRET}  # From .env file

backend:
  environment:
    # ... existing env vars ...
    INTERNAL_JWT_SECRET: ${INTERNAL_JWT_SECRET}  # Same value!
    # Optional: IP allowlist for internal auth (comma-separated CIDR ranges)
    # Leave empty to disable IP validation
    INTERNAL_ALLOWED_IPS: ${INTERNAL_ALLOWED_IPS:-}
```

**File**: `docker-compose.local.yml`

```yaml
global-service:
  environment:
    # ... existing env vars ...
    INTERNAL_JWT_SECRET: "local-dev-internal-jwt-secret-32chars"

backend:
  environment:
    # ... existing env vars ...
    INTERNAL_JWT_SECRET: "local-dev-internal-jwt-secret-32chars"
    # In local dev, allow Docker network IPs
    INTERNAL_ALLOWED_IPS: "172.16.0.0/12,192.168.0.0/16,10.0.0.0/8"
```

#### 2. Add to .env.example

**File**: `.env.example` (create or update)

```bash
# Internal JWT Secret for service-to-service communication
# Generate with: openssl rand -base64 32
# MUST be the same value for global-service and backend
INTERNAL_JWT_SECRET=your-production-secret-here-min-32-chars

# Optional: Restrict internal auth to specific IP ranges (comma-separated CIDR)
# Leave empty to disable IP validation (only JWT signature is checked)
# Examples:
#   Kubernetes pod network: "10.244.0.0/16"
#   Docker network: "172.16.0.0/12"
#   Multiple ranges: "10.244.0.0/16,10.245.0.0/16"
INTERNAL_ALLOWED_IPS=
```

#### 3. Kubernetes ConfigMap (if applicable)

**File**: `k8s/configmap.yaml`

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: chapsmind-config
data:
  # Internal JWT is in Secret, not ConfigMap
  # IP allowlist for internal auth
  INTERNAL_ALLOWED_IPS: "10.244.0.0/16"  # Kubernetes pod CIDR
```

---

### MR 116: screen-poc (backend)

#### 1. Add Environment Variables to Config

**File**: `app/core/config.py`

```python
from typing import Optional

class Settings(BaseSettings):
    # ... existing settings ...

    # Internal JWT for gateway communication
    INTERNAL_JWT_SECRET: str = ""
    INTERNAL_JWT_EXPIRY_SECONDS: int = 60

    # Optional: Comma-separated list of allowed IP ranges (CIDR notation)
    # for internal authentication. If empty, IP validation is disabled.
    # Example: "10.244.0.0/16,172.16.0.0/12"
    INTERNAL_ALLOWED_IPS: str = ""
```

#### 2. Create Internal JWT Module

**File**: `app/core/internal_jwt.py` (NEW)

```python
"""
Internal JWT verification for service-to-service communication.

This module verifies internal JWTs from the gateway and optionally
validates the source IP against an allowlist.

Security layers:
1. JWT signature verification (always)
2. IP allowlist validation (optional, if INTERNAL_ALLOWED_IPS is set)
"""

import jwt
import ipaddress
from datetime import datetime, timezone
from typing import Optional, List
from pydantic import BaseModel
from fastapi import Request

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

ALGORITHM = "HS256"
ISSUER = "global-gateway"
INTERNAL_AUTH_PREFIX = "Internal "


class InternalTokenPayload(BaseModel):
    """Payload structure for internal JWT tokens."""
    sub: str                          # User ID (Keycloak sub)
    username: str                     # Preferred username
    email: Optional[str] = None       # User email
    org_id: str                       # Organization UUID
    org_name: str                     # Organization name
    roles: list[str]                  # User roles from realm_access
    iss: str = ISSUER                 # Issuer
    iat: int = 0                      # Issued at
    exp: int = 0                      # Expiration


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


# Cache parsed IP networks to avoid re-parsing on every request
_allowed_networks: Optional[List[ipaddress.IPv4Network | ipaddress.IPv6Network]] = None
_allowed_networks_parsed: bool = False


def _get_allowed_networks() -> List[ipaddress.IPv4Network | ipaddress.IPv6Network]:
    """
    Parse and cache the allowed IP networks from config.

    Returns empty list if INTERNAL_ALLOWED_IPS is not set (IP validation disabled).
    """
    global _allowed_networks, _allowed_networks_parsed

    if _allowed_networks_parsed:
        return _allowed_networks or []

    _allowed_networks_parsed = True

    if not settings.INTERNAL_ALLOWED_IPS:
        logger.info("INTERNAL_ALLOWED_IPS not set - IP validation disabled for internal auth")
        _allowed_networks = []
        return []

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

    _allowed_networks = networks
    logger.info(f"Internal auth IP allowlist configured with {len(networks)} network(s)")
    return networks


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
            logger.debug(f"Internal auth IP {client_ip_str} allowed (matches {network})")
            return

    logger.warning(
        f"Internal auth rejected: IP {client_ip_str} not in allowlist",
        extra={"client_ip": client_ip_str, "allowed_networks": [str(n) for n in allowed_networks]}
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
            }
        )

        logger.debug(
            "Verified internal token",
            extra={"user_id": payload.get("sub"), "org_id": payload.get("org_id")}
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

    token = auth[len(INTERNAL_AUTH_PREFIX):]
    return verify_internal_token(token)
```

#### 3. Update Keycloak Integration

**File**: `app/core/keycloak.py`

Replace the `InternalTrustIDPWrapper` with internal JWT validation:

```python
from app.core.internal_jwt import (
    is_internal_request,
    verify_internal_request,
    InternalTokenPayload,
    InternalJWTError,
    TokenExpiredError,
    TokenInvalidError,
    IPNotAllowedError,
)


def _get_internal_user(request: Request) -> Optional[OIDCUser]:
    """Extract user from internal JWT with IP validation."""
    try:
        # This performs both IP validation (if configured) and JWT validation
        payload = verify_internal_request(request)

        # Convert to OIDCUser for compatibility with existing code
        return OIDCUser(
            sub=payload.sub,
            preferred_username=payload.username,
            email=payload.email,
            realm_access={"roles": payload.roles},
            organization=[payload.org_name, {payload.org_name: {"id": payload.org_id}}],
            # Required fields with defaults
            iat=payload.iat,
            exp=payload.exp,
            iss=payload.iss,
            aud=[],
            azp="",
        )

    except IPNotAllowedError as e:
        logger.warning(f"Internal auth IP validation failed: {e}")
        return None

    except (TokenExpiredError, TokenInvalidError) as e:
        logger.warning(f"Internal JWT validation failed: {e}")
        return None

    except InternalJWTError as e:
        logger.error(f"Internal auth error: {e}")
        return None


class InternalTrustIDPWrapper:
    """Wrapper that handles both internal JWT and external Keycloak JWT."""

    def __init__(self, wrapped_idp: FastAPIKeycloak):
        self._wrapped = wrapped_idp

    def __getattr__(self, name):
        return getattr(self._wrapped, name)

    def get_current_user(self, required_roles: Optional[List[str]] = None):
        """Get current user from internal JWT or Keycloak JWT."""

        async def get_user_dependency(request: Request) -> OIDCUser:
            # Try internal JWT first
            if is_internal_request(request):
                user = _get_internal_user(request)

                if not user:
                    raise HTTPException(
                        status_code=status.HTTP_401_UNAUTHORIZED,
                        detail="Invalid internal authentication"
                    )

                # Check required roles
                if required_roles:
                    user_roles = set(user.realm_access.get("roles", []) if user.realm_access else [])
                    if not any(role in user_roles for role in required_roles):
                        raise HTTPException(
                            status_code=status.HTTP_403_FORBIDDEN,
                            detail="Insufficient permissions"
                        )

                logger.debug(f"Internal auth OK: user={user.preferred_username}")
                return user

            # Fall back to Keycloak JWT validation
            keycloak_dep = self._wrapped.get_current_user(required_roles=required_roles)
            return await keycloak_dep(request=request)

        return get_user_dependency
```

#### 4. Update Organization Context

**File**: `app/core/organization.py`

Simplify to extract org from internal JWT payload (already in user object):

```python
def get_user_organization(
    request: Request,
    user: OIDCUser = Depends(idp.get_current_user())
) -> OrganizationContext:
    """Extract organization context from user (works for both internal and external auth)."""

    # Organization is already populated by the auth layer
    # For internal JWT: from payload.org_id/org_name
    # For Keycloak JWT: from organization claim

    if hasattr(user, 'organization') and user.organization:
        org_info = extract_organization_from_token({"organization": user.organization})
        if org_info:
            org_id, org_name = org_info
            return OrganizationContext(
                organization_id=org_id,
                organization_name=org_name,
                user_id=user.sub,
                username=user.preferred_username or ""
            )

    raise HTTPException(
        status_code=status.HTTP_403_FORBIDDEN,
        detail="User must be assigned to an organization"
    )
```

#### 5. Remove Deprecated Code

**File**: `app/core/internal_auth.py`

Delete this file entirely - it's replaced by `internal_jwt.py`.

**File**: `app/core/keycloak.py`

Remove:
- `INTERNAL_REQUEST_HEADER`
- `INTERNAL_REQUEST_SECRET`
- `USER_ID_HEADER`, `USER_NAME_HEADER`, `USER_ROLES_HEADER`, `USER_ORG_HEADER`
- `_is_internal_request` (old version)
- `_create_user_from_headers` function

**File**: `app/core/organization.py`

Remove:
- `_is_internal_request` function (old version)
- `_extract_org_from_internal_header` function

---

### MR 144: screen-front

No changes required. The frontend continues to send user JWTs to the gateway as before.

---

## Environment Variables Summary

| Variable | Service | Required | Description |
|----------|---------|----------|-------------|
| `INTERNAL_JWT_SECRET` | gateway, backend | Yes | Shared secret for signing/verifying internal JWTs |
| `INTERNAL_JWT_EXPIRY_SECONDS` | gateway, backend | No | Token expiry (default: 60) |
| `INTERNAL_ALLOWED_IPS` | backend | No | Comma-separated CIDR ranges for IP validation |

### Generating the Secret

```bash
# Generate a secure 32-byte secret
openssl rand -base64 32

# Example output: K7gNU3sdo+OL0wNhqoVWhr3g6s1xYv72ol/pe/Unols=
```

### IP Allowlist Examples

```bash
# Disable IP validation (default)
INTERNAL_ALLOWED_IPS=

# Kubernetes pod network only
INTERNAL_ALLOWED_IPS=10.244.0.0/16

# Docker default networks
INTERNAL_ALLOWED_IPS=172.16.0.0/12,192.168.0.0/16

# Multiple Kubernetes clusters
INTERNAL_ALLOWED_IPS=10.244.0.0/16,10.245.0.0/16,10.246.0.0/16

# Specific gateway IP (most restrictive)
INTERNAL_ALLOWED_IPS=10.244.1.5/32
```

---

## Testing

### Unit Tests for Internal JWT

**File**: `tests/core/test_internal_jwt.py`

```python
import pytest
import time
from unittest.mock import patch, MagicMock
from app.core.internal_jwt import (
    create_internal_token,
    verify_internal_token,
    verify_internal_request,
    validate_source_ip,
    is_internal_request,
    TokenExpiredError,
    TokenInvalidError,
    IPNotAllowedError,
    InternalJWTError,
    _get_allowed_networks,
)


@pytest.fixture
def mock_settings():
    with patch("app.core.internal_jwt.settings") as mock:
        mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
        mock.INTERNAL_JWT_EXPIRY_SECONDS = 60
        mock.INTERNAL_ALLOWED_IPS = ""
        yield mock


@pytest.fixture
def mock_settings_with_ip_allowlist():
    with patch("app.core.internal_jwt.settings") as mock:
        mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
        mock.INTERNAL_JWT_EXPIRY_SECONDS = 60
        mock.INTERNAL_ALLOWED_IPS = "10.244.0.0/16,172.16.0.0/12"
        # Reset cached networks
        import app.core.internal_jwt as jwt_module
        jwt_module._allowed_networks = None
        jwt_module._allowed_networks_parsed = False
        yield mock


def test_create_and_verify_token(mock_settings):
    """Test token creation and verification roundtrip."""
    token = create_internal_token(
        user_id="user-123",
        username="testuser",
        org_id="org-456",
        org_name="Test Org",
        roles=["company.view", "company.create"],
        email="test@example.com",
    )

    payload = verify_internal_token(token)

    assert payload.sub == "user-123"
    assert payload.username == "testuser"
    assert payload.org_id == "org-456"
    assert payload.org_name == "Test Org"
    assert payload.roles == ["company.view", "company.create"]
    assert payload.email == "test@example.com"
    assert payload.iss == "global-gateway"


def test_expired_token(mock_settings):
    """Test that expired tokens are rejected."""
    mock_settings.INTERNAL_JWT_EXPIRY_SECONDS = 1

    token = create_internal_token(
        user_id="user-123",
        username="testuser",
        org_id="org-456",
        org_name="Test Org",
        roles=[],
    )

    time.sleep(2)  # Wait for expiry

    with pytest.raises(TokenExpiredError):
        verify_internal_token(token)


def test_invalid_signature(mock_settings):
    """Test that tokens with wrong signature are rejected."""
    token = create_internal_token(
        user_id="user-123",
        username="testuser",
        org_id="org-456",
        org_name="Test Org",
        roles=[],
    )

    # Change the secret
    mock_settings.INTERNAL_JWT_SECRET = "different-secret-also-32-chars-long"

    with pytest.raises(TokenInvalidError):
        verify_internal_token(token)


def test_missing_secret():
    """Test that missing secret raises error."""
    with patch("app.core.internal_jwt.settings") as mock:
        mock.INTERNAL_JWT_SECRET = ""

        with pytest.raises(InternalJWTError, match="not configured"):
            create_internal_token(
                user_id="user-123",
                username="testuser",
                org_id="org-456",
                org_name="Test Org",
                roles=[],
            )


# IP Validation Tests

def test_ip_validation_disabled_when_empty(mock_settings):
    """Test that IP validation is skipped when INTERNAL_ALLOWED_IPS is empty."""
    mock_settings.INTERNAL_ALLOWED_IPS = ""

    # Reset cache
    import app.core.internal_jwt as jwt_module
    jwt_module._allowed_networks = None
    jwt_module._allowed_networks_parsed = False

    request = MagicMock()
    request.headers = {}
    request.client.host = "1.2.3.4"  # Any IP should work

    # Should not raise
    validate_source_ip(request)


def test_ip_validation_allows_valid_ip(mock_settings_with_ip_allowlist):
    """Test that IPs in allowlist are accepted."""
    request = MagicMock()
    request.headers = {}
    request.client.host = "10.244.1.5"  # In 10.244.0.0/16

    # Should not raise
    validate_source_ip(request)


def test_ip_validation_rejects_invalid_ip(mock_settings_with_ip_allowlist):
    """Test that IPs not in allowlist are rejected."""
    request = MagicMock()
    request.headers = {}
    request.client.host = "192.168.1.1"  # Not in allowlist

    with pytest.raises(IPNotAllowedError):
        validate_source_ip(request)


def test_ip_validation_uses_x_forwarded_for(mock_settings_with_ip_allowlist):
    """Test that X-Forwarded-For header is used for IP detection."""
    request = MagicMock()
    request.headers = {"X-Forwarded-For": "10.244.2.3, 192.168.1.1"}
    request.client.host = "192.168.1.1"  # Proxy IP (not in allowlist)

    # Should use 10.244.2.3 from X-Forwarded-For (first IP)
    validate_source_ip(request)


def test_is_internal_request():
    """Test internal request detection."""
    request = MagicMock()

    request.headers = {"Authorization": "Internal eyJhbGciOi..."}
    assert is_internal_request(request) is True

    request.headers = {"Authorization": "Bearer eyJhbGciOi..."}
    assert is_internal_request(request) is False

    request.headers = {}
    assert is_internal_request(request) is False
```

### Integration Test

**File**: `tests/integration/test_gateway_backend.py`

```python
import pytest
from httpx import AsyncClient


@pytest.mark.asyncio
async def test_gateway_to_backend_auth():
    """Test full flow: frontend -> gateway -> backend."""

    # 1. Get user token from Keycloak
    user_token = await get_test_user_token()

    # 2. Call gateway with user token
    async with AsyncClient(base_url="http://localhost:8001") as client:
        response = await client.get(
            "/api/companies",
            headers={"Authorization": f"Bearer {user_token}"}
        )

    # 3. Verify response (backend received and processed the request)
    assert response.status_code == 200


@pytest.mark.asyncio
async def test_direct_internal_token_rejected():
    """Test that external clients cannot use internal tokens."""
    # Create a valid internal token
    from app.core.internal_jwt import create_internal_token

    internal_token = create_internal_token(
        user_id="attacker",
        username="attacker",
        org_id="fake-org",
        org_name="Fake Org",
        roles=["admin"],
    )

    # Try to call backend directly (bypassing gateway)
    async with AsyncClient(base_url="http://localhost:8000") as client:
        response = await client.get(
            "/api/companies",
            headers={"Authorization": f"Internal {internal_token}"}
        )

    # Should be rejected due to IP validation (if enabled)
    # or succeed if IP validation is disabled
    # This test documents expected behavior
```

---

## Security Considerations

### Why 60 seconds expiry?

- Internal tokens only need to survive the request/response cycle
- 60 seconds allows for slow responses and retries
- Shorter expiry = smaller window if token is intercepted
- No need for refresh - each request gets a new token

### Why HMAC-SHA256 (symmetric)?

- Simpler than RSA (no key pair management)
- Faster signing/verification
- Suitable for trusted internal communication
- Single secret is easier to rotate

### Why optional IP validation?

- **Defense in depth**: Even if JWT secret leaks, attacker needs network access
- **Optional**: Some deployments may not have predictable IPs
- **Flexible**: Can be strict (single IP) or permissive (CIDR range)

### Threat Model

| Threat | Mitigation |
|--------|------------|
| JWT secret compromised | Short expiry (60s) + IP validation |
| Token interception | HTTPS + short expiry + IP validation |
| Replay attack | Short expiry (60s) |
| Token forgery | HMAC-SHA256 signature |
| IP spoofing | Difficult at network layer (TCP) |
| Lateral movement | IP allowlist restricts to gateway only |

### Secret Rotation

To rotate the secret without downtime:

1. Add new secret to both services (support both during transition)
2. Update gateway to sign with new secret
3. Wait for old tokens to expire (60s)
4. Remove old secret from verification

```python
# During rotation, verify with both secrets
INTERNAL_JWT_SECRETS = [
    settings.INTERNAL_JWT_SECRET,
    settings.INTERNAL_JWT_SECRET_OLD,  # Temporary during rotation
]

def verify_internal_token(token: str) -> InternalTokenPayload:
    for secret in INTERNAL_JWT_SECRETS:
        if not secret:
            continue
        try:
            return _verify_with_secret(token, secret)
        except TokenInvalidError:
            continue
    raise TokenInvalidError("Token not valid with any known secret")
```

---

## Migration Checklist

### MR 1 (global-service)

- [ ] Add `INTERNAL_JWT_SECRET` to `config.py`
- [ ] Add `INTERNAL_JWT_EXPIRY_SECONDS` to `config.py` (default: 60)
- [ ] Create `app/core/internal_jwt.py` with `create_internal_token()`
- [ ] Update `auth_middleware.py` to use `create_internal_token()`
- [ ] Update `proxy/routes.py` to extract org and build internal headers
- [ ] Remove static secret constants (`INTERNAL_REQUEST_SECRET`, `X-User-*` headers)
- [ ] Add unit tests for token creation

### MR 7 (infra)

- [ ] Add `INTERNAL_JWT_SECRET` to `docker-compose.yml` (global-service)
- [ ] Add `INTERNAL_JWT_SECRET` to `docker-compose.yml` (backend)
- [ ] Add `INTERNAL_ALLOWED_IPS` to `docker-compose.yml` (backend, optional)
- [ ] Add `INTERNAL_JWT_SECRET` to `docker-compose.local.yml`
- [ ] Add `INTERNAL_ALLOWED_IPS` to `docker-compose.local.yml` (Docker networks)
- [ ] Create/update `.env.example` with documentation
- [ ] Document secret generation in README

### MR 116 (screen-poc)

- [ ] Add `INTERNAL_JWT_SECRET` to `config.py`
- [ ] Add `INTERNAL_JWT_EXPIRY_SECONDS` to `config.py` (default: 60)
- [ ] Add `INTERNAL_ALLOWED_IPS` to `config.py` (default: empty)
- [ ] Create `app/core/internal_jwt.py` with verification + IP validation
- [ ] Update `keycloak.py` wrapper to use `verify_internal_request()`
- [ ] Simplify `organization.py` to use user object directly
- [ ] Delete `app/core/internal_auth.py`
- [ ] Remove old header-based constants from `keycloak.py`
- [ ] Remove old `_is_internal_request` from `organization.py`
- [ ] Add unit tests for token verification and IP validation

### MR 144 (screen-front)

- [ ] No changes required

---

## Future Improvements

1. **Shared Package**: Extract `internal_jwt.py` to a shared Python package used by all services
2. **Metrics**: Add Prometheus metrics for:
   - Token creation/verification latency
   - IP validation rejections
   - Token expiry rejections
3. **Tracing**: Add correlation ID in token for distributed tracing
4. **Key Rotation API**: Admin endpoint to trigger secret rotation
5. **Asymmetric Keys**: Consider RSA/ECDSA if secret sharing becomes complex