from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from sqlalchemy.orm import Session
from typing import List, Optional

from app.database import get_db
from app.infrastructure.database.repositories.company_repository_impl import SQLAlchemyCompanyRepository
from app.services.company import CompanyService
from app.services.token_manager import TokenManager
from app.services.auth import keycloak_service
from app.schemas.user import TokenData

# Service dependencies
def get_company_service(
    db: Session = Depends(get_db)
) -> CompanyService:
    return CompanyService(db=db)

def get_token_manager(db: Session = Depends(get_db)) -> TokenManager:
    return TokenManager(db=db)

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
            print(f"🔐 Auth failed - Invalid token")
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid authentication credentials",
                headers={"WWW-Authenticate": "Bearer"},
            )
        
        return token_data
        
    except Exception as e:
        print(f"🔐 Auth error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Could not validate credentials",
            headers={"WWW-Authenticate": "Bearer"},
        )

def require_roles(required_roles: List[str]):
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