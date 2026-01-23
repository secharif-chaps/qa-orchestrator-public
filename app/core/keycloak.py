"""Keycloak integration for authentication and authorization.

This module initializes and exports the FastAPIKeycloak client instance
that provides JWT validation and role-based access control for all routes.

Usage:
    from app.core.keycloak import idp

    @router.get("/admin/users")
    def list_users(user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin"]))):
        return {"users": [...]}
"""

import time
from typing import Optional, Any
from fastapi_keycloak import FastAPIKeycloak, OIDCUser as BaseOIDCUser
from requests.exceptions import (
    RequestException,
    Timeout,
    ConnectionError,
    HTTPError,
    SSLError,
)
from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)


class OIDCUser(BaseOIDCUser):
    """Extended OIDCUser with organization claim support.

    This extends the base fastapi-keycloak OIDCUser to include the
    'organization' claim from Keycloak Organizations feature.

    The organization claim format from Keycloak is:
    ["OrgName", {"OrgName": {"id": "uuid"}}]
    """
    organization: Optional[Any] = None  # Can be list, dict, or string depending on Keycloak config


def _initialize_keycloak_with_retry(
    max_retries: int = 5, initial_backoff: float = 2.0
) -> FastAPIKeycloak:
    """Initialize FastAPIKeycloak with exponential backoff retry logic.

    During startup, Keycloak may not be immediately available or may be slow to respond.
    This function retries the connection with exponential backoff to handle temporary
    connectivity issues or slow Keycloak initialization.

    Args:
        max_retries: Maximum number of retry attempts (default: 5)
        initial_backoff: Initial backoff delay in seconds (default: 2.0)

    Returns:
        Initialized FastAPIKeycloak instance

    Raises:
        ConnectionError: If connection fails after all retry attempts
    """
    backoff = initial_backoff
    # Construct expected OpenID configuration URL for debugging
    openid_config_url = (
        f"{settings.KEYCLOAK_SERVER_URL}/realms/{settings.KEYCLOAK_REALM}"
        f"/.well-known/openid-configuration"
    )

    for attempt in range(1, max_retries + 1):
        start_time = time.time()
        try:
            # Log all Keycloak configuration parameters for debugging
            logger.info(
                f"🔐 Attempting Keycloak connection (attempt {attempt}/{max_retries})\n"
                f"  server_url: {settings.KEYCLOAK_SERVER_URL}\n"
                f"  realm: {settings.KEYCLOAK_REALM}\n"
                f"  client_id: {settings.KEYCLOAK_CLIENT_ID}\n"
                f"  admin_client_id: {settings.KEYCLOAK_ADMIN_CLIENT_ID}\n"
                f"  callback_uri: {settings.KEYCLOAK_CALLBACK_URI}\n"
                f"  timeout: 60s\n"
                f"  openid_config_url: {openid_config_url}\n"
                f"  has_client_secret: {bool(settings.KEYCLOAK_CLIENT_SECRET)}\n"
                f"  has_admin_secret: {bool(settings.KEYCLOAK_ADMIN_CLIENT_SECRET)}"
            )

            # Initialize FastAPIKeycloak with increased timeout (60s instead of default 10s)
            # This handles slow network or Keycloak response times in production
            idp_instance = FastAPIKeycloak(
                server_url=settings.KEYCLOAK_SERVER_URL,
                client_id=settings.KEYCLOAK_CLIENT_ID,
                client_secret=settings.KEYCLOAK_CLIENT_SECRET,
                admin_client_id=settings.KEYCLOAK_ADMIN_CLIENT_ID,
                admin_client_secret=settings.KEYCLOAK_ADMIN_CLIENT_SECRET,
                realm=settings.KEYCLOAK_REALM,
                callback_uri=settings.KEYCLOAK_CALLBACK_URI,
                timeout=60,  # Increased from default 10s to handle production latency
            )

            # Monkey-patch the user model to use our custom OIDCUser with organization support
            idp_instance.user_model = OIDCUser

            elapsed_time = time.time() - start_time
            logger.info(
                f"✅ Keycloak client initialized successfully\n"
                f"  server_url: {settings.KEYCLOAK_SERVER_URL}\n"
                f"  realm: {settings.KEYCLOAK_REALM}\n"
                f"  client_id: {settings.KEYCLOAK_CLIENT_ID}\n"
                f"  admin_client_id: {settings.KEYCLOAK_ADMIN_CLIENT_ID}\n"
                f"  elapsed_seconds: {round(elapsed_time, 2)}\n"
                f"  total_retries: {attempt - 1}\n"
                f"  attempt: {attempt}"
            )

            return idp_instance

        except SSLError as e:
            # SSL-specific error (certificate validation, handshake failure)
            elapsed_time = time.time() - start_time
            error_details = {
                "exception_type": "SSLError",
                "error_message": str(e),
                "server_url": settings.KEYCLOAK_SERVER_URL,
                "openid_config_url": openid_config_url,
                "elapsed_seconds": round(elapsed_time, 2),
                "ssl_error_details": repr(e),
                "attempt": attempt,
                "max_retries": max_retries,
            }

            if attempt == max_retries:
                logger.critical(
                    "SSL handshake failed after all retries - check SSL certificates and TLS configuration",
                    exc_info=True,
                    extra=error_details,
                )
                raise ConnectionError(
                    f"SSL connection to Keycloak failed at {settings.KEYCLOAK_SERVER_URL} "
                    f"after {max_retries} attempts. SSL Error: {str(e)}"
                ) from e

            logger.warning(
                f"SSL error during Keycloak connection (attempt {attempt}/{max_retries}), retrying in {backoff}s",
                extra={**error_details, "backoff_seconds": backoff},
            )

            time.sleep(backoff)
            backoff *= 2

        except Timeout as e:
            # Timeout error - request took longer than configured timeout
            elapsed_time = time.time() - start_time
            error_details = {
                "exception_type": "Timeout",
                "error_message": str(e),
                "server_url": settings.KEYCLOAK_SERVER_URL,
                "openid_config_url": openid_config_url,
                "elapsed_seconds": round(elapsed_time, 2),
                "configured_timeout": 60,
                "attempt": attempt,
                "max_retries": max_retries,
            }

            if attempt == max_retries:
                logger.critical(
                    "Connection to Keycloak timed out after all retries - Keycloak may be slow or unreachable",
                    exc_info=True,
                    extra=error_details,
                )
                raise ConnectionError(
                    f"Timeout connecting to Keycloak at {settings.KEYCLOAK_SERVER_URL} "
                    f"after {max_retries} attempts (60s timeout). Error: {str(e)}"
                ) from e

            logger.warning(
                f"Timeout during Keycloak connection (attempt {attempt}/{max_retries}), retrying in {backoff}s",
                extra={**error_details, "backoff_seconds": backoff},
            )

            time.sleep(backoff)
            backoff *= 2

        except HTTPError as e:
            # HTTP error (4xx, 5xx response codes)
            elapsed_time = time.time() - start_time
            status_code = e.response.status_code if hasattr(e, "response") else None
            response_text = (
                e.response.text[:500] if hasattr(e, "response") else None
            )  # Limit to 500 chars

            error_details = {
                "exception_type": "HTTPError",
                "error_message": str(e),
                "server_url": settings.KEYCLOAK_SERVER_URL,
                "openid_config_url": openid_config_url,
                "elapsed_seconds": round(elapsed_time, 2),
                "http_status_code": status_code,
                "response_text": response_text,
                "attempt": attempt,
                "max_retries": max_retries,
            }

            if attempt == max_retries:
                logger.critical(
                    f"HTTP error from Keycloak after all retries (status: {status_code})",
                    exc_info=True,
                    extra=error_details,
                )
                raise ConnectionError(
                    f"HTTP error from Keycloak at {settings.KEYCLOAK_SERVER_URL} "
                    f"after {max_retries} attempts. Status: {status_code}, Error: {str(e)}"
                ) from e

            logger.warning(
                f"HTTP error during Keycloak connection (status: {status_code}, attempt {attempt}/{max_retries}), retrying in {backoff}s",
                extra={**error_details, "backoff_seconds": backoff},
            )

            time.sleep(backoff)
            backoff *= 2

        except ConnectionError as e:
            # Network connection error (DNS, refused connection, etc.)
            elapsed_time = time.time() - start_time
            error_details = {
                "exception_type": "ConnectionError",
                "error_message": str(e),
                "server_url": settings.KEYCLOAK_SERVER_URL,
                "openid_config_url": openid_config_url,
                "elapsed_seconds": round(elapsed_time, 2),
                "attempt": attempt,
                "max_retries": max_retries,
            }

            if attempt == max_retries:
                logger.critical(
                    "Network connection to Keycloak failed after all retries - check DNS, firewall, and network connectivity",
                    exc_info=True,
                    extra=error_details,
                )
                raise ConnectionError(
                    f"Network error connecting to Keycloak at {settings.KEYCLOAK_SERVER_URL} "
                    f"after {max_retries} attempts. Error: {str(e)}"
                ) from e

            logger.warning(
                f"Network error during Keycloak connection (attempt {attempt}/{max_retries}), retrying in {backoff}s",
                extra={**error_details, "backoff_seconds": backoff},
            )

            time.sleep(backoff)
            backoff *= 2

        except AttributeError as e:
            # AttributeError during initialization - usually from fastapi-keycloak
            # when response.json() returns None instead of valid JSON
            # This happens when Keycloak returns empty/malformed response
            elapsed_time = time.time() - start_time
            error_details = {
                "exception_type": "AttributeError",
                "error_message": str(e),
                "server_url": settings.KEYCLOAK_SERVER_URL,
                "openid_config_url": openid_config_url,
                "elapsed_seconds": round(elapsed_time, 2),
                "attempt": attempt,
                "max_retries": max_retries,
                "hint": "Keycloak returned empty/null response during admin token request",
            }

            if attempt == max_retries:
                logger.critical(
                    "Keycloak initialization failed after all retries - received null/empty response from Keycloak",
                    exc_info=True,
                    extra=error_details,
                )
                raise ConnectionError(
                    f"Keycloak at {settings.KEYCLOAK_SERVER_URL} returned null/empty response "
                    f"after {max_retries} attempts. This usually indicates Keycloak is unreachable, "
                    f"misconfigured, or returning invalid responses. Error: {str(e)}"
                ) from e

            logger.warning(
                f"Keycloak returned null response (attempt {attempt}/{max_retries}), retrying in {backoff}s",
                extra={**error_details, "backoff_seconds": backoff},
            )

            time.sleep(backoff)
            backoff *= 2

        except RequestException as e:
            # Generic requests exception (catch-all for other request errors)
            elapsed_time = time.time() - start_time
            error_details = {
                "exception_type": type(e).__name__,
                "error_message": str(e),
                "server_url": settings.KEYCLOAK_SERVER_URL,
                "openid_config_url": openid_config_url,
                "elapsed_seconds": round(elapsed_time, 2),
                "attempt": attempt,
                "max_retries": max_retries,
            }

            if attempt == max_retries:
                logger.critical(
                    f"Request to Keycloak failed after all retries ({type(e).__name__})",
                    exc_info=True,
                    extra=error_details,
                )
                raise ConnectionError(
                    f"Request error connecting to Keycloak at {settings.KEYCLOAK_SERVER_URL} "
                    f"after {max_retries} attempts. Error: {str(e)}"
                ) from e

            logger.warning(
                f"Request error during Keycloak connection ({type(e).__name__}, attempt {attempt}/{max_retries}), retrying in {backoff}s",
                extra={**error_details, "backoff_seconds": backoff},
            )

            time.sleep(backoff)
            backoff *= 2

    # This should never be reached, but satisfies type checker
    raise ConnectionError("Unexpected error in Keycloak initialization")


# Initialize FastAPIKeycloak client with retry logic
_raw_idp = _initialize_keycloak_with_retry()


# =============================================================================
# Internal Trust Wrapper (Phase 1 - Gateway Integration)
# =============================================================================
# This wrapper intercepts get_current_user() calls and checks for internal
# requests from the gateway. If detected, it skips JWT validation and
# extracts user info from gateway-provided headers.

# Must match values in global-service/app/core/auth_middleware.py
INTERNAL_REQUEST_HEADER = "X-Internal-Request"
INTERNAL_REQUEST_SECRET = "gateway-internal-v1"
USER_ID_HEADER = "X-User-Id"
USER_NAME_HEADER = "X-User-Name"
USER_ROLES_HEADER = "X-User-Roles"
USER_ORG_HEADER = "X-User-Organization"


def _is_internal_request(request) -> bool:
    """Check if request is from the gateway (has valid internal header)."""
    header_value = request.headers.get(INTERNAL_REQUEST_HEADER, "")
    return header_value == INTERNAL_REQUEST_SECRET


def _create_user_from_headers(request) -> Optional[OIDCUser]:
    """Create OIDCUser from gateway-provided headers."""
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

    return OIDCUser(
        sub=user_id,
        preferred_username=username,
        realm_access={"roles": roles},
        organization=organization,
        # Required fields with default values for internal requests
        iat=0,
        exp=0,
        iss="gateway-internal",
        aud=[],
        azp="",
        email_verified=True,  # Assume email is verified for internal requests
    )


class InternalTrustIDPWrapper:
    """
    Wrapper for FastAPIKeycloak that trusts internal requests from the gateway.

    For internal requests (X-Internal-Request header present):
    - Skips JWT validation completely (already done at gateway)
    - Creates user from X-User-* headers

    For external requests:
    - Validates JWT via Keycloak as normal
    """

    def __init__(self, wrapped_idp: FastAPIKeycloak):
        self._wrapped = wrapped_idp

    def __getattr__(self, name):
        """Forward all other attributes to the wrapped idp."""
        return getattr(self._wrapped, name)

    def get_current_user(self, required_roles=None):
        """
        Get current user with internal trust support.

        For internal requests: Skip JWT validation, use gateway headers
        For external requests: Validate JWT normally via Keycloak

        Returns a FastAPI dependency that can be used with Depends().
        """
        from fastapi import Request, HTTPException, status

        async def get_user_with_internal_trust(
            request: Request,
        ) -> OIDCUser:
            # Check if this is an internal request from gateway FIRST
            if _is_internal_request(request):
                logger.debug("Internal request detected - skipping JWT validation")
                user = _create_user_from_headers(request)

                if not user:
                    logger.warning("Internal request missing user headers")
                    raise HTTPException(
                        status_code=status.HTTP_401_UNAUTHORIZED,
                        detail="Missing user information in internal request"
                    )

                # Check required roles if specified
                if required_roles:
                    user_roles = set(user.realm_access.get("roles", []) if user.realm_access else [])
                    if not any(role in user_roles for role in required_roles):
                        logger.warning(
                            f"Internal user {user.preferred_username} missing required roles: {required_roles}"
                        )
                        raise HTTPException(
                            status_code=status.HTTP_403_FORBIDDEN,
                            detail="Insufficient permissions"
                        )

                logger.debug(f"Internal auth OK: user={user.preferred_username}")
                return user

            # External request - validate JWT with Keycloak
            # Get the original dependency and call it
            keycloak_dependency = self._wrapped.get_current_user(required_roles=required_roles)

            # The keycloak dependency expects to be injected by FastAPI
            # We need to call it with the request
            try:
                # FastAPI-keycloak's get_current_user returns an async function
                # that extracts token from Authorization header and validates it
                user = await keycloak_dependency(request=request)
                return user
            except TypeError:
                # If it doesn't accept request directly, it's a dependency
                # In this case, we need to manually extract and validate the token
                auth_header = request.headers.get("Authorization", "")
                if not auth_header.startswith("Bearer "):
                    raise HTTPException(
                        status_code=status.HTTP_401_UNAUTHORIZED,
                        detail="Not authenticated"
                    )

                token = auth_header[7:]

                # Use the idp's decode_token method
                try:
                    decoded = self._wrapped.decode_token(token)
                    user = OIDCUser(**decoded)

                    # Check roles
                    if required_roles:
                        user_roles = set(user.realm_access.get("roles", []) if user.realm_access else [])
                        if not any(role in user_roles for role in required_roles):
                            raise HTTPException(
                                status_code=status.HTTP_403_FORBIDDEN,
                                detail="Insufficient permissions"
                            )

                    return user
                except Exception as e:
                    logger.debug(f"Token validation failed: {e}")
                    raise HTTPException(
                        status_code=status.HTTP_401_UNAUTHORIZED,
                        detail="Invalid authentication credentials"
                    )

        return get_user_with_internal_trust


# Wrap the idp with internal trust support
# All existing code using `idp.get_current_user()` will automatically benefit
idp = InternalTrustIDPWrapper(_raw_idp)
