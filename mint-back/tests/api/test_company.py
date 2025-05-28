import pytest
from fastapi import status
from unittest.mock import AsyncMock, patch

def test_create_company(client):
    company_data = {
        "name": "Test Company",
        "website": "https://testcompany.com"
    }
    response = client.post("/companies/", json=company_data)
    assert response.status_code == status.HTTP_200_OK
    data = response.json()
    assert data["name"] == company_data["name"]
    assert data["website"] == company_data["website"]
    assert "id" in data

def test_get_companies(client):
    # First create a company
    company_data = {
        "name": "Test Company",
        "website": "https://testcompany.com"
    }
    client.post("/companies/", json=company_data)
    
    # Then get all companies
    response = client.get("/companies/")
    assert response.status_code == status.HTTP_200_OK
    data = response.json()
    assert isinstance(data, list)
    assert len(data) > 0
    assert data[0]["name"] == company_data["name"]

def test_get_company_by_id(client):
    # First create a company
    company_data = {
        "name": "Test Company",
        "website": "https://testcompany.com"
    }
    create_response = client.post("/companies/", json=company_data)
    company_id = create_response.json()["id"]
    
    # Then get the company by ID
    response = client.get(f"/companies/{company_id}")
    assert response.status_code == status.HTTP_200_OK
    data = response.json()
    assert data["name"] == company_data["name"]
    assert data["website"] == company_data["website"]

def test_get_company_by_name(client):
    # First create a company
    company_data = {
        "name": "Test Company",
        "website": "https://testcompany.com"
    }
    client.post("/companies/", json=company_data)
    
    # Then get the company by name
    response = client.get("/companies/by-name/Test Company")
    assert response.status_code == status.HTTP_200_OK
    data = response.json()
    assert data["name"] == company_data["name"]
    assert data["website"] == company_data["website"]

def test_update_company(client):
    # First create a company
    company_data = {
        "name": "Test Company",
        "website": "https://testcompany.com"
    }
    create_response = client.post("/companies/", json=company_data)
    company_id = create_response.json()["id"]
    
    # Then update the company
    update_data = {
        "name": "Updated Company",
        "website": "https://updatedcompany.com"
    }
    response = client.put(f"/companies/{company_id}", json=update_data)
    assert response.status_code == status.HTTP_200_OK
    data = response.json()
    assert data["name"] == update_data["name"]
    assert data["website"] == update_data["website"]

def test_delete_company(client):
    # First create a company
    company_data = {
        "name": "Test Company",
        "website": "https://testcompany.com"
    }
    create_response = client.post("/companies/", json=company_data)
    company_id = create_response.json()["id"]
    
    # Then delete the company
    response = client.delete(f"/companies/{company_id}")
    assert response.status_code == status.HTTP_204_NO_CONTENT
    
    # Verify the company is deleted
    get_response = client.get(f"/companies/{company_id}")
    assert get_response.status_code == status.HTTP_404_NOT_FOUND

@pytest.mark.asyncio
async def test_search_company(client):
    # Mock the N8nClient.trigger_workflow to avoid calling the real n8n service
    with patch('app.domain.services.company_service.N8nClient.trigger_workflow', new_callable=AsyncMock) as mock_trigger:
        mock_trigger.return_value = {"status": "success"}
        company_data = {
            "name": "Test Company",
            "website": "https://testcompany.com"
        }
        response = client.post("/companies/search", json=company_data)
        assert response.status_code == status.HTTP_200_OK
        assert mock_trigger.call_count == 8
        data = response.json()
        assert "message" in data
        assert "company_id" in data
        assert "Search initiated for company" in data["message"]

def test_start_query_invalid_type(client):
    # First create a company
    company_data = {
        "name": "Test Company",
        "website": "https://testcompany.com"
    }
    create_response = client.post("/companies/", json=company_data)
    company_id = create_response.json()["id"]
    
    # Try to start an invalid query type
    response = client.post(f"/companies/{company_id}/query/invalid_type")
    assert response.status_code == status.HTTP_400_BAD_REQUEST
    assert "Invalid query type" in response.json()["detail"]

def test_get_company_not_found(client):
    """Test getting a non-existent company by ID"""
    response = client.get("/companies/999999")
    assert response.status_code == status.HTTP_404_NOT_FOUND
    assert "not found" in response.json()["detail"]

def test_get_company_by_name_not_found(client):
    """Test getting a non-existent company by name"""
    response = client.get("/companies/by-name/NonExistentCompany")
    assert response.status_code == status.HTTP_404_NOT_FOUND
    assert "not found" in response.json()["detail"]

def test_update_company_not_found(client):
    """Test updating a non-existent company"""
    update_data = {
        "name": "Updated Company",
        "website": "https://updatedcompany.com"
    }
    response = client.put("/companies/999999", json=update_data)
    assert response.status_code == status.HTTP_404_NOT_FOUND
    assert "not found" in response.json()["detail"]

def test_delete_company_not_found(client):
    """Test deleting a non-existent company"""
    response = client.delete("/companies/999999")
    assert response.status_code == status.HTTP_404_NOT_FOUND
    assert "not found" in response.json()["detail"]

def test_update_company_partial(client):
    """Test updating a company with partial data"""
    # First create a company
    company_data = {
        "name": "Test Company",
        "website": "https://testcompany.com"
    }
    create_response = client.post("/companies/", json=company_data)
    company_id = create_response.json()["id"]
    
    # Update only the name
    update_data = {
        "name": "Updated Company"
    }
    response = client.put(f"/companies/{company_id}", json=update_data)
    assert response.status_code == status.HTTP_200_OK
    data = response.json()
    assert data["name"] == update_data["name"]
    assert data["website"] == company_data["website"]  # Should remain unchanged

@pytest.mark.asyncio
async def test_start_query_valid_types(client):
    """Test starting queries with all valid query types"""
    # First create a company
    company_data = {
        "name": "Test Company",
        "website": "https://testcompany.com"
    }
    create_response = client.post("/companies/", json=company_data)
    company_id = create_response.json()["id"]
    
    # Create tasks for all query types
    valid_query_types = ["profile", "digital", "timeline", "products", "press", "csr", "jobs", "team"]
    
    with patch('app.domain.services.company_service.N8nClient.trigger_workflow', new_callable=AsyncMock) as mock_trigger:
        mock_trigger.return_value = {"success": True}
        
        # First create all tasks by initiating a company search
        search_response = client.post("/companies/search", json=company_data)
        assert search_response.status_code == status.HTTP_200_OK
        
        # Now test each query type
        for query_type in valid_query_types:
            response = client.post(f"/companies/{company_id}/query/{query_type}")
            assert response.status_code == status.HTTP_200_OK
            data = response.json()
            assert "message" in data
            assert "result" in data
            assert data["result"]["success"] is True


def test_update_company_complex_fields(client):
    """Test updating a company with complex fields"""
    # First create a company
    company_data = {
        "name": "Test Company",
        "website": "https://testcompany.com"
    }
    create_response = client.post("/companies/", json=company_data)
    company_id = create_response.json()["id"]
    
    # Update with complex fields
    update_data = {
        "profile": {"key": "value"},
        "digital": {"social": "data"},
        "timeline": {"events": []},
        "products": {"items": []},
        "jobs": {"positions": []},
        "csr": {"initiatives": []},
        "press": {"releases": []},
        "team": [{"name": "John Doe", "role": "CEO"}]  # Changed to a list of dictionaries
    }
    response = client.put(f"/companies/{company_id}", json=update_data)
    assert response.status_code == status.HTTP_200_OK
    data = response.json()
    
    # Verify all complex fields were updated
    assert data["profile"] == update_data["profile"]
    assert data["digital"] == update_data["digital"]
    assert data["timeline"] == update_data["timeline"]
    assert data["products"] == update_data["products"]
    assert data["jobs"] == update_data["jobs"]
    assert data["csr"] == update_data["csr"]
    assert data["press"] == update_data["press"]
    assert data["team"] == update_data["team"]
    
    # Verify basic fields remain unchanged
    assert data["name"] == company_data["name"]
    assert data["website"] == company_data["website"] 