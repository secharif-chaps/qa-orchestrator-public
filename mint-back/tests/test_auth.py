import pytest
from fastapi.testclient import TestClient
from unittest.mock import patch, AsyncMock
from app.main import app
from app.schemas.user import Token, TokenData

client = TestClient(app)

# Test credentials
TEST_USERNAME = "nmr"
TEST_PASSWORD = "password"


class TestAuthRoutes:
    """Test cases for authentication routes"""

    @patch('app.services.auth.keycloak_service.authenticate_user')
    @pytest.mark.asyncio
    async def test_login_success(self, mock_authenticate):
        """Test successful login"""
        mock_authenticate.return_value = {
            "access_token": "test_access_token",
            "refresh_token": "test_refresh_token",
            "expires_in": 300
        }

        response = client.post(
            "/api/auth/login",
            json={"username": TEST_USERNAME, "password": TEST_PASSWORD}
        )

        assert response.status_code == 200
        data = response.json()
        assert data["access_token"] == "test_access_token"
        assert data["refresh_token"] == "test_refresh_token"
        assert data["token_type"] == "bearer"
        assert data["expires_in"] == 300

    @patch('app.services.auth.keycloak_service.authenticate_user')
    @pytest.mark.asyncio
    async def test_login_invalid_credentials(self, mock_authenticate):
        """Test login with invalid credentials"""
        mock_authenticate.return_value = None

        response = client.post(
            "/api/auth/login",
            json={"username": "invalid", "password": "invalid"}
        )

        assert response.status_code == 401
        assert response.json()["detail"] == "Invalid credentials"

    @patch('app.services.auth.keycloak_service.authenticate_user')
    @pytest.mark.asyncio
    async def test_login_service_error(self, mock_authenticate):
        """Test login with authentication service error"""
        mock_authenticate.side_effect = Exception("Service error")

        response = client.post(
            "/api/auth/login",
            json={"username": TEST_USERNAME, "password": TEST_PASSWORD}
        )

        assert response.status_code == 500
        assert response.json()["detail"] == "Authentication service error"

    @patch('app.services.auth.keycloak_service.refresh_token')
    @pytest.mark.asyncio
    async def test_refresh_token_success(self, mock_refresh):
        """Test successful token refresh"""
        mock_refresh.return_value = {
            "access_token": "new_access_token",
            "refresh_token": "new_refresh_token",
            "expires_in": 300
        }

        response = client.post(
            "/api/auth/refresh",
            json={"refresh_token": "valid_refresh_token"}
        )

        assert response.status_code == 200
        data = response.json()
        assert data["access_token"] == "new_access_token"
        assert data["refresh_token"] == "new_refresh_token"
        assert data["token_type"] == "bearer"

    @patch('app.services.auth.keycloak_service.refresh_token')
    @pytest.mark.asyncio
    async def test_refresh_token_invalid(self, mock_refresh):
        """Test token refresh with invalid refresh token"""
        mock_refresh.return_value = None

        response = client.post(
            "/api/auth/refresh",
            json={"refresh_token": "invalid_refresh_token"}
        )

        assert response.status_code == 401
        assert response.json()["detail"] == "Invalid refresh token"

    @patch('app.services.auth.keycloak_service.logout')
    @pytest.mark.asyncio
    async def test_logout_success(self, mock_logout):
        """Test successful logout"""
        mock_logout.return_value = True

        response = client.post(
            "/api/auth/logout",
            json={"refresh_token": "valid_refresh_token"}
        )

        assert response.status_code == 200
        assert response.json()["message"] == "Successfully logged out"

    @patch('app.services.auth.keycloak_service.logout')
    @pytest.mark.asyncio
    async def test_logout_failure(self, mock_logout):
        """Test logout failure"""
        mock_logout.return_value = False

        response = client.post(
            "/api/auth/logout",
            json={"refresh_token": "invalid_refresh_token"}
        )

        assert response.status_code == 400
        assert response.json()["detail"] == "Failed to logout"

    @patch('app.services.auth.keycloak_service.get_user_info')
    @pytest.mark.asyncio
    async def test_get_current_user_success(self, mock_get_user_info):
        """Test getting current user info with valid token"""
        mock_get_user_info.return_value = {
            "sub": "user-id",
            "username": TEST_USERNAME,
            "email": "test@example.com",
            "roles": ["user"]
        }

        response = client.get(
            "/api/auth/me",
            headers={"Authorization": "Bearer valid_access_token"}
        )

        assert response.status_code == 200
        data = response.json()
        assert data["username"] == TEST_USERNAME
        assert data["email"] == "test@example.com"

    @patch('app.services.auth.keycloak_service.get_user_info')
    @pytest.mark.asyncio
    async def test_get_current_user_invalid_token(self, mock_get_user_info):
        """Test getting current user info with invalid token"""
        mock_get_user_info.return_value = None

        response = client.get(
            "/api/auth/me",
            headers={"Authorization": "Bearer invalid_token"}
        )

        assert response.status_code == 401
        assert response.json()["detail"] == "Invalid token"

    def test_get_current_user_no_token(self):
        """Test getting current user info without token"""
        response = client.get("/api/auth/me")

        assert response.status_code == 403
        assert "Not authenticated" in response.json()["detail"]

    @patch('app.services.auth.keycloak_service.verify_token')
    @pytest.mark.asyncio
    async def test_verify_token_success(self, mock_verify):
        """Test token verification with valid token"""
        mock_token_data = TokenData(
            username=TEST_USERNAME,
            sub="user-id",
            roles=["user"]
        )
        mock_verify.return_value = mock_token_data

        response = client.post(
            "/api/auth/verify",
            headers={"Authorization": "Bearer valid_access_token"}
        )

        assert response.status_code == 200
        data = response.json()
        assert data["valid"] is True
        assert data["username"] == TEST_USERNAME
        assert data["sub"] == "user-id"
        assert data["roles"] == ["user"]

    @patch('app.services.auth.keycloak_service.verify_token')
    @pytest.mark.asyncio
    async def test_verify_token_invalid(self, mock_verify):
        """Test token verification with invalid token"""
        mock_verify.return_value = None

        response = client.post(
            "/api/auth/verify",
            headers={"Authorization": "Bearer invalid_token"}
        )

        assert response.status_code == 401
        assert response.json()["detail"] == "Invalid token"

    @patch('app.services.auth.keycloak_service.introspect_token')
    @pytest.mark.asyncio
    async def test_introspect_token_success(self, mock_introspect):
        """Test token introspection with valid token"""
        mock_introspect.return_value = {
            "active": True,
            "username": TEST_USERNAME,
            "sub": "user-id",
            "exp": 1234567890,
            "client_id": "mint-client"
        }

        response = client.post(
            "/api/auth/introspect",
            headers={"Authorization": "Bearer valid_access_token"}
        )

        assert response.status_code == 200
        data = response.json()
        assert data["active"] is True
        assert data["username"] == TEST_USERNAME

    @patch('app.services.auth.keycloak_service.introspect_token')
    @pytest.mark.asyncio
    async def test_introspect_token_invalid(self, mock_introspect):
        """Test token introspection with invalid token"""
        mock_introspect.return_value = None

        response = client.post(
            "/api/auth/introspect",
            headers={"Authorization": "Bearer invalid_token"}
        )

        assert response.status_code == 401
        assert response.json()["detail"] == "Invalid or inactive token"