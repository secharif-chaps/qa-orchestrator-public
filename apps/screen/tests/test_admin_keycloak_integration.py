"""Integration tests for admin endpoints with fastapi-keycloak authentication.

Tests verify:
1. Role-based access control via fastapi-keycloak dependency injection
2. Automatic 401/403 responses for unauthorized/forbidden access
3. All role combinations (admin.organizations, admin.workflows)
4. Proper access control for all admin endpoints

These are integration tests that use FastAPI dependency overrides
to test the complete request/response cycle with different user roles.
"""

import time
from unittest.mock import MagicMock

import pytest
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
    """Patch idp._wrapped to simulate Keycloak JWT validation with role checking.

    The InternalTrustIDPWrapper.get_current_user() is called at route definition
    time, producing a closure that FastAPI stores as the dependency. That closure
    calls self._wrapped.get_current_user(required_roles=...) for non-internal
    requests. We mock _wrapped so this call returns a dependency that validates
    roles and produces an OIDCUser.
    """
    from fastapi import HTTPException, status

    user = _make_user(roles)
    mock_wrapped = MagicMock()

    def mock_get_current_user(required_roles=None):
        """Mimic Keycloak's get_current_user with role enforcement."""
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


class TestWorkflowAdminEndpointsAccess:
    """Test workflow admin endpoints require 'admin.workflows' role."""

    def test_get_workflows_with_workflow_admin_role(self, monkeypatch, client):
        """Workflow admin can access GET /admin/workflows."""
        _setup_auth(monkeypatch, ["admin.workflows"])

        response = client.get("/api/admin/workflows")

        # Should not be forbidden (may be 500 if DB not available)
        assert response.status_code != 403

    def test_get_workflows_without_workflow_admin_role(self, monkeypatch, client):
        """Admin without admin.workflows role gets 403."""
        _setup_auth(monkeypatch, ["admin"])

        response = client.get("/api/admin/workflows")

        assert response.status_code == 403

    def test_get_workflows_with_regular_user(self, monkeypatch, client):
        """Regular user gets 403 on GET /admin/workflows."""
        _setup_auth(monkeypatch, ["company.view"])

        response = client.get("/api/admin/workflows")

        assert response.status_code == 403

    def test_get_workflows_with_no_roles(self, monkeypatch, client):
        """User with no roles gets 403 on GET /admin/workflows."""
        _setup_auth(monkeypatch, [])

        response = client.get("/api/admin/workflows")

        assert response.status_code in [401, 403]

    def test_update_workflow_config_with_workflow_admin(self, monkeypatch, client):
        """Workflow admin can access PUT /admin/workflows/{task_type}."""
        _setup_auth(monkeypatch, ["admin.workflows"])

        response = client.put(
            "/api/admin/workflows/data_collection",
            json={"workflow_id": "test-workflow-id"},
        )

        assert response.status_code != 403

    def test_update_workflow_config_without_role(self, monkeypatch, client):
        """Organization admin without admin.workflows gets 403."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        response = client.put(
            "/api/admin/workflows/data_collection",
            json={"workflow_id": "test-workflow-id"},
        )

        assert response.status_code == 403


class TestOrganizationAdminEndpointsAccess:
    """Test organization admin endpoints require 'admin.organizations' role."""

    def test_fail_stuck_tasks_with_organization_admin(self, monkeypatch, client):
        """Organization admin can access POST /admin/tasks/fail-stuck."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        response = client.post("/api/admin/tasks/fail-stuck")

        # Should not be forbidden (may fail for other reasons like DB/RabbitMQ)
        assert response.status_code != 403

    def test_fail_stuck_tasks_without_organization_admin(self, monkeypatch, client):
        """Workflow admin without admin.organizations gets 403."""
        _setup_auth(monkeypatch, ["admin.workflows"])

        response = client.post("/api/admin/tasks/fail-stuck")

        assert response.status_code == 403

    def test_fail_stuck_tasks_with_regular_user(self, monkeypatch, client):
        """Regular user gets 403 on POST /admin/tasks/fail-stuck."""
        _setup_auth(monkeypatch, ["company.view"])

        response = client.post("/api/admin/tasks/fail-stuck")

        assert response.status_code in [401, 403]

    def test_fail_stuck_tasks_with_no_roles(self, monkeypatch, client):
        """User with no roles gets 403 on POST /admin/tasks/fail-stuck."""
        _setup_auth(monkeypatch, [])

        response = client.post("/api/admin/tasks/fail-stuck")

        assert response.status_code == 403


class TestCrossRoleAccess:
    """Test that roles don't grant access to endpoints they shouldn't."""

    def test_workflow_admin_cannot_access_organization_endpoints(self, monkeypatch, client):
        """Workflow admin cannot access organization admin endpoints."""
        _setup_auth(monkeypatch, ["admin.workflows"])

        response = client.post("/api/admin/tasks/fail-stuck")

        assert response.status_code == 403

    def test_organization_admin_cannot_access_workflow_endpoints(self, monkeypatch, client):
        """Organization admin cannot access workflow admin endpoints."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        response = client.get("/api/admin/workflows")

        assert response.status_code in [401, 403]

    def test_regular_user_cannot_access_any_admin_endpoint(self, monkeypatch, client):
        """Regular user gets 403 on all admin endpoints."""
        _setup_auth(monkeypatch, ["company.view"])

        responses = [
            client.get("/api/admin/workflows"),
            client.post("/api/admin/tasks/fail-stuck"),
        ]

        for response in responses:
            assert response.status_code in [401, 403]


class TestMultipleRoles:
    """Test users with multiple admin roles have correct access."""

    def test_user_with_all_roles_has_full_access(self, monkeypatch, client):
        """User with all admin roles can access all admin endpoints."""
        _setup_auth(monkeypatch, ["admin.organizations", "admin.workflows"])

        responses = [
            client.get("/api/admin/workflows"),
            client.post("/api/admin/tasks/fail-stuck"),
        ]

        for response in responses:
            assert response.status_code != 403

    def test_user_with_only_workflow_role(self, monkeypatch, client):
        """User with only admin.workflows can access workflows but not tasks."""
        _setup_auth(monkeypatch, ["admin.workflows"])

        # Can access workflow endpoints
        response1 = client.get("/api/admin/workflows")
        assert response1.status_code != 403

        # Cannot access organization admin endpoints
        response2 = client.post("/api/admin/tasks/fail-stuck")
        assert response2.status_code == 403

    def test_user_with_only_organization_role(self, monkeypatch, client):
        """User with only admin.organizations can access tasks but not workflows."""
        _setup_auth(monkeypatch, ["admin.organizations"])

        # Can access organization admin endpoints
        response1 = client.post("/api/admin/tasks/fail-stuck")
        assert response1.status_code != 403

        # Cannot access workflow endpoints
        response2 = client.get("/api/admin/workflows")
        assert response2.status_code == 403
