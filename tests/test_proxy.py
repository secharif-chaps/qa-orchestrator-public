"""
Tests for the REST proxy functionality.

Tests cover:
1. Basic request forwarding (GET, POST, PUT, DELETE)
2. Header preservation
3. Query parameter preservation
4. Response status and body preservation
5. Error handling (timeout, connection error)
6. SSE streaming responses
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch
import httpx


class TestProxyClient:
    """Tests for the proxy client module."""

    @pytest.mark.asyncio
    async def test_get_proxy_client_creates_client(self):
        """Test that get_proxy_client creates an httpx client."""
        from app.proxy.client import get_proxy_client, close_proxy_client, _client

        # Reset client state
        import app.proxy.client as client_module
        client_module._client = None

        with patch("app.proxy.client.settings") as mock_settings:
            mock_settings.BACKEND_BASE_URL = "http://test-backend:8000"

            client = await get_proxy_client()

            assert client is not None
            assert isinstance(client, httpx.AsyncClient)

            # Cleanup
            await close_proxy_client()

    @pytest.mark.asyncio
    async def test_close_proxy_client(self):
        """Test that close_proxy_client closes the client."""
        from app.proxy.client import get_proxy_client, close_proxy_client
        import app.proxy.client as client_module

        # Reset and create client
        client_module._client = None

        with patch("app.proxy.client.settings") as mock_settings:
            mock_settings.BACKEND_BASE_URL = "http://test-backend:8000"

            await get_proxy_client()
            assert client_module._client is not None

            await close_proxy_client()
            assert client_module._client is None


class TestProxyRoutes:
    """Tests for the proxy routes."""

    def test_filter_request_headers(self):
        """Test that hop-by-hop headers are filtered from requests."""
        from app.proxy.routes import filter_request_headers

        headers = {
            "Authorization": "Bearer token",
            "Content-Type": "application/json",
            "Host": "localhost",  # Should be filtered
            "Connection": "keep-alive",  # Should be filtered
            "X-Custom-Header": "value",
        }

        filtered = filter_request_headers(headers)

        assert "Authorization" in filtered
        assert "Content-Type" in filtered
        assert "X-Custom-Header" in filtered
        assert "Host" not in filtered
        assert "Connection" not in filtered

    def test_filter_response_headers(self):
        """Test that hop-by-hop headers are filtered from responses."""
        from app.proxy.routes import filter_response_headers

        headers = httpx.Headers({
            "Content-Type": "application/json",
            "X-Custom-Header": "value",
            "Connection": "keep-alive",  # Should be filtered
            "Transfer-Encoding": "chunked",  # Should be filtered
        })

        filtered = filter_response_headers(headers)

        # httpx normalizes header names to lowercase
        assert "content-type" in filtered
        assert "x-custom-header" in filtered
        assert "connection" not in filtered
        assert "transfer-encoding" not in filtered

    def test_is_sse_request(self):
        """Test SSE request detection."""
        from app.proxy.routes import is_sse_request

        # Mock request with SSE accept header
        mock_request = MagicMock()
        mock_request.headers = {"accept": "text/event-stream"}
        assert is_sse_request(mock_request) is True

        # Mock request without SSE accept header
        mock_request.headers = {"accept": "application/json"}
        assert is_sse_request(mock_request) is False

    def test_is_sse_response(self):
        """Test SSE response detection."""
        from app.proxy.routes import is_sse_response

        # Mock SSE response
        mock_response = MagicMock()
        mock_response.headers = {"content-type": "text/event-stream"}
        assert is_sse_response(mock_response) is True

        # Mock non-SSE response
        mock_response.headers = {"content-type": "application/json"}
        assert is_sse_response(mock_response) is False


class TestProxyIntegration:
    """Integration tests for the proxy (mocked backend)."""

    def _mock_auth_success(self):
        """Helper to create auth mock that returns success."""
        from app.core.auth_middleware import GatewayUser, INTERNAL_REQUEST_HEADER, INTERNAL_REQUEST_SECRET
        mock_user = GatewayUser(sub="user-123", preferred_username="testuser")
        return (True, mock_user, {INTERNAL_REQUEST_HEADER: INTERNAL_REQUEST_SECRET})

    @pytest.mark.asyncio
    async def test_proxy_get_request(self):
        """Test GET request proxying."""
        from fastapi.testclient import TestClient
        from app.main import app

        # Mock the proxy client
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.content = b'{"data": "test"}'
        mock_response.headers = httpx.Headers({"content-type": "application/json"})

        with patch("app.proxy.routes.auth_middleware.validate_request") as mock_auth:
            mock_auth.return_value = self._mock_auth_success()

            with patch("app.proxy.routes.get_proxy_client") as mock_get_client:
                mock_client = AsyncMock()
                mock_client.request = AsyncMock(return_value=mock_response)
                mock_get_client.return_value = mock_client

                # Use TestClient for sync testing
                with TestClient(app) as client:
                    response = client.get("/api/test/endpoint")

                    assert response.status_code == 200
                    assert response.json() == {"data": "test"}

    @pytest.mark.asyncio
    async def test_proxy_post_request_with_body(self):
        """Test POST request with body proxying."""
        from fastapi.testclient import TestClient
        from app.main import app

        mock_response = MagicMock()
        mock_response.status_code = 201
        mock_response.content = b'{"id": 1}'
        mock_response.headers = httpx.Headers({"content-type": "application/json"})

        with patch("app.proxy.routes.auth_middleware.validate_request") as mock_auth:
            mock_auth.return_value = self._mock_auth_success()

            with patch("app.proxy.routes.get_proxy_client") as mock_get_client:
                mock_client = AsyncMock()
                mock_client.request = AsyncMock(return_value=mock_response)
                mock_get_client.return_value = mock_client

                with TestClient(app) as client:
                    response = client.post(
                        "/api/companies",
                        json={"name": "Test Company"},
                        headers={"Authorization": "Bearer token"},
                    )

                    assert response.status_code == 201
                    assert response.json() == {"id": 1}

                    # Verify the request was made correctly
                    call_args = mock_client.request.call_args
                    assert call_args.kwargs["method"] == "POST"
                    assert "/api/companies" in call_args.kwargs["url"]

    @pytest.mark.asyncio
    async def test_proxy_preserves_query_params(self):
        """Test that query parameters are preserved."""
        from fastapi.testclient import TestClient
        from app.main import app

        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.content = b'[]'
        mock_response.headers = httpx.Headers({"content-type": "application/json"})

        with patch("app.proxy.routes.auth_middleware.validate_request") as mock_auth:
            mock_auth.return_value = self._mock_auth_success()

            with patch("app.proxy.routes.get_proxy_client") as mock_get_client:
                mock_client = AsyncMock()
                mock_client.request = AsyncMock(return_value=mock_response)
                mock_get_client.return_value = mock_client

                with TestClient(app) as client:
                    response = client.get("/api/companies?page=1&size=10&search=test")

                    assert response.status_code == 200

                    # Verify query params were included
                    call_args = mock_client.request.call_args
                    assert "page=1" in call_args.kwargs["url"]
                    assert "size=10" in call_args.kwargs["url"]
                    assert "search=test" in call_args.kwargs["url"]

    @pytest.mark.asyncio
    async def test_proxy_timeout_returns_504(self):
        """Test that timeout returns 504 Gateway Timeout."""
        from fastapi.testclient import TestClient
        from app.main import app

        with patch("app.proxy.routes.auth_middleware.validate_request") as mock_auth:
            mock_auth.return_value = self._mock_auth_success()

            with patch("app.proxy.routes.get_proxy_client") as mock_get_client:
                mock_client = AsyncMock()
                mock_client.request = AsyncMock(side_effect=httpx.TimeoutException("timeout"))
                mock_get_client.return_value = mock_client

                with TestClient(app) as client:
                    response = client.get("/api/slow/endpoint")

                    assert response.status_code == 504
                    assert "timeout" in response.json()["detail"].lower()

    @pytest.mark.asyncio
    async def test_proxy_connection_error_returns_503(self):
        """Test that connection error returns 503 Service Unavailable."""
        from fastapi.testclient import TestClient
        from app.main import app

        with patch("app.proxy.routes.auth_middleware.validate_request") as mock_auth:
            mock_auth.return_value = self._mock_auth_success()

            with patch("app.proxy.routes.get_proxy_client") as mock_get_client:
                mock_client = AsyncMock()
                mock_client.request = AsyncMock(side_effect=httpx.ConnectError("connection refused"))
                mock_get_client.return_value = mock_client

                with TestClient(app) as client:
                    response = client.get("/api/unreachable")

                    assert response.status_code == 503
                    assert "unavailable" in response.json()["detail"].lower()


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
