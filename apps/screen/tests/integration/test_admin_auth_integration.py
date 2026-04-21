"""Integration tests for admin endpoints with role-based access control.

Tests verify:
1. Role-based access control via get_current_user dependency injection
2. Automatic 401/403 responses for unauthorized/forbidden access
3. Proper access control for all admin endpoints

These are integration tests that use monkeypatch to replace verify_internal_jwt
and test the complete request/response cycle with different user roles.
"""

from unittest.mock import patch

import pytest
from fastapi.testclient import TestClient

from app.core.auth import AuthenticatedUser
from app.main import app

pytestmark = pytest.mark.integration

INTERNAL_AUTH_HEADER = {"Authorization": "Internal fake-token-for-test"}


@pytest.fixture
def client():
    """Create FastAPI test client."""
    yield TestClient(app)


def _make_user(roles: list[str]) -> AuthenticatedUser:
    """Create an AuthenticatedUser with specified roles."""
    label = "-".join(roles) if roles else "none"
    return AuthenticatedUser(
        sub=f"test-uuid-{label}",
        preferred_username=f"test_user_{label}",
        email="test@example.com",
        roles=roles,
        org_id="test-org-uuid",
        org_name="TestOrg",
    )


def _patch_auth(roles: list[str]):
    """Patch verify_internal_jwt to return an AuthenticatedUser with specified roles.

    The get_current_user() dependency calls verify_internal_jwt() internally.
    By patching verify_internal_jwt at the module level, all dependency instances
    (regardless of required_roles) will use the mocked user. Role checking
    still happens in get_current_user's closure, so RBAC is properly tested.

    Requests must include `Authorization: Internal ...` header so that
    is_internal_request() returns True before verify_internal_jwt is called.
    """
    user = _make_user(roles)
    return patch("app.core.auth.verify_internal_jwt", return_value=user)


class TestOrganizationAdminEndpointsAccess:
    """Test organization admin endpoints require 'admin.organizations' role."""

    def test_fail_stuck_tasks_with_organization_admin(self, client):
        """Organization admin can access POST /admin/tasks/fail-stuck."""
        with _patch_auth(["admin.organizations"]):
            response = client.post("/api/admin/tasks/fail-stuck", headers=INTERNAL_AUTH_HEADER)

        # Should not be forbidden (may fail for other reasons like DB)
        assert response.status_code != 403

    def test_fail_stuck_tasks_without_organization_admin(self, client):
        """Workflow admin without admin.organizations gets 403."""
        with _patch_auth(["admin.workflows"]):
            response = client.post("/api/admin/tasks/fail-stuck", headers=INTERNAL_AUTH_HEADER)

        assert response.status_code == 403

    def test_fail_stuck_tasks_with_regular_user(self, client):
        """Regular user gets 403 on POST /admin/tasks/fail-stuck."""
        with _patch_auth(["company.view"]):
            response = client.post("/api/admin/tasks/fail-stuck", headers=INTERNAL_AUTH_HEADER)

        assert response.status_code == 403

    def test_fail_stuck_tasks_with_no_roles(self, client):
        """User with no roles gets 403 on POST /admin/tasks/fail-stuck."""
        with _patch_auth([]):
            response = client.post("/api/admin/tasks/fail-stuck", headers=INTERNAL_AUTH_HEADER)

        assert response.status_code == 403

    def test_fail_stuck_tasks_without_auth_header(self, client):
        """Request without Internal auth header gets 401."""
        response = client.post("/api/admin/tasks/fail-stuck")

        assert response.status_code == 401


class TestCrossRoleAccess:
    """Test that roles don't grant access to endpoints they shouldn't."""

    def test_regular_user_cannot_access_admin_endpoint(self, client):
        """Regular user gets 403 on admin endpoints."""
        with _patch_auth(["company.view"]):
            response = client.post("/api/admin/tasks/fail-stuck", headers=INTERNAL_AUTH_HEADER)
        assert response.status_code == 403


class TestMultipleRoles:
    """Test users with multiple admin roles have correct access."""

    def test_user_with_organization_role_has_access(self, client):
        """User with admin.organizations can access fail-stuck endpoint."""
        with _patch_auth(["admin.organizations"]):
            response = client.post("/api/admin/tasks/fail-stuck", headers=INTERNAL_AUTH_HEADER)
        assert response.status_code != 403
