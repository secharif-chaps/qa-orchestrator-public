from fastapi import Depends
from sqlalchemy.orm import Session

from app.infrastructure.database.database import get_db
from app.infrastructure.database.repositories.company_repository_impl import SQLAlchemyCompanyRepository
from app.domain.repositories.company_repository import CompanyRepository
from app.domain.services.company_service import CompanyService
from app.infrastructure.n8n.client import N8nClient

# Repository dependencies
def get_company_repository(db: Session = Depends(get_db)) -> CompanyRepository:
    return SQLAlchemyCompanyRepository(db_session=db)

# Service dependencies
def get_n8n_client() -> N8nClient:
    return N8nClient()

def get_company_service(
    repository: CompanyRepository = Depends(get_company_repository),
    n8n_client: N8nClient = Depends(get_n8n_client)
) -> CompanyService:
    return CompanyService(repository=repository, n8n_client=n8n_client) 