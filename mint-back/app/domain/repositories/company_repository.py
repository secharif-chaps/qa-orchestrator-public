from abc import ABC, abstractmethod
from typing import List, Optional, Dict, Any
from app.domain.entities.company import Company

class CompanyRepository(ABC):
    """Repository interface for Company entity operations"""
    
    @abstractmethod
    def get_by_id(self, company_id: int) -> Optional[Company]:
        """Get a company by its ID"""
        pass
    
    @abstractmethod
    def get_by_name(self, name: str) -> Optional[Company]:
        """Get a company by its name"""
        pass
    
    @abstractmethod
    def get_all(self) -> List[Company]:
        """Get all companies"""
        pass
    
    @abstractmethod
    def create(self, name: str, website: str) -> Company:
        """Create a new company"""
        pass
    
    @abstractmethod
    def update(self, company: Company) -> Company:
        """Update an existing company"""
        pass
    
    @abstractmethod
    def delete(self, company_id: int) -> bool:
        """Delete a company by its ID"""
        pass
    
    @abstractmethod
    def update_company_data(self, company_id: int, query_type: str, data: Dict[str, Any]) -> Company:
        """Update specific company data from n8n workflow results"""
        pass 