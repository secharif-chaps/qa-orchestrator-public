from typing import List, Optional, Tuple
from sqlalchemy.orm import Session
from sqlalchemy import asc, desc

from app.models.company import Company
from app.schemas.pagination import PaginationParams

class SQLAlchemyCompanyRepository:
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
    
    
    def get_paginated(self, pagination_params: PaginationParams, organization_id: Optional[str] = None, name_filter: Optional[str] = None, include_archived: bool = False) -> Tuple[List[Company], int]:
        """Get paginated list of companies with sorting and filtering"""
        query = self.db_session.query(Company)

        # Filter out soft-deleted unless explicitly included
        if not include_archived:
            query = query.filter(not Company.is_deleted)

        # Filter by organization if provided
        if organization_id:
            query = query.filter(Company.organization_id == organization_id)
        
        # Filter by name if provided (case-insensitive partial match)
        if name_filter:
            query = query.filter(Company.name.ilike(f"%{name_filter}%"))
        
        # Apply sorting
        if pagination_params.sort:
            sort_field = getattr(Company, pagination_params.sort, None)
            if sort_field is not None:
                if pagination_params.order.value == "asc":
                    query = query.order_by(asc(sort_field))
                else:
                    query = query.order_by(desc(sort_field))
            else:
                # Default sort by created_at DESC if invalid sort field
                query = query.order_by(desc(Company.created_at))
        else:
            # Default sort by created_at DESC
            query = query.order_by(desc(Company.created_at))
        
        # Get total count before applying pagination
        total_count = query.count()
        
        # Apply pagination
        companies = query.offset(pagination_params.get_offset()).limit(pagination_params.get_limit()).all()
        
        # Single result log
        filter_info = f" (filtered by '{name_filter}')" if name_filter else ""
        logger.info(f"📊 Found {total_count} companies{filter_info}, returning {len(companies)} for page {pagination_params.page}")
        
        return companies, total_count 