"""
Tests for the Target Mercure proxy — /.well-known/mercure.

Covers:
1. GET (subscribe): returns StreamingResponse with SSE headers, no Keycloak auth required
2. POST (publish): forwards request and returns backend response
3. Header forwarding: Authorization and Last-Event-ID are preserved
4. Error handling: 503 when FrankenPHP Mercure hub unreachable, 502 on generic error
5. No Keycloak JWT validation: unauthenticated requests pass through
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from contextlib import asynccontextmanager

import httpx
from fastapi.testclient import TestClient


# ─── Fixtures ──────────────────────────────────────────────────────────────


@pytest.fixture
def app():
    """Return a minimal FastAPI app with only the Mercure router registered."""
    from fastapi import FastAPI
    from app.api.routes.target_mercure_proxy import router

    test_app = FastAPI()
    test_app.include_router(router)
    return test_app


@pytest.fixture
def client(app):
    """Synchronous TestClient (used for non-streaming assertions)."""
    return TestClient(app, raise_server_exceptions=False)


# ─── Helper: mock streaming client ────────────────────────────────────────


def _make_mock_response(chunks: list[bytes], status_code: int = 200) -> MagicMock:
    """Build a mock httpx response suitable for streaming."""
    mock_response = MagicMock()
    mock_response.status_code = status_code
    mock_response.headers = httpx.Headers({"content-type": "application/json"})
    mock_response.aread = AsyncMock(return_value=b"")

    async def aiter_bytes():
        for chunk in chunks:
            yield chunk

    mock_response.aiter_bytes = aiter_bytes
    return mock_response


def _mock_streaming_client(chunks: list[bytes], status_code: int = 200):
    """Return a get_streaming_client context manager that yields fake SSE chunks."""
    mock_response = _make_mock_response(chunks, status_code)

    @asynccontextmanager
    async def streaming_ctx(method, url, headers=None):
        yield mock_response

    mock_client = MagicMock()
    mock_client.stream = streaming_ctx

    @asynccontextmanager
    async def get_streaming(_backend_url=None):
        yield mock_client

    return get_streaming


# ─── GET subscribe ─────────────────────────────────────────────────────────


class TestMercureSubscribe:
    """Tests for GET /.well-known/mercure (SSE subscribe)."""

    @pytest.mark.asyncio
    async def test_subscribe_returns_sse_content_type(self, app):
        """GET returns text/event-stream content type."""
        from httpx import AsyncClient, ASGITransport

        chunks = [b"data: hello\n\n", b"data: world\n\n"]

        with patch(
            "app.api.routes.target_mercure_proxy.get_streaming_client",
            _mock_streaming_client(chunks),
        ):
            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                async with ac.stream("GET", "/.well-known/mercure?topic=test") as resp:
                    assert resp.status_code == 200
                    assert "text/event-stream" in resp.headers["content-type"]

    @pytest.mark.asyncio
    async def test_subscribe_returns_no_cache_header(self, app):
        """GET response includes Cache-Control: no-cache."""
        chunks = [b"data: ping\n\n"]

        with patch(
            "app.api.routes.target_mercure_proxy.get_streaming_client",
            _mock_streaming_client(chunks),
        ):
            from httpx import AsyncClient, ASGITransport

            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                async with ac.stream("GET", "/.well-known/mercure") as resp:
                    assert resp.headers.get("cache-control") == "no-cache"

    @pytest.mark.asyncio
    async def test_subscribe_returns_x_accel_buffering_no(self, app):
        """GET response includes X-Accel-Buffering: no to disable nginx buffering."""
        chunks = [b"data: ping\n\n"]

        with patch(
            "app.api.routes.target_mercure_proxy.get_streaming_client",
            _mock_streaming_client(chunks),
        ):
            from httpx import AsyncClient, ASGITransport

            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                async with ac.stream("GET", "/.well-known/mercure") as resp:
                    assert resp.headers.get("x-accel-buffering") == "no"

    @pytest.mark.asyncio
    async def test_subscribe_streams_chunks(self, app):
        """GET streams all chunks from FrankenPHP Mercure hub."""
        chunks = [b"data: event1\n\n", b"data: event2\n\n", b"data: event3\n\n"]

        with patch(
            "app.api.routes.target_mercure_proxy.get_streaming_client",
            _mock_streaming_client(chunks),
        ):
            from httpx import AsyncClient, ASGITransport

            body = b""
            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                async with ac.stream("GET", "/.well-known/mercure") as resp:
                    async for chunk in resp.aiter_bytes():
                        body += chunk

        assert body == b"data: event1\n\ndata: event2\n\ndata: event3\n\n"

    @pytest.mark.asyncio
    async def test_subscribe_hub_unavailable_returns_503(self, app):
        """GET returns 503 JSON when FrankenPHP Mercure hub is unreachable (before stream opens)."""
        @asynccontextmanager
        async def failing_streaming(_backend_url=None):
            mock_client = MagicMock()

            @asynccontextmanager
            async def stream_ctx(method, url, headers=None):
                raise httpx.ConnectError("Connection refused")
                yield  # noqa: unreachable

            mock_client.stream = stream_ctx
            yield mock_client

        with patch(
            "app.api.routes.target_mercure_proxy.get_streaming_client",
            failing_streaming,
        ):
            from httpx import AsyncClient, ASGITransport

            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                resp = await ac.get("/.well-known/mercure")
                assert resp.status_code == 503

    @pytest.mark.asyncio
    async def test_subscribe_backend_401_propagated(self, app):
        """GET propagates 401 from FrankenPHP Mercure hub (bad Mercure JWT) as HTTP 401."""
        with patch(
            "app.api.routes.target_mercure_proxy.get_streaming_client",
            _mock_streaming_client([], status_code=401),
        ):
            from httpx import AsyncClient, ASGITransport

            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                resp = await ac.get("/.well-known/mercure")
                assert resp.status_code == 401


# ─── POST publish ─────────────────────────────────────────────────────────


class TestMercurePublish:
    """Tests for POST /.well-known/mercure (publish)."""

    @pytest.mark.asyncio
    async def test_publish_forwards_to_hub(self, app):
        """POST forwards the request body to FrankenPHP Mercure hub."""
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.headers = httpx.Headers({"content-type": "text/plain"})
        mock_response.aclose = AsyncMock()

        async def aiter_bytes():
            yield b"id=1"

        mock_response.aiter_bytes = aiter_bytes

        mock_client = MagicMock()
        mock_client.build_request = MagicMock(return_value=MagicMock())
        mock_client.send = AsyncMock(return_value=mock_response)

        with patch(
            "app.api.routes.target_mercure_proxy.get_proxy_client",
            AsyncMock(return_value=mock_client),
        ):
            from httpx import AsyncClient, ASGITransport

            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                resp = await ac.post(
                    "/.well-known/mercure",
                    content=b"topic=https://example.com/topic&data=hello",
                    headers={"content-type": "application/x-www-form-urlencoded"},
                )
                assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_publish_hub_unavailable_returns_503(self, app):
        """POST returns 503 when FrankenPHP Mercure hub is unreachable."""
        mock_client = MagicMock()
        mock_client.build_request = MagicMock(return_value=MagicMock())
        mock_client.send = AsyncMock(side_effect=httpx.ConnectError("refused"))

        with patch(
            "app.api.routes.target_mercure_proxy.get_proxy_client",
            AsyncMock(return_value=mock_client),
        ):
            from httpx import AsyncClient, ASGITransport

            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                resp = await ac.post("/.well-known/mercure", content=b"data=test")
                assert resp.status_code == 503

    @pytest.mark.asyncio
    async def test_publish_generic_error_returns_502(self, app):
        """POST returns 502 on unexpected backend error."""
        mock_client = MagicMock()
        mock_client.build_request = MagicMock(return_value=MagicMock())
        mock_client.send = AsyncMock(side_effect=RuntimeError("unexpected"))

        with patch(
            "app.api.routes.target_mercure_proxy.get_proxy_client",
            AsyncMock(return_value=mock_client),
        ):
            from httpx import AsyncClient, ASGITransport

            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                resp = await ac.post("/.well-known/mercure", content=b"data=test")
                assert resp.status_code == 502


# ─── No Keycloak auth ─────────────────────────────────────────────────────


class TestMercureNoAuth:
    """Mercure route must NOT require Keycloak authentication."""

    @pytest.mark.asyncio
    async def test_unauthenticated_get_is_not_blocked(self, app):
        """Request without Authorization header is not rejected by the gateway."""
        chunks = [b"data: ok\n\n"]

        with patch(
            "app.api.routes.target_mercure_proxy.get_streaming_client",
            _mock_streaming_client(chunks),
        ):
            from httpx import AsyncClient, ASGITransport

            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                async with ac.stream("GET", "/.well-known/mercure") as resp:
                    # 401 would mean Keycloak auth is wrongly applied
                    assert resp.status_code != 401
                    assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_authorization_header_forwarded(self, app):
        """Authorization header (Mercure JWT) is forwarded to FrankenPHP Mercure hub."""
        forwarded_headers: dict = {}
        chunks = [b"data: ok\n\n"]

        @asynccontextmanager
        async def capturing_streaming(_backend_url=None):
            mock_client = MagicMock()

            @asynccontextmanager
            async def stream_ctx(method, url, headers=None):
                if headers:
                    forwarded_headers.update(headers)
                yield _make_mock_response(chunks)

            mock_client.stream = stream_ctx
            yield mock_client

        with patch(
            "app.api.routes.target_mercure_proxy.get_streaming_client",
            capturing_streaming,
        ):
            from httpx import AsyncClient, ASGITransport

            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                async with ac.stream(
                    "GET",
                    "/.well-known/mercure",
                    headers={"authorization": "Bearer mercure.jwt.token"},
                ) as resp:
                    assert resp.status_code == 200

        assert "authorization" in forwarded_headers

    @pytest.mark.asyncio
    async def test_last_event_id_forwarded(self, app):
        """Last-Event-ID header is forwarded for SSE reconnection."""
        forwarded_headers: dict = {}
        chunks = [b"data: ok\n\n"]

        @asynccontextmanager
        async def capturing_streaming(_backend_url=None):
            mock_client = MagicMock()

            @asynccontextmanager
            async def stream_ctx(method, url, headers=None):
                if headers:
                    forwarded_headers.update(headers)
                yield _make_mock_response(chunks)

            mock_client.stream = stream_ctx
            yield mock_client

        with patch(
            "app.api.routes.target_mercure_proxy.get_streaming_client",
            capturing_streaming,
        ):
            from httpx import AsyncClient, ASGITransport

            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                async with ac.stream(
                    "GET",
                    "/.well-known/mercure",
                    headers={"last-event-id": "42"},
                ) as resp:
                    assert resp.status_code == 200

        assert "last-event-id" in forwarded_headers


# ─── Query string forwarding ──────────────────────────────────────────────


class TestMercureQueryString:
    """Topic query parameters must be forwarded to FrankenPHP Mercure hub."""

    @pytest.mark.asyncio
    async def test_topic_query_string_forwarded(self, app):
        """?topic=... query is included in the downstream URL."""
        captured_url: list[str] = []
        chunks = [b"data: ok\n\n"]

        @asynccontextmanager
        async def capturing_streaming(_backend_url=None):
            mock_client = MagicMock()

            @asynccontextmanager
            async def stream_ctx(method, url, headers=None):
                captured_url.append(url)
                yield _make_mock_response(chunks)

            mock_client.stream = stream_ctx
            yield mock_client

        with patch(
            "app.api.routes.target_mercure_proxy.get_streaming_client",
            capturing_streaming,
        ):
            from httpx import AsyncClient, ASGITransport

            async with AsyncClient(
                transport=ASGITransport(app=app), base_url="http://test"
            ) as ac:
                async with ac.stream(
                    "GET",
                    "/.well-known/mercure?topic=https%3A%2F%2Fexample.com%2Ftopic",
                ) as resp:
                    assert resp.status_code == 200

        assert len(captured_url) == 1
        assert "topic=" in captured_url[0]
