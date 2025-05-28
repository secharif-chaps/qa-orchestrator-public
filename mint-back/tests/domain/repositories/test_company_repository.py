import pytest
from typing import List, Optional, Dict, Any
from app.domain.entities.company import Company
from app.domain.repositories.company_repository import CompanyRepository

def test_company_repository_interface():
    """Test that CompanyRepository is an abstract base class with required methods"""
    # Verify that CompanyRepository is an abstract base class
    assert hasattr(CompanyRepository, '__abstractmethods__')
    
    # Get all abstract methods
    abstract_methods = CompanyRepository.__abstractmethods__
    
    # Verify all required methods are defined as abstract
    required_methods = {
        'get_by_id',
        'get_by_name',
        'get_all',
        'create',
        'update',
        'delete',
        'update_company_data'
    }
    assert all(method in abstract_methods for method in required_methods)
    
    # Verify method signatures
    assert CompanyRepository.get_by_id.__annotations__ == {'company_id': int, 'return': Optional[Company]}
    assert CompanyRepository.get_by_name.__annotations__ == {'name': str, 'return': Optional[Company]}
    assert CompanyRepository.get_all.__annotations__ == {'return': List[Company]}
    assert CompanyRepository.create.__annotations__ == {'name': str, 'website': str, 'return': Company}
    assert CompanyRepository.update.__annotations__ == {'company': Company, 'return': Company}
    assert CompanyRepository.delete.__annotations__ == {'company_id': int, 'return': bool}
    assert CompanyRepository.update_company_data.__annotations__ == {
        'company_id': int,
        'query_type': str,
        'data': Dict[str, Any],
        'return': Company
    }

def test_company_repository_instantiation():
    """Test that CompanyRepository cannot be instantiated directly"""
    with pytest.raises(TypeError):
        CompanyRepository()

class TestCompanyRepository(CompanyRepository):
    """Test implementation of CompanyRepository"""
    def get_by_id(self, company_id: int) -> Optional[Company]:
        return None
    
    def get_by_name(self, name: str) -> Optional[Company]:
        return None
    
    def get_all(self) -> List[Company]:
        return []
    
    def create(self, name: str, website: str) -> Company:
        return Company(name=name, website=website)
    
    def update(self, company: Company) -> Company:
        return company
    
    def delete(self, company_id: int) -> bool:
        return True
    
    def update_company_data(self, company_id: int, query_type: str, data: Dict[str, Any]) -> Company:
        return Company(name="Test", website="https://test.com")

def test_company_repository_implementation():
    """Test that a concrete implementation can be instantiated"""
    repo = TestCompanyRepository()
    assert isinstance(repo, CompanyRepository)
    
    # Test that all methods can be called
    assert repo.get_by_id(1) is None
    assert repo.get_by_name("test") is None
    assert repo.get_all() == []
    company = repo.create("Test", "https://test.com")
    assert isinstance(company, Company)
    assert repo.update(company) == company
    assert repo.delete(1) is True
    assert isinstance(repo.update_company_data(1, "profile", {}), Company) 