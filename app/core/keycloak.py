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
from fastapi_keycloak import FastAPIKeycloak
from requests.exceptions import RequestException, Timeout, ConnectionError
from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)


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

    for attempt in range(1, max_retries + 1):
        try:
            logger.info(
                f"Initializing Keycloak client (attempt {attempt}/{max_retries})",
                extra={
                    "server_url": settings.KEYCLOAK_SERVER_URL,
                    "realm": settings.KEYCLOAK_REALM,
                    "client_id": settings.KEYCLOAK_CLIENT_ID,
                    "timeout": 60,
                },
            )

            # Initialize FastAPIKeycloak with increased timeout (60s instead of default 10s)
            # This handles slow network or Keycloak response times in production
            idp_instance = FastAPIKeycloak(
                server_url=settings.KEYCLOAK_SERVER_URL,
                client_id=settings.KEYCLOAK_CLIENT_ID,
                client_secret=settings.KEYCLOAK_CLIENT_SECRET,
                admin_client_secret=settings.KEYCLOAK_ADMIN_CLIENT_SECRET,
                realm=settings.KEYCLOAK_REALM,
                callback_uri=settings.KEYCLOAK_CALLBACK_URI,
                timeout=60,  # Increased from default 10s to handle production latency
            )

            logger.info(
                "Keycloak client initialized successfully",
                extra={
                    "server_url": settings.KEYCLOAK_SERVER_URL,
                    "realm": settings.KEYCLOAK_REALM,
                    "client_id": settings.KEYCLOAK_CLIENT_ID,
                    "attempt": attempt,
                },
            )

            return idp_instance

        except (Timeout, ConnectionError, RequestException) as e:
            if attempt == max_retries:
                logger.critical(
                    "Failed to initialize Keycloak client after all retries",
                    exc_info=True,
                    extra={
                        "server_url": settings.KEYCLOAK_SERVER_URL,
                        "realm": settings.KEYCLOAK_REALM,
                        "max_retries": max_retries,
                        "error": str(e),
                    },
                )
                raise ConnectionError(
                    f"Failed to connect to Keycloak at {settings.KEYCLOAK_SERVER_URL} "
                    f"after {max_retries} attempts. Error: {str(e)}"
                ) from e

            logger.warning(
                f"Keycloak connection failed (attempt {attempt}/{max_retries}), retrying in {backoff}s",
                extra={
                    "server_url": settings.KEYCLOAK_SERVER_URL,
                    "backoff_seconds": backoff,
                    "error": str(e),
                },
            )

            time.sleep(backoff)
            backoff *= 2  # Exponential backoff: 2s, 4s, 8s, 16s, 32s

    # This should never be reached, but satisfies type checker
    raise ConnectionError("Unexpected error in Keycloak initialization")


# Initialize FastAPIKeycloak client with retry logic
# This will be used across all routers for authentication and authorization
idp = _initialize_keycloak_with_retry()
