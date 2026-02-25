"""Integration tests for admin users endpoints with permissions management.

Tests verify:
1. GET /api/users returns users with permissions field
2. PUT /api/users/{user_id}/permissions updates user permissions
3. PUT /api/users/{user_id}/disable disables user account
4. POST /api/users/{user_id}/reset-password resets user password
5. Role-based access control (admin.organizations required)
6. Proper error handling for invalid inputs

These are integration tests that mock Keycloak Admin API calls
to test the complete request/response cycle.
"""

import pytest
from unittest.mock import patch, AsyncMock, MagicMock
from fastapi.testclient import TestClient
from fastapi_keycloak import OIDCUser

from app.main import app


# Test client fixture
@pytest.fixture
def client():
    """Create FastAPI test client."""
    yield TestClient(app)


# Helper function to create a mock get_current_user
def create_mock_get_current_user(roles: list[str]):
    """Create a mock get_current_user function that returns callable with specified roles."""
    def get_current_user_mock(required_roles: list[str] | None = None):
        """Mock version of idp.get_current_user that checks roles."""
        def dependency():
            # Create OIDCUser with the specified roles
            user = OIDCUser(
                sub=f"test-uuid-{'-'.join(roles) if roles else 'none'}",
                preferred_username=f"test_user_{'-'.join(roles) if roles else 'none'}",
                email="test@example.com",
                roles=roles
            )

            # Check if user has required roles (mimic fastapi-keycloak behavior)
            if required_roles:
                if not any(role in roles for role in required_roles):
                    from fastapi import HTTPException, status
                    raise HTTPException(
                        status_code=status.HTTP_403_FORBIDDEN,
                        detail=f"User lacks required role(s): {required_roles}"
                    )

            return user
        return dependency
    return get_current_user_mock


class TestGetAllUsersWithPermissions:
    """Test GET /api/users returns users with permissions field."""

    @patch("app.services.keycloak_admin.keycloak_admin_service.get_user_realm_roles")
    @patch("app.services.keycloak_admin.keycloak_admin_service.get_users")
    @patch("app.services.keycloak_admin.keycloak_admin_service.count_users")
    @patch("app.core.keycloak.idp.get_current_user")
    def test_get_users_includes_permissions(
        self,
        mock_get_user,
        mock_count_users,
        mock_get_users,
        mock_get_roles,
        client
    ):
        """GET /api/users returns users with permissions array."""
        # Setup mocks
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])
        mock_count_users.return_value = AsyncMock(return_value=2)

        # Mock Keycloak users
        mock_get_users.return_value = AsyncMock(return_value=[
            {
                "id": "user-uuid-1",
                "username": "john.doe",
                "email": "john@example.com",
                "enabled": True,
                "createdTimestamp": 1234567890000,
                "attributes": {
                    "organization_id": ["org-uuid-1"],
                    "organization_name": ["Test Org"]
                }
            },
            {
                "id": "user-uuid-2",
                "username": "jane.smith",
                "email": "jane@example.com",
                "enabled": True,
                "createdTimestamp": 1234567891000,
                "attributes": {}
            }
        ])

        # Mock user roles - different permissions for each user
        async def mock_roles_side_effect(user_id: str):
            if user_id == "user-uuid-1":
                return [
                    {"name": "company.view"},
                    {"name": "organization.read"}
                ]
            elif user_id == "user-uuid-2":
                return [
                    {"name": "company.view"},
                    {"name": "company.create"},
                    {"name": "company.delete"},
                    {"name": "organization.read"},
                    {"name": "organization.write"}
                ]
            return []

        mock_get_roles.side_effect = mock_roles_side_effect

        # Make request
        response = client.get("/api/users?page=1&limit=20")

        # Assertions
        assert response.status_code == 200
        data = response.json()

        assert "data" in data
        assert "pagination" in data
        assert len(data["data"]) == 2

        # Check first user has permissions
        user1 = data["data"][0]
        assert "permissions" in user1
        assert isinstance(user1["permissions"], list)
        assert set(user1["permissions"]) == {"company.view", "organization.read"}

        # Check second user has different permissions
        user2 = data["data"][1]
        assert "permissions" in user2
        assert set(user2["permissions"]) == {
            "company.view", "company.create", "company.delete",
            "organization.read", "organization.write"
        }

    @patch("app.services.keycloak_admin.keycloak_admin_service.get_user_realm_roles")
    @patch("app.services.keycloak_admin.keycloak_admin_service.get_users")
    @patch("app.services.keycloak_admin.keycloak_admin_service.count_users")
    @patch("app.core.keycloak.idp.get_current_user")
    def test_get_users_filters_internal_roles(
        self,
        mock_get_user,
        mock_count_users,
        mock_get_users,
        mock_get_roles,
        client
    ):
        """GET /api/users filters out Keycloak internal roles."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])
        mock_count_users.return_value = AsyncMock(return_value=1)

        mock_get_users.return_value = AsyncMock(return_value=[
            {
                "id": "user-uuid-1",
                "username": "john.doe",
                "email": "john@example.com",
                "enabled": True,
                "createdTimestamp": 1234567890000,
                "attributes": {}
            }
        ])

        # Mock roles including internal Keycloak roles
        async def mock_roles_with_internal(user_id: str):
            return [
                {"name": "company.view"},  # Application role
                {"name": "organization.read"},  # Application role
                {"name": "uma_authorization"},  # Internal role - should be filtered
                {"name": "offline_access"},  # Internal role - should be filtered
                {"name": "default-roles-mint-realm"}  # Internal role - should be filtered
            ]

        mock_get_roles.side_effect = mock_roles_with_internal

        response = client.get("/api/users?page=1&limit=20")

        assert response.status_code == 200
        data = response.json()
        user = data["data"][0]

        # Should only have application roles, not internal ones
        assert set(user["permissions"]) == {"company.view", "organization.read"}
        assert "uma_authorization" not in user["permissions"]
        assert "offline_access" not in user["permissions"]

    @patch("app.core.keycloak.idp.get_current_user")
    def test_get_users_requires_admin_organizations_role(self, mock_get_user, client):
        """GET /api/users requires admin.organizations role."""
        # User without admin.organizations role
        mock_get_user.side_effect = create_mock_get_current_user(["company.view"])

        response = client.get("/api/users?page=1&limit=20")

        # Should be forbidden
        assert response.status_code == 403


class TestUpdateUserPermissions:
    """Test PUT /api/users/{user_id}/permissions endpoint."""

    @patch("app.services.keycloak_admin.keycloak_admin_service.sync_user_realm_roles")
    @patch("app.services.keycloak_admin.keycloak_admin_service.get_user")
    @patch("app.services.keycloak_admin.keycloak_admin_service.get_user_realm_roles")
    @patch("app.core.keycloak.idp.get_current_user")
    def test_update_permissions_success(
        self,
        mock_get_user,
        mock_get_roles,
        mock_get_kc_user,
        mock_sync_roles,
        client
    ):
        """PUT /api/users/{user_id}/permissions successfully updates permissions."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        # Mock Keycloak user
        mock_get_kc_user.return_value = AsyncMock(return_value={
            "id": "user-uuid-1",
            "username": "john.doe",
            "email": "john@example.com",
            "enabled": True,
            "createdTimestamp": 1234567890000,
            "attributes": {
                "organization_id": ["org-uuid-1"],
                "organization_name": ["Test Org"]
            }
        })

        # Mock role sync success
        mock_sync_roles.return_value = AsyncMock(return_value=True)

        # Mock updated roles after sync
        async def mock_updated_roles(user_id: str):
            return [
                {"name": "company.view"},
                {"name": "company.create"}
            ]
        mock_get_roles.side_effect = mock_updated_roles

        # Make request
        response = client.put(
            "/api/users/user-uuid-1/permissions",
            json={"permissions": ["company.view", "company.create"]}
        )

        # Assertions
        assert response.status_code == 200
        data = response.json()

        assert data["user_id"] == "user-uuid-1"
        assert set(data["permissions"]) == {"company.view", "company.create"}

        # Verify sync was called with correct roles
        mock_sync_roles.assert_called_once()
        call_args = mock_sync_roles.call_args[0]
        assert call_args[0] == "user-uuid-1"
        assert set(call_args[1]) == {"company.view", "company.create"}

    @patch("app.core.keycloak.idp.get_current_user")
    def test_update_permissions_invalid_permission(self, mock_get_user, client):
        """PUT /api/users/{user_id}/permissions rejects invalid permissions."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        # Request with invalid permission
        response = client.put(
            "/api/users/user-uuid-1/permissions",
            json={"permissions": ["company.view", "invalid.permission"]}
        )

        # Should return 400 Bad Request
        assert response.status_code == 400
        assert "invalid" in response.json()["detail"].lower()

    @patch("app.core.keycloak.idp.get_current_user")
    def test_update_permissions_requires_admin_organizations_role(self, mock_get_user, client):
        """PUT /api/users/{user_id}/permissions requires admin.organizations role."""
        mock_get_user.side_effect = create_mock_get_current_user(["company.view"])

        response = client.put(
            "/api/users/user-uuid-1/permissions",
            json={"permissions": ["company.view"]}
        )

        assert response.status_code == 403


class TestDisableUser:
    """Test PUT /api/users/{user_id}/disable endpoint."""

    @patch("app.services.keycloak_admin.keycloak_admin_service.update_user")
    @patch("app.services.keycloak_admin.keycloak_admin_service.get_user")
    @patch("app.services.keycloak_admin.keycloak_admin_service.get_user_realm_roles")
    @patch("app.core.keycloak.idp.get_current_user")
    def test_disable_user_success(
        self,
        mock_get_user,
        mock_get_roles,
        mock_get_kc_user,
        mock_update_user,
        client
    ):
        """PUT /api/users/{user_id}/disable successfully disables user."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        # Mock Keycloak user before disable
        mock_get_kc_user.return_value = AsyncMock(return_value={
            "id": "user-uuid-1",
            "username": "john.doe",
            "email": "john@example.com",
            "enabled": True,  # Currently enabled
            "createdTimestamp": 1234567890000,
            "attributes": {
                "organization_id": ["org-uuid-1"],
                "organization_name": ["Test Org"]
            }
        })

        # Mock successful update
        mock_update_user.return_value = AsyncMock(return_value=True)

        # Mock roles
        mock_get_roles.return_value = AsyncMock(return_value=[])

        response = client.put("/api/users/user-uuid-1/disable")

        assert response.status_code == 200
        data = response.json()

        assert data["user_id"] == "user-uuid-1"
        assert data["status"] == "revoked"

        # Verify update_user was called with enabled=False
        mock_update_user.assert_called_once()
        call_args = mock_update_user.call_args[0]
        assert call_args[0] == "user-uuid-1"
        assert call_args[1]["enabled"] == False

    @patch("app.core.keycloak.idp.get_current_user")
    def test_disable_user_requires_admin_organizations_role(self, mock_get_user, client):
        """PUT /api/users/{user_id}/disable requires admin.organizations role."""
        mock_get_user.side_effect = create_mock_get_current_user(["company.view"])

        response = client.put("/api/users/user-uuid-1/disable")

        assert response.status_code == 403


class TestResetUserPassword:
    """Test POST /api/users/{user_id}/reset-password endpoint."""

    @patch("app.services.keycloak_admin.keycloak_admin_service.reset_user_password")
    @patch("app.core.keycloak.idp.get_current_user")
    def test_reset_password_with_temporary_password(
        self,
        mock_get_user,
        mock_reset_password,
        client
    ):
        """POST /api/users/{user_id}/reset-password sets temporary password."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])
        mock_reset_password.return_value = AsyncMock(return_value=True)

        response = client.post(
            "/api/users/user-uuid-1/reset-password",
            json={
                "temporary_password": "TempPass123!",
                "send_email": False
            }
        )

        assert response.status_code == 200
        data = response.json()

        assert data["success"] is True
        assert data["method"] == "temporary_password"
        assert "temporary password" in data["message"].lower()

    @patch("app.services.keycloak_admin.keycloak_admin_service.send_password_reset_email")
    @patch("app.core.keycloak.idp.get_current_user")
    def test_reset_password_with_email(
        self,
        mock_get_user,
        mock_send_email,
        client
    ):
        """POST /api/users/{user_id}/reset-password sends reset email."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])
        mock_send_email.return_value = AsyncMock(return_value=True)

        response = client.post(
            "/api/users/user-uuid-1/reset-password",
            json={"send_email": True}
        )

        assert response.status_code == 200
        data = response.json()

        assert data["success"] is True
        assert data["method"] == "email"
        assert "email" in data["message"].lower()

    @patch("app.core.keycloak.idp.get_current_user")
    def test_reset_password_invalid_password(self, mock_get_user, client):
        """POST /api/users/{user_id}/reset-password rejects weak passwords."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        # Weak password (too short, no special chars)
        response = client.post(
            "/api/users/user-uuid-1/reset-password",
            json={
                "temporary_password": "weak",
                "send_email": False
            }
        )

        assert response.status_code == 400
        assert "password" in response.json()["detail"].lower()

    @patch("app.core.keycloak.idp.get_current_user")
    def test_reset_password_requires_admin_organizations_role(self, mock_get_user, client):
        """POST /api/users/{user_id}/reset-password requires admin.organizations role."""
        mock_get_user.side_effect = create_mock_get_current_user(["company.view"])

        response = client.post(
            "/api/users/user-uuid-1/reset-password",
            json={"temporary_password": "TempPass123!"}
        )

        assert response.status_code == 403
