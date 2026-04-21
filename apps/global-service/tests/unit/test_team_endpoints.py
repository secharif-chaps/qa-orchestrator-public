"""Tests for team management endpoints.

Tests verify:
1. GET /api/team/members - list team members with pagination
2. GET /api/team/members/{user_id}/permissions - get member permissions
3. POST /api/team/members - invite new team member
4. PATCH /api/team/members/{user_id} - update member permissions
5. PUT /api/team/members/{user_id} - update member profile & permissions
6. POST /api/team/members/{user_id}/reset-password - reset member password
7. DELETE /api/team/members/{user_id} - remove team member
8. Role-based access control

Auth mocking strategy:
  - Manager user (organization.manage) for write operations
  - Reader user (organization.read) for permission denied tests on verify_any_role_access
  - deny_auth for permission denied tests on required_roles in Depends
"""

import pytest
import uuid
from datetime import datetime, timezone
from unittest.mock import patch, AsyncMock

from fastapi import HTTPException, status
from fastapi.testclient import TestClient

from app.core.keycloak import OIDCUser
from app.core.organization import OrganizationContext, get_user_organization
from app.main import app


# ── Constants ────────────────────────────────────────────────────────

TEST_ORG_ID = str(uuid.uuid4())
TEST_ORG_NAME = "Test Organization"
MANAGER_USER_ID = str(uuid.uuid4())
READER_USER_ID = str(uuid.uuid4())
TARGET_USER_ID = str(uuid.uuid4())

KC_SERVICE = "app.services.keycloak_admin.keycloak_admin_service"

# Sample Keycloak user data with valid UUIDs
KC_MEMBER_1 = {
    "id": str(uuid.uuid4()),
    "username": "john.doe",
    "email": "john@example.com",
    "firstName": "John",
    "lastName": "Doe",
    "createdTimestamp": 1234567890000,
}

KC_MEMBER_2 = {
    "id": str(uuid.uuid4()),
    "username": "jane.smith",
    "email": "jane@example.com",
    "firstName": "Jane",
    "lastName": "Smith",
    "createdTimestamp": 1234567891000,
}

TARGET_KC_USER = {
    "id": TARGET_USER_ID,
    "username": "target.user",
    "email": "target@example.com",
    "firstName": "Target",
    "lastName": "User",
    "createdTimestamp": 1234567892000,
}


# ── User factories ──────────────────────────────────────────────────

def _make_manager_user() -> OIDCUser:
    """Manager with organization.manage role (can do write operations)."""
    now = int(datetime.now(timezone.utc).timestamp())
    return OIDCUser(
        sub=MANAGER_USER_ID,
        preferred_username="manager_user",
        email="manager@test.com",
        email_verified=True,
        iat=now,
        exp=now + 3600,
        realm_access={"roles": ["organization.manage", "organization.read"]},
    )


def _make_reader_user() -> OIDCUser:
    """Reader with only organization.read role (no manage access)."""
    now = int(datetime.now(timezone.utc).timestamp())
    return OIDCUser(
        sub=READER_USER_ID,
        preferred_username="reader_user",
        email="reader@test.com",
        email_verified=True,
        iat=now,
        exp=now + 3600,
        realm_access={"roles": ["organization.read"]},
    )


def _deny_auth():
    raise HTTPException(
        status_code=status.HTTP_403_FORBIDDEN, detail="Permission denied"
    )


def _make_org_context() -> OrganizationContext:
    return OrganizationContext(
        organization_id=TEST_ORG_ID,
        organization_name=TEST_ORG_NAME,
        user_id=MANAGER_USER_ID,
        username="manager_user",
    )


# ── Fixtures ─────────────────────────────────────────────────────────

@pytest.fixture
def client(mock_keycloak_initialization):
    """Test client with manager auth and org context."""
    mock_dep = mock_keycloak_initialization.get_current_user.return_value
    app.dependency_overrides[mock_dep] = _make_manager_user
    app.dependency_overrides[get_user_organization] = _make_org_context
    yield TestClient(app)
    app.dependency_overrides.pop(mock_dep, None)
    app.dependency_overrides.pop(get_user_organization, None)


@pytest.fixture
def reader_client(mock_keycloak_initialization):
    """Test client with reader auth (fails verify_any_role_access)."""
    mock_dep = mock_keycloak_initialization.get_current_user.return_value
    app.dependency_overrides[mock_dep] = _make_reader_user
    app.dependency_overrides[get_user_organization] = _make_org_context
    yield TestClient(app)
    app.dependency_overrides.pop(mock_dep, None)
    app.dependency_overrides.pop(get_user_organization, None)


@pytest.fixture
def deny_auth(mock_keycloak_initialization):
    """Override auth to raise 403 (for required_roles in Depends)."""
    mock_dep = mock_keycloak_initialization.get_current_user.return_value
    app.dependency_overrides[mock_dep] = _deny_auth
    yield
    app.dependency_overrides[mock_dep] = _make_manager_user


# ── GET /api/team/members ────────────────────────────────────────────

class TestListTeamMembers:
    """Test GET /api/team/members endpoint."""

    @patch(f"{KC_SERVICE}.count_organization_members", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_organization_members", new_callable=AsyncMock)
    def test_list_members_returns_paginated_data(self, mock_get_members, mock_count, client):
        mock_get_members.return_value = [KC_MEMBER_1, KC_MEMBER_2]
        mock_count.return_value = 2

        response = client.get("/api/team/members?page=1&limit=10")

        assert response.status_code == 200
        data = response.json()
        assert "data" in data
        assert "pagination" in data
        assert len(data["data"]) == 2
        assert data["pagination"]["total"] == 2
        assert data["pagination"]["page"] == 1
        assert data["pagination"]["total_pages"] == 1


# ── GET /api/team/members/{user_id}/permissions ──────────────────────

class TestGetMemberPermissions:
    """Test GET /api/team/members/{user_id}/permissions endpoint."""

    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    def test_get_permissions_reader(self, mock_get_roles, client):
        mock_get_roles.return_value = [{"name": "organization.read"}]

        response = client.get(f"/api/team/members/{TARGET_USER_ID}/permissions")

        assert response.status_code == 200
        data = response.json()
        assert data["user_id"] == TARGET_USER_ID
        assert data["permission_tier"] == "reader"

    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    def test_get_permissions_writer(self, mock_get_roles, client):
        mock_get_roles.return_value = [
            {"name": "organization.read"},
            {"name": "organization.write"},
            {"name": "company.view"},
            {"name": "company.create"},
        ]

        response = client.get(f"/api/team/members/{TARGET_USER_ID}/permissions")

        assert response.status_code == 200
        assert response.json()["permission_tier"] == "writer"

    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    def test_get_permissions_manager(self, mock_get_roles, client):
        mock_get_roles.return_value = [
            {"name": "organization.manage"},
            {"name": "organization.read"},
        ]

        response = client.get(f"/api/team/members/{TARGET_USER_ID}/permissions")

        assert response.status_code == 200
        assert response.json()["permission_tier"] == "manager"

    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    def test_get_permissions_user_not_found(self, mock_get_roles, client):
        mock_get_roles.return_value = None
        non_existent_id = str(uuid.uuid4())

        response = client.get(f"/api/team/members/{non_existent_id}/permissions")

        assert response.status_code == 404

    def test_get_permissions_requires_auth(self, client, deny_auth):
        response = client.get(f"/api/team/members/{TARGET_USER_ID}/permissions")
        assert response.status_code == 403


# ── POST /api/team/members ───────────────────────────────────────────

class TestInviteTeamMember:
    """Test POST /api/team/members endpoint."""

    VALID_INVITE = {
        "username": "new.user",
        "email": "newuser@example.com",
        "first_name": "New",
        "last_name": "User",
        "temporary_password": "TempPass123!",
        "permission_tier": "reader",
    }

    @patch(f"{KC_SERVICE}.sync_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.add_user_to_organization", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.create_user", new_callable=AsyncMock)
    def test_invite_member_success(
        self, mock_create, mock_add_org, mock_sync_roles, client
    ):
        new_user_id = str(uuid.uuid4())
        mock_create.return_value = new_user_id
        mock_add_org.return_value = True
        mock_sync_roles.return_value = True

        response = client.post("/api/team/members", json=self.VALID_INVITE)

        assert response.status_code == 201
        data = response.json()
        assert data["id"] == new_user_id
        assert data["username"] == "new.user"
        assert data["email"] == "newuser@example.com"
        assert data["permission_tier"] == "reader"
        mock_create.assert_called_once()
        mock_add_org.assert_called_once_with(
            organization_id=TEST_ORG_ID, user_id=new_user_id
        )

    @patch(f"{KC_SERVICE}.create_user", new_callable=AsyncMock)
    def test_invite_member_duplicate_user(self, mock_create, client):
        """Keycloak returns 409 for duplicate username/email."""
        mock_create.side_effect = HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail="A user with this username or email already exists",
        )

        response = client.post("/api/team/members", json=self.VALID_INVITE)

        assert response.status_code == 409


# ── PATCH /api/team/members/{user_id} ────────────────────────────────

class TestUpdateMemberPermissions:
    """Test PATCH /api/team/members/{user_id} endpoint."""

    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.sync_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user", new_callable=AsyncMock)
    def test_update_permissions_success(self, mock_get_user, mock_sync_roles, mock_get_roles, client):
        mock_get_user.return_value = TARGET_KC_USER
        mock_sync_roles.return_value = True
        mock_get_roles.return_value = [
            {"name": "organization.read"},
            {"name": "organization.write"},
            {"name": "company.view"},
            {"name": "company.create"},
            {"name": "company.delete"},
        ]

        response = client.patch(f"/api/team/members/{TARGET_USER_ID}", json={"permission_tier": "writer"})

        assert response.status_code == 200
        data = response.json()
        assert data["id"] == TARGET_USER_ID
        assert data["permission_tier"] == "writer"

    def test_update_permissions_self_forbidden(self, client):
        """Cannot modify your own permissions."""
        response = client.patch(
            f"/api/team/members/{MANAGER_USER_ID}",
            json={"permission_tier": "reader"},
        )

        assert response.status_code == 400
        assert response.json()["detail"]["error"] == "self_modification_forbidden"

    def test_update_permissions_requires_manage_role(self, reader_client):
        """Reader cannot update permissions (verify_any_role_access)."""
        response = reader_client.patch(
            f"/api/team/members/{TARGET_USER_ID}",
            json={"permission_tier": "writer"},
        )
        assert response.status_code == 403


# ── PUT /api/team/members/{user_id} ─────────────────────────────────

class TestUpdateTeamMember:
    """Test PUT /api/team/members/{user_id} endpoint."""

    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.update_user", new_callable=AsyncMock)
    def test_update_profile_success(
        self, mock_update_user, mock_get_user, mock_get_roles, client
    ):
        updated_kc_user = {
            **TARGET_KC_USER,
            "firstName": "Updated",
            "lastName": "Name",
        }
        mock_get_user.side_effect = [TARGET_KC_USER, updated_kc_user]
        mock_update_user.return_value = True
        mock_get_roles.return_value = [{"name": "organization.read"}]

        response = client.put(
            f"/api/team/members/{TARGET_USER_ID}",
            json={"first_name": "Updated", "last_name": "Name"},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["first_name"] == "Updated"
        assert data["last_name"] == "Name"
        mock_update_user.assert_called_once_with(
            user_id=TARGET_USER_ID,
            user_data={"firstName": "Updated", "lastName": "Name"},
        )

    @patch(f"{KC_SERVICE}.get_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.sync_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.update_user", new_callable=AsyncMock)
    def test_update_profile_and_permissions(self, mock_update_user, mock_sync_roles, mock_get_user, mock_get_roles, client):
        """Update both profile and permission tier in one request."""
        updated_kc_user = {**TARGET_KC_USER, "email": "newemail@example.com"}
        mock_get_user.side_effect = [TARGET_KC_USER, updated_kc_user]
        mock_update_user.return_value = True
        mock_sync_roles.return_value = True
        mock_get_roles.return_value = [
            {"name": "organization.manage"},
            {"name": "organization.read"},
        ]

        response = client.put(
            f"/api/team/members/{TARGET_USER_ID}",
            json={"email": "newemail@example.com", "permission_tier": "manager"},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["email"] == "newemail@example.com"
        assert data["permission_tier"] == "manager"
        mock_update_user.assert_called_once()
        mock_sync_roles.assert_called_once()

    def test_update_self_forbidden(self, client):
        response = client.put(
            f"/api/team/members/{MANAGER_USER_ID}",
            json={"first_name": "Sneaky"},
        )

        assert response.status_code == 400
        assert response.json()["detail"]["error"] == "self_modification_forbidden"


# ── POST /api/team/members/{user_id}/reset-password ──────────────────

class TestResetMemberPassword:
    """Test POST /api/team/members/{user_id}/reset-password endpoint."""

    VALID_PASSWORD = "NewTempPass123!"

    @patch(f"{KC_SERVICE}.set_user_password", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user", new_callable=AsyncMock)
    def test_reset_password_success(
        self, mock_get_user, mock_set_password, client
    ):
        mock_get_user.return_value = TARGET_KC_USER
        mock_set_password.return_value = True

        response = client.post(
            f"/api/team/members/{TARGET_USER_ID}/reset-password",
            json={"temporary_password": self.VALID_PASSWORD},
        )

        assert response.status_code == 200
        data = response.json()
        assert data["temporary_password"] == self.VALID_PASSWORD
        mock_set_password.assert_called_once_with(
            user_id=TARGET_USER_ID,
            password=self.VALID_PASSWORD,
            temporary=True,
        )

    def test_reset_password_weak_password(self, client):
        response = client.post(
            f"/api/team/members/{TARGET_USER_ID}/reset-password",
            json={"temporary_password": "weak"},
        )
        assert response.status_code == 422

    def test_reset_password_requires_manage_role(self, reader_client):
        response = reader_client.post(
            f"/api/team/members/{TARGET_USER_ID}/reset-password",
            json={"temporary_password": self.VALID_PASSWORD},
        )
        assert response.status_code == 403


# ── DELETE /api/team/members/{user_id} ───────────────────────────────

class TestRemoveTeamMember:
    """Test DELETE /api/team/members/{user_id} endpoint."""

    @patch(f"{KC_SERVICE}.sync_user_realm_roles", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.remove_user_from_organization", new_callable=AsyncMock)
    @patch(f"{KC_SERVICE}.get_user", new_callable=AsyncMock)
    def test_remove_member_success(
        self, mock_get_user, mock_remove, mock_sync_roles, client
    ):
        mock_get_user.return_value = TARGET_KC_USER
        mock_remove.return_value = True
        mock_sync_roles.return_value = True

        response = client.delete(f"/api/team/members/{TARGET_USER_ID}")

        assert response.status_code == 204
        mock_remove.assert_called_once_with(
            organization_id=TEST_ORG_ID, user_id=TARGET_USER_ID
        )
        # Roles should be stripped after removal
        mock_sync_roles.assert_called_once_with(
            user_id=TARGET_USER_ID, target_roles=[]
        )

    def test_remove_self_forbidden(self, client):
        response = client.delete(f"/api/team/members/{MANAGER_USER_ID}")

        assert response.status_code == 400
        assert response.json()["detail"]["error"] == "self_removal_forbidden"
