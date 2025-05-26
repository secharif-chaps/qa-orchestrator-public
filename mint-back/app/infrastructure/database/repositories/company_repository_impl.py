from typing import List, Optional, Dict, Any
from sqlalchemy.orm import Session

from app.domain.entities.company import Company
from app.domain.repositories.company_repository import CompanyRepository

class SQLAlchemyCompanyRepository(CompanyRepository):
    """SQLAlchemy implementation of the Company repository"""
    
    def __init__(self, db_session: Session):
        self.db_session = db_session
    
    def get_by_id(self, company_id: int) -> Optional[Company]:
        return self.db_session.query(Company).filter(Company.id == company_id).first()
    
    def get_by_name(self, name: str) -> Optional[Company]:
        return self.db_session.query(Company).filter(Company.name == name).first()
    
    def get_all(self) -> List[Company]:
        return self.db_session.query(Company).all()
    
    def create(self, name: str, website: str) -> Company:
        company = Company(name=name, website=website)
        self.db_session.add(company)
        self.db_session.commit()
        self.db_session.refresh(company)
        return company
    
    def update(self, company: Company) -> Company:
        self.db_session.add(company)
        self.db_session.commit()
        self.db_session.refresh(company)
        return company
    
    def delete(self, company_id: int) -> bool:
        company = self.get_by_id(company_id)
        if not company:
            return False
        
        self.db_session.delete(company)
        self.db_session.commit()
        return True
    
    def update_company_data(self, company_id: int, query_type: str, data: Dict[str, Any]) -> Company:
        company = self.get_by_id(company_id)
        if not company:
            raise ValueError(f"Company with ID {company_id} not found")
        
        company.update_from_n8n(query_type, data)
        self.db_session.add(company)
        self.db_session.commit()
        self.db_session.refresh(company)
        return company 