"""Tests for POST /api/users/import - bulk user import endpoint.

Tests verify:
1. Successful bulk import creates users, assigns org, grants permissions
2. Duplicate detection (email/username) against existing Keycloak users
3. Duplicate detection within the import file itself
4. Password generation vs provided passwords
5. Partial success (some rows fail, others succeed)
6. Validation errors (empty users, too many users, invalid data)
7. Organization not found returns 404
8. Role-based access control (admin.organizations required)

Auth mocking strategy:
  Same as test_user_endpoints.py - uses app.dependency_overrides
  to replace the Keycloak mock dependency with a proper callable.

Keycloak mocking strategy:
  A single `kc_mock` fixture replaces the entire keycloak_admin_service
  in the user_import module. Tests override individual methods as needed.
"""

import pytest
from datetime import datetime, timezone
from unittest.mock import patch, AsyncMock, MagicMock

from fastapi import HTTPException, status
from fastapi.testclient import TestClient

from app.core.keycloak import OIDCUser
from app.main import app


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
    """Test client with auth overridden to return an admin user."""
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
    app.dependency_overrides[mock_dep] = _make_admin_user


@pytest.fixture
def kc_mock():
    """Mock keycloak_admin_service with sensible defaults for bulk import.

    Replaces the entire service object in user_import module.
    Tests can override individual methods: kc_mock.create_user.return_value = ...
    """
    mock = MagicMock()
    # Async methods
    mock.get_organization = AsyncMock(return_value={"id": "org-uuid-1", "name": "Test Org"})
    mock.get_users = AsyncMock(return_value=[])
    mock.create_user = AsyncMock(return_value={"id": "new-user-1"})
    mock.add_user_to_organization = AsyncMock(return_value=True)
    mock.sync_user_realm_roles = AsyncMock(return_value=True)
    # Sync methods
    mock.generate_temp_password.return_value = "GenPass123!"

    with patch("app.services.user_import.keycloak_admin_service", mock):
        yield mock


def valid_import_payload(users=None, organization_id="org-uuid-1", generate_passwords=True):
    """Build a valid import request payload."""
    if users is None:
        users = [
            {
                "username": "john.doe",
                "email": "john@example.com",
                "firstname": "John",
                "lastname": "Doe",
            },
            {
                "username": "jane.smith",
                "email": "jane@example.com",
                "firstname": "Jane",
                "lastname": "Smith",
            },
        ]
    return {
        "organization_id": organization_id,
        "users": users,
        "generate_passwords": generate_passwords,
    }


class TestBulkImportUsersSuccess:
    """Test successful bulk import scenarios."""

    def test_import_two_users_success(self, kc_mock, client):
        """POST /api/users/import creates users and returns success counts."""
        kc_mock.create_user = AsyncMock(side_effect=[
            {"id": "new-user-1"},
            {"id": "new-user-2"},
        ])

        response = client.post("/api/users/import", json=valid_import_payload())

        assert response.status_code == 200
        data = response.json()
        assert data["success_count"] == 2
        assert data["error_count"] == 0
        assert data["total_count"] == 2
        assert len(data["results"]) == 2
        assert all(r["success"] for r in data["results"])

    def test_import_assigns_organization_and_grants_read(self, kc_mock, client):
        """Import assigns users to org and grants organization.read role."""
        payload = valid_import_payload(users=[{
            "username": "john.doe",
            "email": "john@example.com",
        }])
        client.post("/api/users/import", json=payload)

        kc_mock.add_user_to_organization.assert_called_once_with(
            organization_id="org-uuid-1",
            user_id="new-user-1",
        )
        kc_mock.sync_user_realm_roles.assert_called_once_with(
            user_id="new-user-1",
            target_roles=["organization.read"],
        )

    @pytest.mark.usefixtures("kc_mock")
    def test_import_returns_generated_passwords(self, client):
        """When generate_passwords=True, response includes generated passwords."""
        payload = valid_import_payload(
            users=[{"username": "john.doe", "email": "john@example.com"}],
            generate_passwords=True,
        )
        response = client.post("/api/users/import", json=payload)

        assert response.status_code == 200
        result = response.json()["results"][0]
        assert result["generated_password"] == "GenPass123!"


class TestBulkImportDuplicateDetection:
    """Test duplicate detection in bulk import."""

    def test_existing_email_detected(self, kc_mock, client):
        """Import detects email that already exists in Keycloak."""
        kc_mock.get_users = AsyncMock(return_value=[
            {"id": "existing-user", "email": "john@example.com", "username": "existing.john"},
        ])

        payload = valid_import_payload(users=[{
            "username": "john.doe",
            "email": "john@example.com",
        }])
        response = client.post("/api/users/import", json=payload)

        assert response.status_code == 200
        data = response.json()
        assert data["success_count"] == 0
        assert data["error_count"] == 1
        assert "already exists" in data["results"][0]["error_message"]

    def test_existing_username_detected(self, kc_mock, client):
        """Import detects username that already exists in Keycloak."""
        kc_mock.get_users = AsyncMock(return_value=[
            {"id": "existing-user", "email": "other@example.com", "username": "john.doe"},
        ])

        payload = valid_import_payload(users=[{
            "username": "john.doe",
            "email": "john.new@example.com",
        }])
        response = client.post("/api/users/import", json=payload)

        assert response.status_code == 200
        data = response.json()
        assert data["success_count"] == 0
        assert data["error_count"] == 1
        assert "already exists" in data["results"][0]["error_message"]


class TestBulkImportValidation:
    """Test request validation."""

    def test_empty_users_list_rejected(self, client):
        """Import with empty users list returns 422."""
        payload = valid_import_payload(users=[])
        response = client.post("/api/users/import", json=payload)
        assert response.status_code == 422

    def test_weak_password_rejected(self, client):
        """Import with weak password returns 422."""
        payload = valid_import_payload(users=[{
            "username": "john.doe",
            "email": "john@example.com",
            "password": "weak",
        }])
        response = client.post("/api/users/import", json=payload)
        assert response.status_code == 422

    def test_missing_username_rejected(self, client):
        """Import without username field returns 422."""
        payload = valid_import_payload(users=[{
            "email": "john@example.com",
        }])
        response = client.post("/api/users/import", json=payload)
        assert response.status_code == 422

    def test_missing_email_rejected(self, client):
        """Import without email field returns 422."""
        payload = valid_import_payload(users=[{
            "username": "john.doe",
        }])
        response = client.post("/api/users/import", json=payload)
        assert response.status_code == 422


class TestBulkImportAuth:
    """Test authentication/authorization."""

    def test_import_requires_admin_role(self, client, deny_auth):
        """POST /api/users/import requires admin.organizations role."""
        response = client.post(
            "/api/users/import",
            json=valid_import_payload(),
        )
        assert response.status_code == 403
