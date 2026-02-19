"""Tests for admin user management endpoints.

Tests verify:
1. GET /api/users returns users with pagination and permission tiers
2. PUT /api/users/{user_id}/organization assigns user to organization
3. PUT /api/users/{user_id}/permissions updates user permissions
4. PUT /api/users/{user_id}/disable disables user account
5. PUT /api/users/{user_id}/enable enables user account
6. POST /api/users/{user_id}/reset-password resets user password
7. Role-based access control (admin.organizations required)

Auth mocking strategy:
  The conftest patches `idp` with a MagicMock at module level. FastAPI captures
  the mock_dependency at route registration time. Since MagicMock's __call__
  signature (*args, **kwargs) confuses FastAPI's dependency injection, we use
  `app.dependency_overrides` to replace it with a proper callable.
"""

import pytest
from datetime import datetime, timezone
from unittest.mock import patch, AsyncMock

from fastapi import HTTPException, status
from fastapi.testclient import TestClient

from app.core.keycloak import OIDCUser
from app.main import app


# Sample Keycloak user data
SAMPLE_KC_USER_1 = {
    "id": "user-uuid-1",
    "username": "john.doe",
    "email": "john@example.com",
    "firstName": "John",
    "lastName": "Doe",
    "enabled": True,
    "createdTimestamp": 1234567890000,
    "attributes": {
        "organization_id": ["org-uuid-1"],
        "organization_name": ["Test Org"],
    },
}

SAMPLE_KC_USER_2 = {
    "id": "user-uuid-2",
    "username": "jane.smith",
    "email": "jane@example.com",
    "firstName": "Jane",
    "lastName": "Smith",
    "enabled": True,
    "createdTimestamp": 1234567891000,
    "attributes": {},
}

KC_SERVICE = "app.services.keycloak_admin.keycloak_admin_service"


def _make_admin_user() -> OIDCUser:
    now = int(datetime.now(timezone.utc).timestamp())
    return OIDCUser(
        sub="admin-uuid-123",
        preferred_username="admin_user",
        email="admin@test.com",
        email_verified=True,
        iat=now,
        exp=now + 3600,
    )


def _deny_auth():
    raise HTTPException(
        status_code=status.HTTP_403_FORBIDDEN, detail="Permission denied"
    )


@pytest.fixture
def client(mock_keycloak_initialization):
    """Test client with auth overridden to return an admin user by default."""
    mock_dep = mock_keycloak_initialization.get_current_user.return_value
    app.dependency_overrides[mock_dep] = _make_admin_user
    yield TestClient(app)
    app.dependency_overrides.pop(mock_dep, None)


@pytest.fixture
def deny_auth(mock_keycloak_initialization):
    """Override auth to raise 403 Forbidden."""
    mock_dep = mock_keycloak_initialization.get_current_user.return_value
    app.dependency_overrides[mock_dep] = _deny_auth
    yield
    # Restore admin auth (client fixture cleanup will handle final removal)
    app.dependency_overrides[mock_dep] = _make_admin_user


class TestGetAllUsers:
    """Test GET /api/users endpoint."""

    @patch(f"{KC_SERVICE}.get_user_organization_optimized", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.search_users", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.count_users_with_search", new_callable=AsyncMock)
    def test_get_users_returns_paginated_data(
        self, mock_count, mock_search, mock_get_roles, mock_get_org, client
    ):
        """GET /api/users returns users with pagination info."""
        mock_count.return_value = 2
        mock_search.return_value = [SAMPLE_KC_USER_1, SAMPLE_KC_USER_2]
        mock_get_roles.return_value = [{"name": "company.view"}]
        mock_get_org.return_value = {"id": "org-uuid-1", "name": "Test Org"}

        response = client.get("/api/users?page=1&limit=20")

        assert response.status_code == 200
        data = response.json()
        assert "data" in data
        assert "pagination" in data
        assert len(data["data"]) == 2
        assert data["pagination"]["total"] == 2
        assert data["pagination"]["page"] == 1

    @patch(f"{KC_SERVICE}.get_user_organization_optimized", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.search_users", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.count_users_with_search", new_callable=AsyncMock)
    def test_get_users_includes_permission_tier(
        self, mock_count, mock_search, mock_get_roles, mock_get_org, client
    ):
        """GET /api/users includes permission_tier derived from roles."""
        mock_count.return_value = 1
        mock_search.return_value = [SAMPLE_KC_USER_1]
        mock_get_roles.return_value = [
            {"name": "organization.read"},
            {"name": "organization.write"},
            {"name": "company.view"},
            {"name": "company.create"},
        ]
        mock_get_org.return_value = {"id": "org-uuid-1", "name": "Test Org"}

        response = client.get("/api/users?page=1&limit=20")

        assert response.status_code == 200
        user = response.json()["data"][0]
        assert "permission_tier" in user
        assert user["permission_tier"] == "writer"

    @patch(f"{KC_SERVICE}.get_user_organization_optimized", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.search_users", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.count_users_with_search", new_callable=AsyncMock)
    def test_get_users_includes_organization_id(
        self, mock_count, mock_search, mock_get_roles, mock_get_org, client
    ):
        """GET /api/users includes organization_id from user's organization."""
        mock_count.return_value = 1
        mock_search.return_value = [SAMPLE_KC_USER_1]
        mock_get_roles.return_value = [{"name": "company.view"}]
        mock_get_org.return_value = {"id": "org-uuid-1", "name": "Test Org"}

        response = client.get("/api/users?page=1&limit=20")

        assert response.status_code == 200
        user = response.json()["data"][0]
        assert user["organization_id"] == "org-uuid-1"

    def test_get_users_requires_admin_role(self, client, deny_auth):
        """GET /api/users requires admin.organizations role."""
        response = client.get("/api/users?page=1&limit=20")

        assert response.status_code == 403


class TestAssignUserToOrganization:
    """Test PUT /api/users/{user_id}/organization endpoint."""

    @patch(f"{KC_SERVICE}.add_user_to_organization", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user_organizations", new_callable=AsyncMock)
    def test_assign_organization_success(
        self, mock_get_orgs, mock_add_to_org, client
    ):
        """PUT /api/users/{user_id}/organization assigns user to new org."""
        mock_get_orgs.return_value = []
        mock_add_to_org.return_value = True

        response = client.put(
            "/api/users/user-uuid-1/organization",
            json={"organization_id": "org-uuid-new"},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        assert data["organization_id"] == "org-uuid-new"

    @patch(f"{KC_SERVICE}.add_user_to_organization", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.remove_user_from_organization", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user_organizations", new_callable=AsyncMock)
    def test_assign_organization_removes_old_org(
        self, mock_get_orgs, mock_remove_from_org, mock_add_to_org, client
    ):
        """PUT /api/users/{user_id}/organization removes user from old org."""
        mock_get_orgs.return_value = [{"id": "old-org-uuid", "name": "Old Org"}]
        mock_remove_from_org.return_value = True
        mock_add_to_org.return_value = True

        response = client.put(
            "/api/users/user-uuid-1/organization",
            json={"organization_id": "new-org-uuid"},
        )

        assert response.status_code == 200
        mock_remove_from_org.assert_called_once_with(
            organization_id="old-org-uuid", user_id="user-uuid-1"
        )

    def test_assign_organization_requires_admin_role(self, client, deny_auth):
        """PUT /api/users/{user_id}/organization requires admin.organizations."""
        response = client.put(
            "/api/users/user-uuid-1/organization",
            json={"organization_id": "org-uuid-1"},
        )

        assert response.status_code == 403


class TestUpdateUserPermissions:
    """Test PUT /api/users/{user_id}/permissions endpoint."""

    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.sync_user_realm_roles", new_callable=AsyncMock)
    def test_update_permissions_success(
        self, mock_sync_roles, mock_get_kc_user, mock_get_roles, client
    ):
        """PUT /api/users/{user_id}/permissions updates permissions."""
        mock_sync_roles.return_value = True
        mock_get_kc_user.return_value = SAMPLE_KC_USER_1
        mock_get_roles.return_value = [
            {"name": "company.create"},
            {"name": "organization.read"},
        ]

        response = client.put(
            "/api/users/user-uuid-1/permissions",
            json={"permissions": ["company.create", "organization.read"]},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["user_id"] == "user-uuid-1"
        assert set(data["permissions"]) == {"company.create", "organization.read"}

    def test_update_permissions_invalid_permission(self, client):
        """PUT /api/users/{user_id}/permissions rejects invalid permissions."""
        response = client.put(
            "/api/users/user-uuid-1/permissions",
            json={"permissions": ["company.create", "invalid.permission"]},
        )

        assert response.status_code == 422

    def test_update_permissions_requires_admin_role(self, client, deny_auth):
        """PUT /api/users/{user_id}/permissions requires admin.organizations."""
        response = client.put(
            "/api/users/user-uuid-1/permissions",
            json={"permissions": ["company.create"]},
        )

        assert response.status_code == 403


class TestDisableUser:
    """Test PUT /api/users/{user_id}/disable endpoint."""

    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.update_user", new_callable=AsyncMock)
    def test_disable_user_success(
        self, mock_update, mock_get_kc_user, mock_get_roles, client
    ):
        """PUT /api/users/{user_id}/disable disables user and returns revoked status."""
        mock_update.return_value = True
        mock_get_kc_user.return_value = SAMPLE_KC_USER_1
        mock_get_roles.return_value = [{"name": "company.view"}]

        response = client.put("/api/users/user-uuid-1/disable")

        assert response.status_code == 200
        data = response.json()
        assert data["user_id"] == "user-uuid-1"
        assert data["status"] == "revoked"
        mock_update.assert_called_once_with(
            user_id="user-uuid-1", user_data={"enabled": False}
        )

    def test_disable_user_requires_admin_role(self, client, deny_auth):
        """PUT /api/users/{user_id}/disable requires admin.organizations."""
        response = client.put("/api/users/user-uuid-1/disable")

        assert response.status_code == 403


class TestEnableUser:
    """Test PUT /api/users/{user_id}/enable endpoint."""

    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.update_user", new_callable=AsyncMock)
    def test_enable_user_success(
        self, mock_update, mock_get_kc_user, mock_get_roles, client
    ):
        """PUT /api/users/{user_id}/enable enables user and returns active status."""
        mock_update.return_value = True
        mock_get_kc_user.return_value = {**SAMPLE_KC_USER_1, "enabled": False}
        mock_get_roles.return_value = [{"name": "company.view"}]

        response = client.put("/api/users/user-uuid-1/enable")

        assert response.status_code == 200
        data = response.json()
        assert data["user_id"] == "user-uuid-1"
        assert data["status"] == "active"
        mock_update.assert_called_once_with(
            user_id="user-uuid-1", user_data={"enabled": True}
        )

    def test_enable_user_requires_admin_role(self, client, deny_auth):
        """PUT /api/users/{user_id}/enable requires admin.organizations."""
        response = client.put("/api/users/user-uuid-1/enable")

        assert response.status_code == 403


class TestResetUserPassword:
    """Test POST /api/users/{user_id}/reset-password endpoint."""

    @patch(f"{KC_SERVICE}.set_user_password", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user", new_callable=AsyncMock)
    def test_reset_password_success(
        self, mock_get_kc_user, mock_set_password, client
    ):
        """POST /api/users/{user_id}/reset-password sets temporary password."""
        mock_get_kc_user.return_value = SAMPLE_KC_USER_1
        mock_set_password.return_value = True

        response = client.post(
            "/api/users/user-uuid-1/reset-password",
            json={"temporary_password": "TempPass123!"},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        assert data["method"] == "temporary_password"
        mock_set_password.assert_called_once_with(
            user_id="user-uuid-1",
            password="TempPass123!",
            temporary=True,
        )

    def test_reset_password_requires_admin_role(self, client, deny_auth):
        """POST /api/users/{user_id}/reset-password requires admin.organizations."""
        response = client.post(
            "/api/users/user-uuid-1/reset-password",
            json={"temporary_password": "TempPass123!"},
        )

        assert response.status_code == 403
