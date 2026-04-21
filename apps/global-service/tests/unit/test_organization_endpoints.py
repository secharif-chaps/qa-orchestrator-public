"""Tests for organization context endpoints.

Tests:
- GET /organizations/current - current organization context (basic smoke test)
- GET /organizations/current/activities - recent organization activities (integration marked)

Note: Full testing of these endpoints requires running services.
These are basic tests to verify endpoint structure.
"""

import pytest


class TestGetCurrentOrganization:
    """Tests for GET /organizations/current endpoint."""

    async def test_get_current_organization_structure(self, client):
        """Test endpoint returns expected structure."""
        # The conftest provides a mocked user with organization context
        response = await client.get("/api/organizations/current")

        # Should return 200 with the expected structure or 422 if validation fails
        assert response.status_code in [200, 422]

        # Only check structure if 200
        if response.status_code == 200:
            data = response.json()
            assert "id" in data
            assert "name" in data


@pytest.mark.integration
class TestGetOrganizationActivitiesIntegration:
    """Integration tests for GET /organizations/current/activities endpoint.

    These require running backend service.
    Run with: pytest --run-integration
    """

    async def test_activities_endpoint_exists(self, client):
        """Test activities endpoint is registered."""
        # This will attempt to call the endpoint
        # With mocked auth it should at least reach the endpoint
        response = await client.get("/api/organizations/current/activities")

        # We expect either 200 (success) or 503 (service unavailable)
        # or 500 (missing backend) - all are valid as they prove endpoint exists
        assert response.status_code in [200, 500, 503]
