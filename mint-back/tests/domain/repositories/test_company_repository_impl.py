import pytest
from unittest.mock import Mock, patch
from sqlalchemy.orm import Session
from app.domain.entities.company import Company
from app.infrastructure.database.repositories.company_repository_impl import SQLAlchemyCompanyRepository

@pytest.fixture
def mock_session():
    """Create a mock database session"""
    session = Mock(spec=Session)
    session.query = Mock()
    return session

@pytest.fixture
def repository(mock_session):
    """Create a repository instance with the mock session"""
    return SQLAlchemyCompanyRepository(db_session=mock_session)

def test_get_by_id(repository, mock_session):
    """Test getting a company by ID"""
    # Setup mock company
    company = Company(name="Test Company", website="https://test.com")
    mock_query = Mock()
    mock_query.filter.return_value.first.return_value = company
    mock_session.query.return_value = mock_query
    
    # Test getting the company
    result = repository.get_by_id(1)
    assert result is not None
    assert result.name == "Test Company"
    assert result.website == "https://test.com"
    
    # Verify mock calls
    mock_session.query.assert_called_once_with(Company)
    mock_query.filter.assert_called_once()

def test_get_by_name(repository, mock_session):
    """Test getting a company by name"""
    # Setup mock company
    company = Company(name="Test Company", website="https://test.com")
    mock_query = Mock()
    mock_query.filter.return_value.first.return_value = company
    mock_session.query.return_value = mock_query
    
    # Test getting the company
    result = repository.get_by_name("Test Company")
    assert result is not None
    assert result.name == "Test Company"
    assert result.website == "https://test.com"
    
    # Verify mock calls
    mock_session.query.assert_called_once_with(Company)
    mock_query.filter.assert_called_once()

def test_get_all(repository, mock_session):
    """Test getting all companies"""
    # Setup mock companies
    companies = [
        Company(name="Company 1", website="https://company1.com"),
        Company(name="Company 2", website="https://company2.com")
    ]
    mock_query = Mock()
    mock_query.all.return_value = companies
    mock_session.query.return_value = mock_query
    
    # Test getting all companies
    results = repository.get_all()
    assert len(results) == 2
    assert any(c.name == "Company 1" for c in results)
    assert any(c.name == "Company 2" for c in results)
    
    # Verify mock calls
    mock_session.query.assert_called_once_with(Company)
    mock_query.all.assert_called_once()

def test_create(repository, mock_session):
    """Test creating a new company"""
    # Setup mock session
    mock_session.add = Mock()
    mock_session.commit = Mock()
    mock_session.refresh = Mock()
    
    # Create a company
    company = repository.create("New Company", "https://newcompany.com")
    
    # Verify the company was created
    assert company.name == "New Company"
    assert company.website == "https://newcompany.com"
    
    # Verify mock calls
    mock_session.add.assert_called_once()
    mock_session.commit.assert_called_once()
    mock_session.refresh.assert_called_once()

def test_update(repository, mock_session):
    """Test updating a company"""
    # Setup mock session
    mock_session.add = Mock()
    mock_session.commit = Mock()
    mock_session.refresh = Mock()
    
    # Create and update a company
    company = Company(name="Test Company", website="https://test.com")
    company.name = "Updated Company"
    company.website = "https://updated.com"
    
    updated = repository.update(company)
    
    # Verify the update
    assert updated.name == "Updated Company"
    assert updated.website == "https://updated.com"
    
    # Verify mock calls
    mock_session.add.assert_called_once_with(company)
    mock_session.commit.assert_called_once()
    mock_session.refresh.assert_called_once_with(company)

def test_delete(repository, mock_session):
    """Test deleting a company"""
    # Setup mock company
    company = Company(name="Test Company", website="https://test.com")
    mock_query = Mock()
    mock_query.filter.return_value.first.return_value = company
    mock_session.query.return_value = mock_query
    mock_session.delete = Mock()
    mock_session.commit = Mock()
    
    # Delete the company
    result = repository.delete(1)
    assert result is True
    
    # Verify mock calls
    mock_session.query.assert_called_once_with(Company)
    mock_session.delete.assert_called_once_with(company)
    mock_session.commit.assert_called_once()

def test_delete_not_found(repository, mock_session):
    """Test deleting a non-existent company"""
    # Setup mock query to return None
    mock_query = Mock()
    mock_query.filter.return_value.first.return_value = None
    mock_session.query.return_value = mock_query
    
    # Delete the company
    result = repository.delete(1)
    assert result is False
    
    # Verify mock calls
    mock_session.query.assert_called_once_with(Company)
    mock_session.delete.assert_not_called()
    mock_session.commit.assert_not_called()

def test_update_company_data(repository, mock_session):
    """Test updating company data from n8n workflow results"""
    # Setup mock company
    company = Company(name="Test Company", website="https://test.com")
    mock_query = Mock()
    mock_query.filter.return_value.first.return_value = company
    mock_session.query.return_value = mock_query
    mock_session.add = Mock()
    mock_session.commit = Mock()
    mock_session.refresh = Mock()
    
    # Update company data
    data = {
        "profile": {"key": "value"},
        "digital": {"social": "data"},
        "timeline": {"events": []},
        "products": {"items": []},
        "jobs": {"positions": []},
        "csr": {"initiatives": []},
        "press": {"releases": []},
        "team": [{"name": "John Doe", "role": "CEO"}]
    }
    
    # Test updating each type of data
    for query_type in ["profile", "digital", "timeline", "products", "jobs", "csr", "press", "team"]:
        updated = repository.update_company_data(1, query_type, data)
        assert getattr(updated, query_type) == data[query_type]
        
        # Verify mock calls
        mock_session.add.assert_called_with(company)
        mock_session.commit.assert_called()
        mock_session.refresh.assert_called_with(company)

def test_update_company_data_not_found(repository, mock_session):
    """Test updating data for a non-existent company"""
    # Setup mock query to return None
    mock_query = Mock()
    mock_query.filter.return_value.first.return_value = None
    mock_session.query.return_value = mock_query
    
    # Test updating data
    with pytest.raises(ValueError, match="Company with ID 999 not found"):
        repository.update_company_data(999, "profile", {"key": "value"})
    
    # Verify mock calls
    mock_session.query.assert_called_once_with(Company)
    mock_session.add.assert_not_called()
    mock_session.commit.assert_not_called() 