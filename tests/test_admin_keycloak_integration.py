"""Integration tests for admin endpoints with fastapi-keycloak authentication.

Tests verify:
1. Role-based access control via fastapi-keycloak dependency injection
2. Automatic 401/403 responses for unauthorized/forbidden access
3. All role combinations (admin, admin.organizations, admin.workflows)
4. Proper access control for all admin endpoints

These are integration tests that mock the Keycloak JWT validation
to test the complete request/response cycle with different user roles.
"""

import pytest
from unittest.mock import patch
from fastapi.testclient import TestClient
from fastapi_keycloak import OIDCUser

from app.main import app


# Test client fixture
@pytest.fixture
def client():
    """Create FastAPI test client."""
    yield TestClient(app)


# Helper function to create a mock get_current_user that returns user with specific roles
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




class TestAdminEndpointsAccess:
    """Test admin endpoints require 'admin' role."""

    @patch("app.core.keycloak.idp.get_current_user")
    def test_admin_get_companies_with_admin_role(self, mock_get_user, client):
        """Admin role can access GET /admin/companies."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin"])

        response = client.get("/api/admin/companies")

        # Should succeed (200) or return valid response
        # Note: May be 500 if database not set up, but shouldn't be 403
        assert response.status_code != 403

    @patch("app.core.keycloak.idp.get_current_user")
    def test_admin_get_companies_without_admin_role(self, mock_get_user, client):
        """Regular user without admin role gets 401/403 on GET /admin/companies."""
        mock_get_user.side_effect = create_mock_get_current_user(["company.view"])

        response = client.get("/api/admin/companies")

        # Should be denied (401 or 403)
        assert response.status_code in [401, 403]

    @patch("app.core.keycloak.idp.get_current_user")
    def test_admin_get_companies_with_no_roles(self, mock_get_user, client):
        """User with no roles gets 401/403 on GET /admin/companies."""
        mock_get_user.side_effect = create_mock_get_current_user([])

        response = client.get("/api/admin/companies")

        assert response.status_code in [401, 403]

    @patch("app.core.keycloak.idp.get_current_user")
    def test_admin_get_company_by_id_with_admin_role(self, mock_get_user, client):
        """Admin role can access GET /admin/companies/{id}."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin"])

        response = client.get("/api/admin/companies/1")

        # Should not be forbidden (may be 404 if company doesn't exist)
        assert response.status_code != 403

    @patch("app.core.keycloak.idp.get_current_user")
    def test_admin_delete_company_with_admin_role(self, mock_get_user, client):
        """Admin role can access DELETE /admin/companies/{id}."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin"])

        response = client.delete("/api/admin/companies/1")

        # Should not be forbidden
        assert response.status_code != 403

    @patch("app.core.keycloak.idp.get_current_user")
    def test_admin_delete_company_without_admin_role(self, mock_get_user, client):
        """Organization admin without admin role gets 401/403 on DELETE."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        response = client.delete("/api/admin/companies/1")

        assert response.status_code in [401, 403]

    @patch("app.core.keycloak.idp.get_current_user")
    def test_admin_get_user_companies_with_admin_role(self, mock_get_user, client):
        """Admin role can access GET /admin/users/{username}/companies."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin"])

        response = client.get("/api/admin/users/testuser/companies")

        assert response.status_code != 403


class TestOrganizationAdminEndpointsAccess:
    """Test organization admin endpoints require 'admin.organizations' role."""

    @patch("app.core.keycloak.idp.get_current_user")
    def test_organization_modules_with_organization_admin_role(self, mock_get_user, client):
        """Organization admin can access GET /admin/organizations/{id}/modules."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        response = client.get("/api/admin/organizations/org-uuid-1/modules")

        # Should not be forbidden
        assert response.status_code != 403

    @patch("app.core.keycloak.idp.get_current_user")
    def test_organization_modules_without_organization_admin_role(self, mock_get_user, client):
        """Admin without admin.organizations role gets 401/403."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin"])

        response = client.get("/api/admin/organizations/org-uuid-1/modules")

        assert response.status_code in [401, 403]

    @patch("app.core.keycloak.idp.get_current_user")
    def test_update_organization_modules_with_organization_admin(self, mock_get_user, client):
        """Organization admin can access PUT /admin/organizations/{id}/modules."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        response = client.put("/api/admin/organizations/org-uuid-1/modules", json={})

        assert response.status_code != 403

    @patch("app.core.keycloak.idp.get_current_user")
    def test_update_organization_modules_without_role(self, mock_get_user, client):
        """Regular user gets 401/403 on PUT /admin/organizations/{id}/modules."""
        mock_get_user.side_effect = create_mock_get_current_user(["company.view"])

        response = client.put("/api/admin/organizations/org-uuid-1/modules", json={})

        assert response.status_code in [401, 403]

    @patch("app.core.keycloak.idp.get_current_user")
    def test_add_module_tokens_with_organization_admin(self, mock_get_user, client):
        """Organization admin can access POST /admin/organizations/{id}/modules/{module}/tokens."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        response = client.post("/api/admin/organizations/org-uuid-1/modules/chapse_assist/tokens", json={"tokens": 100})

        assert response.status_code != 403

    @patch("app.core.keycloak.idp.get_current_user")
    def test_toggle_module_with_organization_admin(self, mock_get_user, client):
        """Organization admin can access PUT /admin/organizations/{id}/modules/{module}/toggle."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        response = client.put("/api/admin/organizations/org-uuid-1/modules/chapse_assist/toggle")

        assert response.status_code != 403

    @patch("app.core.keycloak.idp.get_current_user")
    def test_fail_stuck_tasks_with_organization_admin(self, mock_get_user, client):
        """Organization admin can access POST /admin/tasks/fail-stuck."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        response = client.post("/api/admin/tasks/fail-stuck")

        assert response.status_code != 403

    @patch("app.core.keycloak.idp.get_current_user")
    def test_fail_stuck_tasks_without_organization_admin(self, mock_get_user, client):
        """Workflow admin without admin.organizations gets 401/403."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.workflows"])

        response = client.post("/api/admin/tasks/fail-stuck")

        assert response.status_code in [401, 403]


class TestWorkflowAdminEndpointsAccess:
    """Test workflow admin endpoints require 'admin.workflows' role."""

    @patch("app.core.keycloak.idp.get_current_user")
    def test_get_workflows_with_workflow_admin_role(self, mock_get_user, client):
        """Workflow admin can access GET /admin/workflows."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.workflows"])

        response = client.get("/api/admin/workflows")

        # Should not be forbidden
        assert response.status_code != 403

    @patch("app.core.keycloak.idp.get_current_user")
    def test_get_workflows_without_workflow_admin_role(self, mock_get_user, client):
        """Admin without admin.workflows role gets 401/403."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin"])

        response = client.get("/api/admin/workflows")

        assert response.status_code in [401, 403]

    @patch("app.core.keycloak.idp.get_current_user")
    def test_get_workflows_with_regular_user(self, mock_get_user, client):
        """Regular user gets 401/403 on GET /admin/workflows."""
        mock_get_user.side_effect = create_mock_get_current_user(["company.view"])

        response = client.get("/api/admin/workflows")

        assert response.status_code in [401, 403]

    @patch("app.core.keycloak.idp.get_current_user")
    def test_update_workflow_config_with_workflow_admin(self, mock_get_user, client):
        """Workflow admin can access PUT /admin/workflows/{task_type}."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.workflows"])

        response = client.put("/api/admin/workflows/data_collection", json={
            "workflow_id": "test-workflow-id"
        })

        assert response.status_code != 403

    @patch("app.core.keycloak.idp.get_current_user")
    def test_update_workflow_config_without_role(self, mock_get_user, client):
        """Organization admin without admin.workflows gets 401/403."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        response = client.put("/api/admin/workflows/data_collection", json={
            "workflow_id": "test-workflow-id"
        })

        assert response.status_code in [401, 403]


class TestCrossRoleAccess:
    """Test that roles don't grant access to endpoints they shouldn't."""

    @patch("app.core.keycloak.idp.get_current_user")
    def test_workflow_admin_cannot_access_admin_endpoints(self, mock_get_user, client):
        """Workflow admin cannot access general admin endpoints."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.workflows"])

        response = client.get("/api/admin/companies")

        assert response.status_code in [401, 403]

    @patch("app.core.keycloak.idp.get_current_user")
    def test_admin_cannot_access_organization_admin_endpoints(self, mock_get_user, client):
        """Admin without admin.organizations cannot access organization endpoints."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin"])

        response = client.get("/api/admin/organizations/org-uuid-1/modules")

        assert response.status_code in [401, 403]

    @patch("app.core.keycloak.idp.get_current_user")
    def test_organization_admin_cannot_access_workflow_endpoints(self, mock_get_user, client):
        """Organization admin cannot access workflow admin endpoints."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin.organizations"])

        response = client.get("/api/admin/workflows")

        assert response.status_code in [401, 403]


class TestMultipleRoles:
    """Test users with multiple admin roles have correct access."""

    @patch("app.core.keycloak.idp.get_current_user")
    def test_user_with_all_roles_has_full_access(self, mock_get_user, client):
        """User with all admin roles can access all admin endpoints."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin", "admin.organizations", "admin.workflows"])

        # Test access to each type of endpoint
        responses = [
            client.get("/api/admin/companies"),
            client.get("/api/admin/organizations/org-uuid-1/modules"),
            client.get("/api/admin/workflows"),
        ]

        # None should be forbidden
        for response in responses:
            assert response.status_code != 403

    @patch("app.core.keycloak.idp.get_current_user")
    def test_user_with_admin_and_organization_roles(self, mock_get_user, client):
        """User with admin + admin.organizations can access both endpoint types."""
        mock_get_user.side_effect = create_mock_get_current_user(["admin", "admin.organizations"])

        # Can access admin endpoints
        response1 = client.get("/api/admin/companies")
        assert response1.status_code != 403

        # Can access organization admin endpoints
        response2 = client.get("/api/admin/organizations/org-uuid-1/modules")
        assert response2.status_code != 403

        # Cannot access workflow admin endpoints
        response3 = client.get("/api/admin/workflows")
        assert response3.status_code in [401, 403]
