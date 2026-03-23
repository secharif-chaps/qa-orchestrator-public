"""Integration tests for admin users endpoints with permissions management.

Tests verify:
1. GET /api/users returns users with permission_tier field
2. PUT /api/users/{user_id}/permissions updates user permissions
3. PUT /api/users/{user_id}/disable disables user account
4. POST /api/users/{user_id}/reset-password resets user password
5. Role-based access control (admin.organizations required)
6. Proper error handling for invalid inputs

These are integration tests that mock Keycloak Admin API calls
to test the complete request/response cycle.
"""

import time
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from fastapi import HTTPException, status
from fastapi.testclient import TestClient
from fastapi_keycloak import OIDCUser

from app.core.keycloak import idp
from app.main import app


@pytest.fixture
def client():
    """Create FastAPI test client."""
    yield TestClient(app)


def _make_user(roles: list[str]) -> OIDCUser:
    """Create an OIDCUser with specified roles."""
    label = "-".join(roles) if roles else "none"
    now = int(time.time())
    return OIDCUser(
        sub=f"test-uuid-{label}",
        preferred_username=f"test_user_{label}",
        email="test@example.com",
        email_verified=True,
        iat=now,
        exp=now + 3600,
        realm_access={"roles": roles} if roles else None,
    )


def _setup_auth(monkeypatch, roles: list[str]):
    """Patch idp._wrapped to simulate Keycloak JWT validation with role checking."""
    user = _make_user(roles)
    mock_wrapped = MagicMock()

    def mock_get_current_user(required_roles=None):
        async def dependency(request=None):
            if required_roles:
                if not any(role in roles for role in required_roles):
                    raise HTTPException(
                        status_code=status.HTTP_403_FORBIDDEN,
                        detail=f"User lacks required role(s): {required_roles}",
                    )
            return user

        return dependency

    mock_wrapped.get_current_user.side_effect = mock_get_current_user
    monkeypatch.setattr(idp, "_wrapped", mock_wrapped)


class TestGetAllUsersWithPermissions:
    """Test GET /api/users returns users with permission_tier field."""

    @patch("app.api.endpoints.users.keycloak_admin_service")
    def test_get_users_includes_permission_tier(self, mock_kc_service, monkeypatch, client):
        """GET /api/users returns users with permission_tier."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        # Mock count_users_with_search
        mock_kc_service.count_users_with_search = AsyncMock(return_value=2)

        # Mock search_users (no search, so uses paginated path)
        mock_kc_service.search_users = AsyncMock(
            return_value=[
                {
                    "id": "user-uuid-1",
                    "username": "john.doe",
                    "email": "john@example.com",
                    "firstName": "John",
                    "lastName": "Doe",
                    "enabled": True,
                    "createdTimestamp": 1234567890000,
                },
                {
                    "id": "user-uuid-2",
                    "username": "jane.smith",
                    "email": "jane@example.com",
                    "firstName": "Jane",
                    "lastName": "Smith",
                    "enabled": True,
                    "createdTimestamp": 1234567891000,
                },
            ]
        )

        # Mock user roles for tier computation
        async def mock_roles_side_effect(user_id: str):
            if user_id == "user-uuid-1":
                return [
                    {"name": "organization.read"},
                ]
            elif user_id == "user-uuid-2":
                return [
                    {"name": "organization.read"},
                    {"name": "organization.write"},
                    {"name": "company.create"},
                ]
            return []

        mock_kc_service.get_user_realm_roles = AsyncMock(side_effect=mock_roles_side_effect)

        # Mock organization lookup
        async def mock_org_side_effect(user_id: str):
            if user_id == "user-uuid-1":
                return {"id": "org-uuid-1", "name": "Test Org"}
            return None

        mock_kc_service.get_user_organization_optimized = AsyncMock(side_effect=mock_org_side_effect)

        response = client.get("/api/users?page=1&limit=20")

        assert response.status_code == 200
        data = response.json()

        assert "data" in data
        assert "pagination" in data
        assert len(data["data"]) == 2

        # Find users by user_id (sort order is created_at desc by default)
        users_by_id = {u["user_id"]: u for u in data["data"]}

        # user-uuid-1: only organization.read → reader tier
        user1 = users_by_id["user-uuid-1"]
        assert "permission_tier" in user1
        assert user1["permission_tier"] == "reader"
        assert user1["organization_id"] == "org-uuid-1"
        assert user1["organization_name"] == "Test Org"

        # user-uuid-2: organization.write → writer tier
        user2 = users_by_id["user-uuid-2"]
        assert user2["permission_tier"] == "writer"

    @patch("app.api.endpoints.users.keycloak_admin_service")
    def test_get_users_filters_internal_roles_for_tier(
        self,
        mock_kc_service,
        monkeypatch,
        client,
    ):
        """GET /api/users filters out Keycloak internal roles when computing tier."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        mock_kc_service.count_users_with_search = AsyncMock(return_value=1)
        mock_kc_service.search_users = AsyncMock(
            return_value=[
                {
                    "id": "user-uuid-1",
                    "username": "john.doe",
                    "email": "john@example.com",
                    "enabled": True,
                    "createdTimestamp": 1234567890000,
                },
            ]
        )

        # Mock roles including internal Keycloak roles
        async def mock_roles_with_internal(user_id: str):
            return [
                {"name": "organization.read"},
                {"name": "uma_authorization"},
                {"name": "offline_access"},
                {"name": "default-roles-chapsmind"},
            ]

        mock_kc_service.get_user_realm_roles = AsyncMock(side_effect=mock_roles_with_internal)
        mock_kc_service.get_user_organization_optimized = AsyncMock(return_value=None)

        response = client.get("/api/users?page=1&limit=20")

        assert response.status_code == 200
        data = response.json()
        user = data["data"][0]

        # Internal roles are filtered, only organization.read remains → reader tier
        assert user["permission_tier"] == "reader"

    def test_get_users_requires_admin_organizations_role(self, monkeypatch, client):
        """GET /api/users requires admin.organizations role."""
        _setup_auth(monkeypatch, ["company.view"])

        response = client.get("/api/users?page=1&limit=20")

        assert response.status_code == 403


class TestUpdateUserPermissions:
    """Test PUT /api/users/{user_id}/permissions endpoint."""

    @patch("app.api.endpoints.users.keycloak_admin_service")
    def test_update_permissions_success(
        self,
        mock_kc_service,
        monkeypatch,
        client,
    ):
        """PUT /api/users/{user_id}/permissions successfully updates permissions."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        # Mock sync_user_realm_roles
        mock_kc_service.sync_user_realm_roles = AsyncMock(return_value=True)

        # Mock get_user (called after sync to build response)
        mock_kc_service.get_user = AsyncMock(
            return_value={
                "id": "user-uuid-1",
                "username": "john.doe",
                "email": "john@example.com",
                "enabled": True,
                "createdTimestamp": 1234567890000,
                "attributes": {
                    "organization_id": ["org-uuid-1"],
                    "organization_name": ["Test Org"],
                },
            }
        )

        # Mock updated roles after sync
        mock_kc_service.get_user_realm_roles = AsyncMock(
            return_value=[
                {"name": "company.create"},
                {"name": "organization.read"},
            ]
        )

        response = client.put(
            "/api/users/user-uuid-1/permissions",
            json={"permissions": ["company.create", "organization.read"]},
        )

        assert response.status_code == 200
        data = response.json()

        assert data["user_id"] == "user-uuid-1"
        assert set(data["permissions"]) == {"company.create", "organization.read"}

        # Verify sync was called
        mock_kc_service.sync_user_realm_roles.assert_called_once()

    def test_update_permissions_invalid_permission(self, monkeypatch, client):
        """PUT /api/users/{user_id}/permissions rejects invalid permissions."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        response = client.put(
            "/api/users/user-uuid-1/permissions",
            json={"permissions": ["company.create", "invalid.permission"]},
        )

        # Pydantic validation returns 422 for invalid field values
        assert response.status_code == 422

    def test_update_permissions_requires_admin_organizations_role(self, monkeypatch, client):
        """PUT /api/users/{user_id}/permissions requires admin.organizations role."""
        _setup_auth(monkeypatch, ["company.view"])

        response = client.put(
            "/api/users/user-uuid-1/permissions",
            json={"permissions": ["company.create"]},
        )

        assert response.status_code == 403


class TestDisableUser:
    """Test PUT /api/users/{user_id}/disable endpoint."""

    @patch("app.api.endpoints.users.keycloak_admin_service")
    def test_disable_user_success(
        self,
        mock_kc_service,
        monkeypatch,
        client,
    ):
        """PUT /api/users/{user_id}/disable successfully disables user."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        # Mock update_user
        mock_kc_service.update_user = AsyncMock(return_value=True)

        # Mock get_user (called after update to build response)
        mock_kc_service.get_user = AsyncMock(
            return_value={
                "id": "user-uuid-1",
                "username": "john.doe",
                "email": "john@example.com",
                "enabled": False,
                "createdTimestamp": 1234567890000,
                "attributes": {
                    "organization_id": ["org-uuid-1"],
                    "organization_name": ["Test Org"],
                },
            }
        )

        # Mock roles
        mock_kc_service.get_user_realm_roles = AsyncMock(return_value=[])

        response = client.put("/api/users/user-uuid-1/disable")

        assert response.status_code == 200
        data = response.json()

        assert data["user_id"] == "user-uuid-1"
        assert data["status"] == "revoked"

        # Verify update_user was called with enabled=False
        mock_kc_service.update_user.assert_called_once_with(
            user_id="user-uuid-1",
            user_data={"enabled": False},
        )

    def test_disable_user_requires_admin_organizations_role(self, monkeypatch, client):
        """PUT /api/users/{user_id}/disable requires admin.organizations role."""
        _setup_auth(monkeypatch, ["company.view"])

        response = client.put("/api/users/user-uuid-1/disable")

        assert response.status_code == 403


class TestResetUserPassword:
    """Test POST /api/users/{user_id}/reset-password endpoint."""

    @patch("app.api.endpoints.users.keycloak_admin_service")
    def test_reset_password_with_temporary_password(
        self,
        mock_kc_service,
        monkeypatch,
        client,
    ):
        """POST /api/users/{user_id}/reset-password sets temporary password."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        # Mock get_user
        mock_kc_service.get_user = AsyncMock(
            return_value={
                "id": "user-uuid-1",
                "username": "john.doe",
                "email": "john@example.com",
                "enabled": True,
            }
        )

        # Mock set_user_password
        mock_kc_service.set_user_password = AsyncMock(return_value=True)

        response = client.post(
            "/api/users/user-uuid-1/reset-password", json={"temporary_password": "TempPass123!", "send_email": False}
        )

        assert response.status_code == 200
        data = response.json()

        assert data["success"] is True
        assert data["method"] == "temporary_password"
        assert "temporary password" in data["message"].lower()

        # Verify set_user_password was called
        mock_kc_service.set_user_password.assert_called_once_with(
            user_id="user-uuid-1",
            password="TempPass123!",
            temporary=True,
        )

    @patch("app.api.endpoints.users.keycloak_admin_service")
    def test_reset_password_requires_temporary_password(
        self,
        mock_kc_service,
        monkeypatch,
        client,
    ):
        """POST /api/users/{user_id}/reset-password requires temporary_password."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        # Mock get_user
        mock_kc_service.get_user = AsyncMock(
            return_value={
                "id": "user-uuid-1",
                "username": "john.doe",
            }
        )

        response = client.post(
            "/api/users/user-uuid-1/reset-password",
            json={"send_email": True},
        )

        assert response.status_code == 400
        assert "temporary_password" in response.json()["detail"].lower()

    def test_reset_password_invalid_password(self, monkeypatch, client):
        """POST /api/users/{user_id}/reset-password rejects weak passwords."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        # Weak password (too short, no special chars)
        response = client.post(
            "/api/users/user-uuid-1/reset-password", json={"temporary_password": "weak", "send_email": False}
        )

        # Pydantic validation returns 422 for invalid field values
        assert response.status_code == 422

    def test_reset_password_requires_admin_organizations_role(self, monkeypatch, client):
        """POST /api/users/{user_id}/reset-password requires admin.organizations role."""
        _setup_auth(monkeypatch, ["company.view"])

        response = client.post(
            "/api/users/user-uuid-1/reset-password",
            json={"temporary_password": "TempPass123!"},
        )

        assert response.status_code == 403
