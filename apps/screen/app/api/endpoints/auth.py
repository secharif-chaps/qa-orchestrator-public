import logging
from typing import Any

from fastapi import APIRouter, Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.schemas.user import LoginRequest, RefreshTokenRequest, Token
from app.services.auth import keycloak_service

# Configure logging
logger = logging.getLogger(__name__)

router = APIRouter(prefix="/auth", tags=["authentication"])
security = HTTPBearer()


@router.post("/login", response_model=Token)
async def login(login_request: LoginRequest) -> Token:
    """
    Authenticate user with Keycloak and return tokens
    """
    logger.info(f"Login attempt for username: {login_request.username}")
    
    try:
        token_data = await keycloak_service.authenticate_user(
            login_request.username, 
            login_request.password
        )
        
        logger.info(f"Keycloak authentication response received for user: {login_request.username}")
        logger.debug(f"Token data type: {type(token_data)}")
        
        if token_data:
            logger.info(f"Authentication successful for user: {login_request.username}")
            logger.debug(f"Token data keys: {list(token_data.keys()) if isinstance(token_data, dict) else 'Not a dict'}")
        else:
            logger.warning(f"Authentication failed for user: {login_request.username} - token_data is None/False")
        
    except Exception as e:
        logger.error(f"Exception during authentication for user {login_request.username}: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Authentication service error"
        )
    
    if not token_data:
        logger.warning(f"Invalid credentials for user: {login_request.username}")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid credentials",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    logger.info(f"Returning token for user: {login_request.username}")
    return Token(
        access_token=token_data["access_token"],
        refresh_token=token_data.get("refresh_token"),
        token_type="bearer",
        expires_in=token_data.get("expires_in", 300)
    )


@router.post("/refresh", response_model=Token)
async def refresh_token(refresh_request: RefreshTokenRequest) -> Token:
    """
    Refresh access token using refresh token
    """
    token_data = await keycloak_service.refresh_token(refresh_request.refresh_token)
    
    if not token_data:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid refresh token",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    return Token(
        access_token=token_data["access_token"],
        refresh_token=token_data.get("refresh_token"),
        token_type="bearer",
        expires_in=token_data.get("expires_in", 300)
    )


@router.post("/logout")
async def logout(refresh_request: RefreshTokenRequest) -> dict[str, str]:
    """
    Logout user by invalidating refresh token
    """
    success = await keycloak_service.logout(refresh_request.refresh_token)
    
    if not success:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Failed to logout"
        )
    
    return {"message": "Successfully logged out"}


@router.get("/me", response_model=dict[str, Any])
async def get_current_user(credentials: HTTPAuthorizationCredentials = Depends(security)) -> dict[str, Any]:
    """
    Get current user information from access token
    """
    token = credentials.credentials
    user_info = await keycloak_service.get_user_info(token)
    
    if not user_info:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid token",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    return user_info


@router.post("/verify")
async def verify_token(credentials: HTTPAuthorizationCredentials = Depends(security)) -> dict[str, Any]:
    """
    Verify if the provided token is valid
    """
    token = credentials.credentials
    token_data = await keycloak_service.verify_token(token)
    
    if not token_data:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid token",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    return {
        "valid": True,
        "username": token_data.username,
        "sub": token_data.sub,
        "roles": token_data.roles
    }


@router.post("/introspect")
async def introspect_token(credentials: HTTPAuthorizationCredentials = Depends(security)) -> dict[str, Any]:
    """
    Introspect token (server-side validation with detailed info)
    """
    token = credentials.credentials
    token_info = await keycloak_service.introspect_token(token)
    
    if not token_info:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or inactive token",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    return token_info