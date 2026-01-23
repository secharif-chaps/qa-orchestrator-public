"""
Internal authentication for gateway-to-backend communication.

This module provides authentication that trusts internal requests from the
Global Service gateway, avoiding double JWT validation.

Phase 1 Implementation:
- Detects X-Internal-Request header from gateway
- Extracts user info from X-User-* headers
- Falls back to normal JWT validation for external requests

Usage:
    # Replace idp.get_current_user() with internal_idp.get_current_user()
    from app.core.internal_auth import internal_idp

    @router.get("/endpoint")
    def endpoint(user: OIDCUser = Depends(internal_idp.get_current_user(required_roles=["admin"]))):
        return {"user": user.preferred_username}
"""

from typing import Optional, List, Any
from fastapi import Request, HTTPException, status, Depends
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from app.core.keycloak import idp, OIDCUser
from app.core.logging_config import get_logger

logger = get_logger(__name__)

# Must match the values in global-service/app/core/auth_middleware.py
INTERNAL_REQUEST_HEADER = "X-Internal-Request"
INTERNAL_REQUEST_SECRET = "gateway-internal-v1"  # TODO: Move to env var

# User info headers from gateway
USER_ID_HEADER = "X-User-Id"
USER_NAME_HEADER = "X-User-Name"
USER_ROLES_HEADER = "X-User-Roles"
USER_ORG_HEADER = "X-User-Organization"

# Security scheme (optional, for consistency)
security = HTTPBearer(auto_error=False)


class InternalUser(OIDCUser):
    """
    User created from internal gateway headers.

    Extends OIDCUser to be compatible with existing code that expects OIDCUser.
    """

    @classmethod
    def from_headers(cls, request: Request) -> Optional["InternalUser"]:
        """
        Create an InternalUser from gateway headers.

        Returns None if headers are missing or invalid.
        """
        user_id = request.headers.get(USER_ID_HEADER)
        if not user_id:
            return None

        username = request.headers.get(USER_NAME_HEADER, "")
        roles_str = request.headers.get(USER_ROLES_HEADER, "")
        org_str = request.headers.get(USER_ORG_HEADER, "")

        # Parse roles
        roles = [r.strip() for r in roles_str.split(",") if r.strip()]

        # Parse organization (format: "OrgName:OrgId" or just "OrgName")
        organization = None
        if org_str:
            if ":" in org_str:
                org_name, org_id = org_str.split(":", 1)
                organization = [org_name, {org_name: {"id": org_id}}]
            else:
                organization = [org_str, {}]

        # Create the user object
        # Note: We construct minimal OIDCUser-compatible object
        return cls(
            sub=user_id,
            preferred_username=username,
            realm_access={"roles": roles},
            organization=organization,
            # These fields are required by OIDCUser but may not be in headers
            iat=0,
            exp=0,
            iss="",
            aud=[],
            azp="",
        )


def is_internal_request(request: Request) -> bool:
    """
    Check if the request is an internal request from the gateway.

    Internal requests have the X-Internal-Request header with the correct secret.
    """
    header_value = request.headers.get(INTERNAL_REQUEST_HEADER, "")
    is_internal = header_value == INTERNAL_REQUEST_SECRET

    if is_internal:
        logger.debug("Internal request detected from gateway")

    return is_internal


class InternalTrustIDP:
    """
    IDP wrapper that trusts internal requests from the gateway.

    This class provides the same interface as FastAPIKeycloak's idp,
    but handles internal requests by extracting user info from headers
    instead of re-validating the JWT.
    """

    def get_current_user(self, required_roles: Optional[List[str]] = None):
        """
        Get current user dependency with internal trust support.

        For internal requests (from gateway):
        - Trusts the X-User-* headers
        - Skips JWT validation (already done at gateway)

        For external requests:
        - Uses normal JWT validation via fastapi-keycloak

        Args:
            required_roles: List of roles required for this endpoint

        Returns:
            FastAPI dependency that returns OIDCUser
        """

        async def get_user(
            request: Request,
            credentials: Optional[HTTPAuthorizationCredentials] = Depends(security),
        ) -> OIDCUser:
            # Check if this is an internal request from gateway
            if is_internal_request(request):
                # Create user from headers
                user = InternalUser.from_headers(request)

                if not user:
                    logger.warning("Internal request missing user headers")
                    raise HTTPException(
                        status_code=status.HTTP_401_UNAUTHORIZED,
                        detail="Missing user information in internal request"
                    )

                # Check required roles if specified
                if required_roles:
                    user_roles = set(
                        user.realm_access.get("roles", []) if user.realm_access else []
                    )
                    if not any(role in user_roles for role in required_roles):
                        logger.warning(
                            f"Internal user {user.preferred_username} missing required roles: {required_roles}"
                        )
                        raise HTTPException(
                            status_code=status.HTTP_403_FORBIDDEN,
                            detail="Insufficient permissions"
                        )

                logger.debug(
                    f"Internal auth: user={user.preferred_username}, "
                    f"roles={user.realm_access.get('roles', []) if user.realm_access else []}"
                )
                return user

            # External request - use normal Keycloak validation
            # This calls the fastapi-keycloak dependency directly
            keycloak_get_user = idp.get_current_user(required_roles=required_roles)

            # FastAPI dependencies need to be resolved properly
            # We call the dependency function which FastAPI will inject
            try:
                return await keycloak_get_user(request=request)
            except Exception as e:
                logger.debug(f"Keycloak auth failed: {e}")
                raise HTTPException(
                    status_code=status.HTTP_401_UNAUTHORIZED,
                    detail="Not authenticated"
                ) from e

        return get_user


# Global instance - use this instead of idp for endpoints that should support internal trust
internal_idp = InternalTrustIDP()
