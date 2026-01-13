import pytest
from unittest.mock import Mock, patch, AsyncMock
from jose import jwt
import asyncio
from datetime import datetime, timezone

from app.core.keycloak import OIDCUser
from app.core.client_auth import introspect_token, ClientAuthError
from app.core.organization import extract_organization_from_token, OrganizationContext


def test_1_user_jwt_validation():
    """Test 1: User JWT validation - OIDCUser model."""
    # OIDCUser from fastapi-keycloak requires iat, exp, email_verified
    now = int(datetime.now(timezone.utc).timestamp())
    user = OIDCUser(
        sub="test-user-123",
        preferred_username="testuser",
        email="test@example.com",
        email_verified=True,
        iat=now,
        exp=now + 3600,
        organization=[{"Test Org": {"id": "org-123"}}, "Test Org"]
    )
    assert user.sub == "test-user-123"
    assert user.preferred_username == "testuser"
    assert user.organization is not None

@pytest.mark.asyncio
async def test_2_client_credentials_validation():
    """Test 2: Client credentials validation."""
    # Mock the post request directly
    async def mock_post(*args, **kwargs):
        # Create a mock response
        response = AsyncMock()
        # Make json() return the dict directly when awaited
        response.json = AsyncMock(return_value={"active": True, "client_id": "test-service"})
        response.raise_for_status = Mock()
        return response
    
    # Mock the async context manager
    class MockAsyncClient:
        def __init__(self, *args, **kwargs):
            pass
            
        async def __aenter__(self):
            self.post = mock_post
            return self
            
        async def __aexit__(self, *args):
            pass
    
    with patch('app.core.client_auth.httpx.AsyncClient', MockAsyncClient):
        # Mock settings
        with patch('app.core.client_auth.settings') as mock_settings:
            mock_settings.KEYCLOAK_SERVER_URL = "http://test"
            mock_settings.KEYCLOAK_REALM = "test"
            mock_settings.KEYCLOAK_CLIENT_ID = "test"
            mock_settings.KEYCLOAK_CLIENT_SECRET = "test"
            
            # Mock the cache to avoid cache issues
            with patch('app.core.client_auth._introspection_cache', {}):
                result = await introspect_token("test-token")
                assert result["active"] is True
                assert result["client_id"] == "test-service"

def test_3_organization_extraction():
    """Test 3: Organization extraction from token."""
    # Valid extraction
    token = {"organization": [{"Company": {"id": "123"}}, "Company"]}
    result = extract_organization_from_token(token)
    assert result == ("123", "Company")
    
    # Invalid extraction
    token = {"organization": "invalid"}
    result = extract_organization_from_token(token)
    assert result is None

def test_4_organization_context_model():
    """Test 4: OrganizationContext model."""
    context = OrganizationContext(
        organization_id="org-1",
        organization_name="Test Org",
        user_id="user-1",
        username="testuser",
        enabled_modules=["Screen", "Target"]
    )
    assert context.organization_id == "org-1"
    assert context.username == "testuser"
    assert len(context.enabled_modules) == 2

if __name__ == "__main__":
    # Run tests
    test_1_user_jwt_validation()
    asyncio.run(test_2_client_credentials_validation())
    test_3_organization_extraction()
    test_4_organization_context_model()