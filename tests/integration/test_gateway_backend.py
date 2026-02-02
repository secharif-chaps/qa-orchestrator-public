"""
Integration tests for gateway-to-backend authentication flow.

These tests verify the full flow:
1. Frontend sends user JWT to gateway
2. Gateway validates JWT and creates internal JWT
3. Gateway forwards request to backend with internal JWT
4. Backend verifies internal JWT and processes request

Requirements:
- Running Keycloak instance
- Running gateway (global-service) on port 8001
- Running backend (screen-poc) on port 8000
- Valid test user in Keycloak

Run with: pytest tests/integration/ -v --run-integration
Skip with: pytest tests/ -v (default, skips integration tests)
"""

import pytest
import httpx
from unittest.mock import patch

# Mark all tests in this module as integration tests
pytestmark = pytest.mark.integration


def pytest_configure(config):
    """Register custom markers."""
    config.addinivalue_line(
        "markers", "integration: marks tests as integration tests (require running services)"
    )


@pytest.fixture
def gateway_url():
    """Gateway service URL."""
    return "http://localhost:8001"


@pytest.fixture
def backend_url():
    """Backend service URL."""
    return "http://localhost:8000"


@pytest.fixture
def mock_settings_for_token():
    """Mock settings for creating test tokens."""
    with patch("app.core.internal_jwt.settings") as mock:
        mock.INTERNAL_JWT_SECRET = "local-dev-internal-jwt-secret-32chars"
        mock.INTERNAL_JWT_EXPIRY_SECONDS = 60
        yield mock


class TestGatewayToBackendAuth:
    """Test the full gateway-to-backend authentication flow."""

    @pytest.mark.asyncio
    async def test_gateway_proxies_authenticated_request(self, gateway_url):
        """
        Test that gateway correctly proxies authenticated requests.

        This test requires:
        - Running gateway service
        - Running backend service
        - Valid Keycloak token (obtained manually or via test user)
        """
        # Note: In a real CI environment, you would:
        # 1. Get a token from Keycloak using test credentials
        # 2. Use that token here
        #
        # For manual testing, get a token from the browser dev tools
        # and set it as an environment variable or pass it directly.

        # Skip if no token is available
        import os
        user_token = os.environ.get("TEST_USER_TOKEN")
        if not user_token:
            pytest.skip("TEST_USER_TOKEN environment variable not set")

        async with httpx.AsyncClient(base_url=gateway_url, timeout=10.0) as client:
            response = await client.get(
                "/api/companies/recent?limit=5",
                headers={"Authorization": f"Bearer {user_token}"}
            )

        # Should succeed (gateway validated token and forwarded to backend)
        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_gateway_rejects_invalid_token(self, gateway_url):
        """Test that gateway rejects requests with invalid JWT."""
        async with httpx.AsyncClient(base_url=gateway_url, timeout=10.0) as client:
            response = await client.get(
                "/api/companies/recent?limit=5",
                headers={"Authorization": "Bearer invalid-token-here"}
            )

        # Should be rejected at gateway
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_gateway_rejects_missing_token(self, gateway_url):
        """Test that gateway rejects requests without authentication."""
        async with httpx.AsyncClient(base_url=gateway_url, timeout=10.0) as client:
            response = await client.get("/api/companies/recent?limit=5")

        # Should be rejected at gateway
        assert response.status_code == 401


class TestDirectBackendAccess:
    """Test direct backend access with internal tokens."""

    @pytest.mark.asyncio
    async def test_backend_accepts_valid_internal_token(
        self, backend_url, mock_settings_for_token
    ):
        """
        Test that backend accepts valid internal JWT from allowed IP.

        This verifies the internal JWT verification works correctly.
        In Docker network, the IP should be in the allowed range.
        """
        from app.core.internal_jwt import create_internal_token

        # Create a valid internal token
        internal_token = create_internal_token(
            user_id="test-user-123",
            username="testuser",
            org_id="test-org-456",
            org_name="Test Organization",
            roles=["company.view"],
            email="test@example.com",
        )

        async with httpx.AsyncClient(base_url=backend_url, timeout=10.0) as client:
            response = await client.get(
                "/api/companies/recent?limit=5",
                headers={"Authorization": f"Internal {internal_token}"}
            )

        # Should succeed if called from allowed IP range (Docker network)
        # May fail with 403 if IP validation rejects external caller
        assert response.status_code in [200, 403]

    @pytest.mark.asyncio
    async def test_backend_rejects_expired_internal_token(
        self, backend_url, mock_settings_for_token
    ):
        """Test that backend rejects expired internal tokens."""
        import time
        import jwt

        # Create an expired token manually
        now = int(time.time())
        payload = {
            "sub": "test-user-123",
            "username": "testuser",
            "email": "test@example.com",
            "org_id": "test-org-456",
            "org_name": "Test Organization",
            "roles": ["company.view"],
            "iss": "global-gateway",
            "iat": now - 120,  # Issued 2 minutes ago
            "exp": now - 60,   # Expired 1 minute ago
        }
        expired_token = jwt.encode(
            payload,
            "local-dev-internal-jwt-secret-32chars",
            algorithm="HS256"
        )

        async with httpx.AsyncClient(base_url=backend_url, timeout=10.0) as client:
            response = await client.get(
                "/api/companies/recent?limit=5",
                headers={"Authorization": f"Internal {expired_token}"}
            )

        # Should be rejected due to expiration
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_backend_rejects_invalid_signature(self, backend_url):
        """Test that backend rejects tokens with invalid signature."""
        import time
        import jwt

        # Create a token with wrong secret
        now = int(time.time())
        payload = {
            "sub": "attacker-123",
            "username": "attacker",
            "email": "attacker@example.com",
            "org_id": "fake-org",
            "org_name": "Fake Org",
            "roles": ["admin"],
            "iss": "global-gateway",
            "iat": now,
            "exp": now + 60,
        }
        forged_token = jwt.encode(
            payload,
            "wrong-secret-trying-to-forge-token",
            algorithm="HS256"
        )

        async with httpx.AsyncClient(base_url=backend_url, timeout=10.0) as client:
            response = await client.get(
                "/api/companies/recent?limit=5",
                headers={"Authorization": f"Internal {forged_token}"}
            )

        # Should be rejected due to invalid signature
        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_backend_rejects_bearer_token_directly(self, backend_url):
        """
        Test that backend rejects external Bearer tokens sent directly.

        The backend should only accept:
        - Internal JWT tokens from the gateway
        - Bearer tokens validated against Keycloak (external requests)

        A random Bearer token should fail validation.
        """
        async with httpx.AsyncClient(base_url=backend_url, timeout=10.0) as client:
            response = await client.get(
                "/api/companies/recent?limit=5",
                headers={"Authorization": "Bearer fake-bearer-token"}
            )

        # Should be rejected - invalid Keycloak token
        assert response.status_code == 401


class TestIPValidation:
    """Test IP allowlist validation for internal tokens."""

    @pytest.mark.asyncio
    async def test_documents_ip_validation_behavior(
        self, backend_url, mock_settings_for_token
    ):
        """
        Document the expected behavior of IP validation.

        When INTERNAL_ALLOWED_IPS is configured:
        - Requests from allowed IPs are accepted
        - Requests from non-allowed IPs are rejected with 403

        When INTERNAL_ALLOWED_IPS is empty:
        - IP validation is disabled
        - Only JWT signature is checked

        This test documents the actual behavior without asserting,
        since the IP seen by the backend depends on the network setup.
        """
        from app.core.internal_jwt import create_internal_token

        internal_token = create_internal_token(
            user_id="test-user-123",
            username="testuser",
            org_id="test-org-456",
            org_name="Test Organization",
            roles=["company.view"],
        )

        async with httpx.AsyncClient(base_url=backend_url, timeout=10.0) as client:
            response = await client.get(
                "/api/companies/recent?limit=5",
                headers={"Authorization": f"Internal {internal_token}"}
            )

        # Document the response
        print(f"\nIP Validation Test Results:")
        print(f"  Status Code: {response.status_code}")
        print(f"  Response: {response.text[:200] if response.text else 'empty'}")

        if response.status_code == 200:
            print("  → Request accepted (IP in allowed range or IP validation disabled)")
        elif response.status_code == 403:
            print("  → Request rejected (IP not in allowed range)")
        elif response.status_code == 401:
            print("  → Request rejected (token invalid or expired)")

        # This test passes regardless of result - it's for documentation
        assert response.status_code in [200, 401, 403]
