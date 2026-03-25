from fastapi import Depends
from sqlalchemy.orm import Session

from app.database import get_db
from app.services.company import CompanyService
from app.services.global_service_client import GlobalServiceClient
from app.services.token_manager import TokenManager


# Service dependencies
def get_company_service(db: Session = Depends(get_db)) -> CompanyService:
    return CompanyService(db=db)


def get_token_manager(db: Session = Depends(get_db)) -> TokenManager:
    return TokenManager(db=db)


def get_global_service_client() -> GlobalServiceClient:
    """Get a GlobalServiceClient instance for calling global-service APIs."""
    return GlobalServiceClient()
