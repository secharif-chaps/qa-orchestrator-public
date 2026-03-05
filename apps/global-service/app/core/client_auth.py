# app/core/client_auth.py
"""
Client credentials validation for service-to-service authentication.

This module handles OAuth2 client credentials flow for service-to-service
communication, using Keycloak token introspection with caching.
"""

import logging
from datetime import UTC, datetime
from typing import Any

import httpx
from cachetools import TTLCache

from app.core.config import settings

logger = logging.getLogger(__name__)

# Cache introspection results for 60 seconds (TTL)
_introspection_cache = TTLCache(maxsize=100, ttl=60)


class ClientAuthError(Exception):
    """Client authentication error."""
    pass


async def introspect_token(token: str) -> dict:
    """
    Introspect a client credentials token against Keycloak.
    
    Args:
        token: OAuth2 token string (with or without "Bearer " prefix)
        
    Returns:
        Token introspection payload
        
    Raises:
        ClientAuthError: If token is invalid or introspection fails
    """
    if not token:
        raise ClientAuthError("Missing authorization token")
    
    # Remove "Bearer " prefix if present
    if token.startswith("Bearer "):
        token = token[7:]
    
    # Check cache first
    if token in _introspection_cache:
        logger.debug("Token introspection result retrieved from cache")
        return _introspection_cache[token]
    
    try:
        # Make introspection request to Keycloak
        async with httpx.AsyncClient(timeout=10.0) as client:
            response = await client.post(
                f"{settings.KEYCLOAK_SERVER_URL}/realms/"
                f"{settings.KEYCLOAK_REALM}/protocol/openid-connect/token/introspect",
                data={"token": token, "client_id": settings.KEYCLOAK_CLIENT_ID},
                auth=(settings.KEYCLOAK_CLIENT_ID, settings.KEYCLOAK_CLIENT_SECRET),
                headers={"Content-Type": "application/x-www-form-urlencoded"}
            )
            
            response.raise_for_status()
            result = await response.json()
            
    except httpx.HTTPError as e:
        logger.error(f"Keycloak introspection failed: {str(e)}")
        raise ClientAuthError("Authentication service unavailable")
    
    # Validate token is active
    if not result.get("active", False):
        logger.warning(f"Inactive token for client: {result.get('client_id')}")
        raise ClientAuthError("Inactive or invalid token")
    
    # Validate token type (should be Bearer for client credentials)
    token_type = result.get("token_type", "").lower()
    if token_type != "bearer":
        logger.warning(f"Unexpected token type: {token_type}")
    
    # Check expiration
    exp = result.get("exp")
    if exp:
        exp_time = datetime.fromtimestamp(exp, tz=UTC)
        if exp_time < datetime.now(UTC):
            logger.warning("Token expired according to introspection")
            raise ClientAuthError("Token expired")
    
    # Cache the result
    _introspection_cache[token] = result
    logger.debug(f"Token introspection successful for client: {result.get('client_id')}")
    
    return result


async def get_client_info(token: str) -> dict[str, Any]:
    """
    Get client information from validated token.
    
    Args:
        token: OAuth2 token string
        
    Returns:
        Client information dictionary
    """
    result = await introspect_token(token)
    
    return {
        "client_id": result.get("client_id"),
        "scope": result.get("scope", "").split(),
        "active": result.get("active", False),
        "token_type": result.get("token_type", "Bearer"),
        "exp": result.get("exp"),
    }