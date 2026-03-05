from typing import Optional

from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from sqlalchemy.orm import Session

from app.database import get_db
from app.schemas.user import TokenData
from app.services.auth import keycloak_service
from app.services.company import CompanyService
from app.services.global_service_client import GlobalServiceClient
from app.services.token_manager import TokenManager


# Service dependencies
def get_company_service(
    db: Session = Depends(get_db)
) -> CompanyService:
    return CompanyService(db=db)

def get_token_manager(db: Session = Depends(get_db)) -> TokenManager:
    return TokenManager(db=db)

def get_global_service_client() -> GlobalServiceClient:
    """Get a GlobalServiceClient instance for calling global-service APIs."""
    return GlobalServiceClient()

# Authentication dependencies
security = HTTPBearer()

async def get_current_user(credentials: HTTPAuthorizationCredentials = Depends(security)) -> TokenData:
    """
    Dependency to get current authenticated user from JWT token
    """
    import logging
    logger = logging.getLogger(__name__)

    token = credentials.credentials

    try:
        token_data = await keycloak_service.verify_token(token)

        if not token_data:
            logger.debug("🔐 Auth failed - Invalid token")
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid authentication credentials",
                headers={"WWW-Authenticate": "Bearer"},
            )

        return token_data

    except Exception as e:
        logger.debug(f"🔐 Auth error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Could not validate credentials",
            headers={"WWW-Authenticate": "Bearer"},
        )

def require_roles(required_roles: list[str]):
    """
    Dependency factory to require specific roles
    """
    async def check_roles(current_user: TokenData = Depends(get_current_user)) -> TokenData:
        if not current_user.roles:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Insufficient permissions"
            )

        user_roles = set(current_user.roles)
        required_roles_set = set(required_roles)

        if not required_roles_set.intersection(user_roles):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Insufficient permissions"
            )

        return current_user

    return check_roles

def require_admin():
    """
    Dependency to require admin role
    """
    return require_roles(["admin"])

async def get_optional_user(credentials: Optional[HTTPAuthorizationCredentials] = Depends(HTTPBearer(auto_error=False))) -> Optional[TokenData]:
    """
    Dependency to get current user if token is provided, otherwise None
    """
    if not credentials:
        return None

    token = credentials.credentials
    return await keycloak_service.verify_token(token)
