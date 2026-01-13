# app/core/dependencies.py
"""
Authentication dependencies for FastAPI endpoints.

Provides dependencies for:
1. User JWT authentication (frontend)
2. Client credentials authentication (service-to-service)
3. Organization context extraction
"""

from typing import Dict, Any, Optional
from fastapi import Depends, HTTPException, status, Header
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials

from app.core.keycloak import idp, validate_client_token
from app.core.client_auth import introspect_token, ClientAuthError
from app.core.organization import OrganizationContext, get_user_organization
from app.core.logging import get_logger

logger = get_logger(__name__)

security = HTTPBearer(auto_error=False)


# User JWT Authentication (for frontend)
async def get_current_user(
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security)
):
    """
    Dependency for user JWT authentication.
    
    Validates Keycloak JWT tokens for frontend users using fastapi-keycloak.
    
    Usage:
        @app.get("/protected")
        async def protected_endpoint(user = Depends(get_current_user)):
            return {"user": user.preferred_username}
    """
    if not credentials:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Not authenticated",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    try:
        # Use fastapi-keycloak to validate user JWT
        user = idp.get_current_user(credentials.credentials)
        return user
    except Exception as e:
        logger.warning(f"JWT validation failed: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid authentication token",
            headers={"WWW-Authenticate": "Bearer"},
        )


# Client Credentials Authentication (for service-to-service)
async def get_service_client(
    authorization: Optional[str] = Header(None, alias="Authorization")
) -> Dict[str, Any]:
    """
    Dependency for client credentials authentication.
    
    Validates OAuth2 client credentials for service-to-service communication.
    
    Usage:
        @app.get("/api/service-endpoint")
        async def service_endpoint(
            client: Dict = Depends(get_service_client)
        ):
            return {"client_id": client["client_id"]}
    """
    if not authorization:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing authorization header",
        )
    
    try:
        # Validate client credentials token
        client_info = await introspect_token(authorization)
        
        # Extract and return client information
        return {
            "client_id": client_info.get("client_id"),
            "scope": client_info.get("scope", "").split(),
            "token_type": client_info.get("token_type", "Bearer"),
            "active": client_info.get("active", False),
        }
        
    except ClientAuthError as e:
        logger.warning(f"Client authentication failed: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=str(e),
        )
    except Exception as e:
        logger.error(f"Unexpected error during client auth: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal authentication error",
        )

# Combined authentication (accepts either user or client)
async def get_authenticated_entity(
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security)
) -> Dict[str, Any]:
    """
    Accepts either user JWT or client credentials.
    
    Tries user JWT first, then falls back to client credentials.
    Returns a dictionary with authentication type and entity info.
    """
    if not credentials:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Not authenticated",
        )
    
    token = credentials.credentials
    
    try:
        # Try as user JWT
        user = idp.get_current_user(token)
        return {
            "type": "user",
            "user_id": user.sub,
            "username": user.preferred_username,
            "email": user.email,
            "organization": user.organization if hasattr(user, 'organization') else None,
        }
    except Exception:
        try:
            # Try as client credentials
            client_info = await introspect_token(token)
            return {
                "type": "client",
                "client_id": client_info.get("client_id"),
                "scope": client_info.get("scope", "").split(),
                "active": client_info.get("active", False),
            }
        except ClientAuthError:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid authentication token",
            )

# Re-export organization dependencies
# Directly use the function from organization.py
get_organization_context = get_user_organization