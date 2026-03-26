"""Tests for the Correlation ID middleware.

Covers:
- UUID generation when no header present
- UUID validation (only valid UUIDs are accepted from client)
- Header propagation when client sends valid X-Correlation-ID
- Response header injection
- Integration with proxy route headers
"""

import re
import uuid

import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from starlette.requests import Request
from starlette.testclient import TestClient

from app.core.correlation import (
    CORRELATION_HEADER,
    CorrelationIdMiddleware,
    get_or_create_correlation_id,
    _is_valid_uuid,
)

UUID_REGEX = re.compile(
    r"^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$",
    re.IGNORECASE,
)


class TestIsValidUuid:
    """Unit tests for UUID validation."""

    def test_valid_uuid_v4(self):
        assert _is_valid_uuid(str(uuid.uuid4())) is True

    def test_valid_uuid_v1(self):
        assert _is_valid_uuid(str(uuid.uuid1())) is True

    def test_valid_uuid_uppercase(self):
        assert _is_valid_uuid(str(uuid.uuid4()).upper()) is True

    def test_invalid_random_string(self):
        assert _is_valid_uuid("my-custom-trace-id-123") is False

    def test_invalid_empty(self):
        assert _is_valid_uuid("") is False

    def test_invalid_too_long(self):
        assert _is_valid_uuid("a" * 200) is False

    def test_invalid_partial_uuid(self):
        assert _is_valid_uuid("550e8400-e29b-41d4") is False

    def test_invalid_non_printable(self):
        assert _is_valid_uuid("550e8400\x00e29b-41d4-a716-446655440000") is False


class TestGetOrCreateCorrelationId:
    """Unit tests for get_or_create_correlation_id."""

    def test_generates_uuid_when_no_header(self):
        """Should generate a valid UUID v4 when no header is present."""
        request = MagicMock(spec=Request)
        request.headers = {}

        result = get_or_create_correlation_id(request)

        assert UUID_REGEX.match(result), f"Expected UUID v4, got: {result}"

    def test_returns_existing_valid_uuid(self):
        """Should return the existing header when it's a valid UUID."""
        existing_id = str(uuid.uuid4())
        request = MagicMock(spec=Request)
        request.headers = {CORRELATION_HEADER: existing_id}

        result = get_or_create_correlation_id(request)

        assert result == existing_id

    def test_rejects_non_uuid_and_generates_new(self):
        """Should reject non-UUID values and generate a new UUID."""
        request = MagicMock(spec=Request)
        request.headers = {CORRELATION_HEADER: "not-a-uuid"}

        result = get_or_create_correlation_id(request)

        assert UUID_REGEX.match(result), "Should generate a UUID v4 for invalid input"

    def test_rejects_malicious_long_value(self):
        """Should reject excessively long values."""
        request = MagicMock(spec=Request)
        request.headers = {CORRELATION_HEADER: "x" * 1000}

        result = get_or_create_correlation_id(request)

        assert UUID_REGEX.match(result)

    def test_generates_unique_ids(self):
        """Should generate unique IDs for different requests."""
        request = MagicMock(spec=Request)
        request.headers = {}

        ids = {get_or_create_correlation_id(request) for _ in range(100)}

        assert len(ids) == 100, "Generated IDs should be unique"

    def test_empty_header_generates_new_id(self):
        """Should generate a new ID when header is empty string."""
        request = MagicMock(spec=Request)
        request.headers = {CORRELATION_HEADER: ""}

        result = get_or_create_correlation_id(request)

        assert UUID_REGEX.match(result)


class TestCorrelationIdMiddleware:
    """Integration tests for the middleware using TestClient."""

    @pytest.fixture
    def test_app(self):
        """Create a minimal FastAPI app with the middleware."""
        from fastapi import FastAPI

        app = FastAPI()
        app.add_middleware(CorrelationIdMiddleware)

        @app.get("/test")
        async def test_endpoint(request: Request):
            return {"correlation_id": request.state.correlation_id}

        return app

    @pytest.fixture
    def client(self, test_app):
        return TestClient(test_app)

    def test_adds_correlation_id_to_response(self, client):
        """Response should always include X-Correlation-ID as UUID."""
        response = client.get("/test")

        assert response.status_code == 200
        assert CORRELATION_HEADER in response.headers
        assert UUID_REGEX.match(response.headers[CORRELATION_HEADER])

    def test_propagates_valid_uuid(self, client):
        """Should propagate a valid UUID from the client."""
        client_id = str(uuid.uuid4())
        response = client.get("/test", headers={CORRELATION_HEADER: client_id})

        assert response.status_code == 200
        assert response.headers[CORRELATION_HEADER] == client_id
        assert response.json()["correlation_id"] == client_id

    def test_rejects_invalid_header_and_generates_new(self, client):
        """Should ignore non-UUID header and generate a fresh UUID."""
        response = client.get(
            "/test", headers={CORRELATION_HEADER: "not-a-valid-uuid"}
        )

        assert response.status_code == 200
        returned_id = response.headers[CORRELATION_HEADER]
        assert UUID_REGEX.match(returned_id)
        assert returned_id != "not-a-valid-uuid"

    def test_stores_in_request_state(self, client):
        """Should store the correlation ID in request.state."""
        response = client.get("/test")

        body = response.json()
        assert "correlation_id" in body
        assert UUID_REGEX.match(body["correlation_id"])
        assert body["correlation_id"] == response.headers[CORRELATION_HEADER]

    def test_different_requests_get_different_ids(self, client):
        """Each request without a header should get a unique ID."""
        ids = set()
        for _ in range(10):
            response = client.get("/test")
            ids.add(response.headers[CORRELATION_HEADER])

        assert len(ids) == 10


class TestCorrelationIdInProxy:
    """Test that correlation ID is injected into proxy headers."""

    @pytest.mark.asyncio
    async def test_proxy_injects_correlation_id_in_backend_headers(self):
        """Proxy should add X-Correlation-ID to headers sent to backend."""
        from app.proxy.routes import proxy_request

        test_uuid = str(uuid.uuid4())

        mock_request = MagicMock(spec=Request)
        mock_request.method = "GET"
        mock_request.headers = {"accept": "application/json"}
        mock_request.url = MagicMock()
        mock_request.url.query = ""
        mock_request.state = MagicMock()
        mock_request.state.correlation_id = test_uuid

        captured_headers = {}

        mock_user = MagicMock()
        mock_user.preferred_username = "testuser"

        with (
            patch("app.proxy.routes.auth_middleware") as mock_auth,
            patch("app.proxy.routes.get_proxy_client") as mock_client_fn,
        ):
            mock_auth.validate_request = AsyncMock(
                return_value=(True, mock_user, {"Authorization": "Internal test"})
            )

            mock_client = AsyncMock()
            mock_client_fn.return_value = mock_client

            mock_response = MagicMock()
            mock_response.status_code = 200
            mock_response.headers = {"content-type": "application/json"}
            async def mock_aiter_bytes():
                yield b'{"ok":true}'

            mock_response.aiter_bytes = mock_aiter_bytes
            mock_response.aclose = AsyncMock()
            mock_client.send = AsyncMock(return_value=mock_response)

            def capture_build_request(**kwargs):
                captured_headers.update(kwargs.get("headers", {}))
                return MagicMock()

            mock_client.build_request = MagicMock(side_effect=capture_build_request)

            await proxy_request(mock_request, "test/path")

            assert CORRELATION_HEADER in captured_headers
            assert captured_headers[CORRELATION_HEADER] == test_uuid
