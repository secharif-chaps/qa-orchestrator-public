"""Tests for the health check endpoints.

Covers:
- /health/live returns 200 with status alive
- /health/ready returns 200 with status, openapi_hash, version
- openapi_hash is a 16-char hex string
- openapi_hash is deterministic (cached)
- Endpoints are accessible without authentication
"""

import re

import pytest
from fastapi.testclient import TestClient

from app.main import app

HEX16_REGEX = re.compile(r"^[0-9a-f]{16}$")


@pytest.fixture
def client():
    """Create a test client for the stream app."""
    return TestClient(app)


class TestHealthLive:
    """Tests for GET /health/live."""

    def test_returns_200(self, client):
        response = client.get("/health/live")
        assert response.status_code == 200

    def test_returns_alive_status(self, client):
        response = client.get("/health/live")
        assert response.json() == {"status": "alive"}

    def test_accessible_without_auth(self, client):
        """Should not require any authentication headers."""
        response = client.get("/health/live")
        assert response.status_code == 200


class TestHealthReady:
    """Tests for GET /health/ready."""

    def test_returns_200(self, client):
        response = client.get("/health/ready")
        assert response.status_code == 200

    def test_returns_required_fields(self, client):
        data = client.get("/health/ready").json()
        assert "status" in data
        assert "openapi_hash" in data
        assert "version" in data

    def test_status_is_ready(self, client):
        data = client.get("/health/ready").json()
        assert data["status"] == "ready"

    def test_openapi_hash_is_16_char_hex(self, client):
        data = client.get("/health/ready").json()
        assert HEX16_REGEX.match(data["openapi_hash"]), f"Expected 16-char hex string, got: {data['openapi_hash']}"

    def test_openapi_hash_is_deterministic(self, client):
        """Multiple calls should return the same hash (cached)."""
        hash1 = client.get("/health/ready").json()["openapi_hash"]
        hash2 = client.get("/health/ready").json()["openapi_hash"]
        assert hash1 == hash2

    def test_version_is_string(self, client):
        data = client.get("/health/ready").json()
        assert isinstance(data["version"], str)
        assert len(data["version"]) > 0

    def test_accessible_without_auth(self, client):
        """Should not require any authentication headers."""
        response = client.get("/health/ready")
        assert response.status_code == 200


class TestHealthHashCache:
    """Tests for the OpenAPI hash caching mechanism."""

    @pytest.fixture(autouse=True)
    def _reset_cache(self):
        """Reset the hash cache before and after each test."""
        from app.api.endpoints import health as health_mod

        health_mod._cached_hash = None
        yield
        health_mod._cached_hash = None

    def test_cache_returns_same_value(self):
        """_get_openapi_hash should return cached value on repeated calls."""
        from app.api.endpoints import health as health_mod

        mock_app = type("MockApp", (), {"openapi": lambda self: {"openapi": "3.0.0", "paths": {}}})()

        hash1 = health_mod._get_openapi_hash(mock_app)
        hash2 = health_mod._get_openapi_hash(mock_app)

        assert hash1 == hash2
        assert HEX16_REGEX.match(hash1)

    def test_hash_is_content_based(self):
        """_get_openapi_hash should produce different hashes for different schemas."""
        from app.api.endpoints import health as health_mod

        schema1 = {"openapi": "3.0.0", "paths": {}}
        schema2 = {"openapi": "3.0.0", "paths": {"/new": {}}}

        mock_app1 = type("MockApp", (), {"openapi": lambda self: schema1})()
        hash1 = health_mod._get_openapi_hash(mock_app1)

        # Reset cache to compute for a different schema
        health_mod._cached_hash = None
        mock_app2 = type("MockApp", (), {"openapi": lambda self: schema2})()
        hash2 = health_mod._get_openapi_hash(mock_app2)

        assert hash1 != hash2
