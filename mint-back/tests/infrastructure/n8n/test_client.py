import pytest
import httpx
from unittest.mock import patch, AsyncMock
from app.infrastructure.n8n.client import N8nClient

@pytest.fixture
def n8n_client():
    return N8nClient(base_url="http://test-n8n.com", webhook_id="test-webhook")


@pytest.mark.asyncio
async def test_trigger_workflow_timeout(n8n_client):
    """Test workflow trigger timeout"""
    with patch('httpx.AsyncClient.post', new_callable=AsyncMock) as mock_post:
        mock_post.side_effect = httpx.TimeoutException("Request timed out")
        
        with pytest.raises(Exception) as exc_info:
            await n8n_client.trigger_workflow(
                company="Test Company",
                website="https://test.com",
                query="profile"
            )
        
        assert "Request to n8n timed out after 5 minutes" in str(exc_info.value)


@pytest.mark.asyncio
async def test_trigger_workflow_generic_error(n8n_client):
    """Test workflow trigger generic error"""
    with patch('httpx.AsyncClient.post', new_callable=AsyncMock) as mock_post:
        mock_post.side_effect = Exception("Generic error")
        
        with pytest.raises(Exception) as exc_info:
            await n8n_client.trigger_workflow(
                company="Test Company",
                website="https://test.com",
                query="profile"
            )
        
        assert "Error triggering n8n workflow" in str(exc_info.value)

@pytest.mark.asyncio
async def test_check_workflow_status(n8n_client):
    """Test checking workflow status"""
    mock_response = AsyncMock()
    mock_response.status_code = 200
    mock_response.json = AsyncMock(return_value={"status": "completed"})
    mock_response.raise_for_status = AsyncMock()
    
    with patch('httpx.AsyncClient.get', new_callable=AsyncMock) as mock_get:
        mock_get.return_value = mock_response
        
        result = await n8n_client.check_workflow_status("test-execution-id")
        
        assert result == {"status": "completed"}
        mock_get.assert_called_once_with(
            "http://test-n8n.com/executions/test-execution-id"
        )
        mock_response.raise_for_status.assert_called_once()
        mock_response.json.assert_called_once()

@pytest.mark.asyncio
async def test_check_workflow_status_error(n8n_client):
    """Test checking workflow status with error"""
    mock_response = AsyncMock()
    mock_response.status_code = 404
    mock_response.text = "Execution not found"
    mock_response.raise_for_status = AsyncMock(side_effect=httpx.HTTPStatusError(
        "HTTP Error",
        request=httpx.Request("GET", "http://test-n8n.com"),
        response=mock_response
    ))
    
    with patch('httpx.AsyncClient.get', new_callable=AsyncMock) as mock_get:
        mock_get.return_value = mock_response
        
        with pytest.raises(httpx.HTTPStatusError) as exc_info:
            await n8n_client.check_workflow_status("test-execution-id")
        
        assert exc_info.value.response.status_code == 404
        mock_response.raise_for_status.assert_called_once() 