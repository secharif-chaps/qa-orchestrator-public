"""Tests for readiness health checks (TAR-1025)."""

import asyncio
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.health import (
    check_database,
    check_grpc,
    check_keycloak,
    check_proxy_client,
    run_readiness_checks,
)


@pytest.mark.asyncio
class TestCheckDatabase:
    """Tests for the database health check."""

    async def test_database_healthy(self):
        """SELECT 1 succeeds → True."""
        mock_conn = AsyncMock()
        mock_conn.execute = AsyncMock()
        mock_engine = MagicMock()
        mock_engine.connect = MagicMock(
            return_value=AsyncMock(
                __aenter__=AsyncMock(return_value=mock_conn),
                __aexit__=AsyncMock(return_value=False),
            )
        )

        with patch("app.health.engine", mock_engine):
            result = await check_database()
        assert result is True

    async def test_database_down(self):
        """Connection fails → False."""
        mock_engine = MagicMock()
        mock_engine.connect = MagicMock(
            return_value=AsyncMock(
                __aenter__=AsyncMock(side_effect=ConnectionError("DB down")),
                __aexit__=AsyncMock(return_value=False),
            )
        )

        with patch("app.health.engine", mock_engine):
            result = await check_database()
        assert result is False


@pytest.mark.asyncio
class TestCheckGrpc:
    """Tests for the gRPC server health check."""

    async def test_grpc_listening(self):
        """TCP connect to gRPC port succeeds → True."""
        real_loop = asyncio.get_running_loop()
        mock_loop = AsyncMock(wraps=real_loop)
        mock_loop.sock_connect = AsyncMock()  # succeeds silently

        mock_sock = MagicMock()

        with (
            patch("app.health.socket.socket", return_value=mock_sock),
            patch("app.health.asyncio.get_running_loop", return_value=mock_loop),
        ):
            result = await check_grpc()
        assert result is True

    async def test_grpc_not_listening(self):
        """TCP connect fails on both IPv6 and IPv4 → False."""
        real_loop = asyncio.get_running_loop()
        mock_loop = AsyncMock(wraps=real_loop)
        mock_loop.sock_connect = AsyncMock(side_effect=ConnectionRefusedError())

        mock_sock = MagicMock()

        with (
            patch("app.health.socket.socket", return_value=mock_sock),
            patch("app.health.asyncio.get_running_loop", return_value=mock_loop),
        ):
            result = await check_grpc()
        assert result is False


@pytest.mark.asyncio
class TestCheckProxyClient:
    """Tests for the httpx proxy client health check."""

    async def test_proxy_client_healthy(self):
        """Pool reports healthy → True."""
        with patch("app.health._pool") as mock_pool:
            mock_pool.health_check.return_value = (True, {"status": "healthy"})
            result = await check_proxy_client()
        assert result is True

    async def test_proxy_client_idle(self):
        """No clients yet (idle) → True."""
        with patch("app.health._pool") as mock_pool:
            mock_pool.health_check.return_value = (True, {"status": "idle", "clients": 0})
            result = await check_proxy_client()
        assert result is True

    async def test_proxy_client_degraded(self):
        """Pool reports degraded → False."""
        with patch("app.health._pool") as mock_pool:
            mock_pool.health_check.return_value = (False, {"status": "degraded"})
            result = await check_proxy_client()
        assert result is False

    async def test_proxy_client_exception(self):
        """Pool health_check raises → False (graceful degradation)."""
        with patch("app.health._pool") as mock_pool:
            mock_pool.health_check.side_effect = RuntimeError("pool corrupted")
            result = await check_proxy_client()
        assert result is False


@pytest.mark.asyncio
class TestCheckKeycloak:
    """Tests for the Keycloak connectivity health check."""

    async def test_keycloak_reachable(self):
        """OpenID configuration endpoint responds 200 → True."""
        mock_response = MagicMock()
        mock_response.raise_for_status = MagicMock()

        mock_client = AsyncMock()
        mock_client.get = AsyncMock(return_value=mock_response)

        with patch("app.health._get_health_http_client", return_value=mock_client):
            result = await check_keycloak()
        assert result is True

    async def test_keycloak_unreachable(self):
        """Connection to Keycloak fails → False."""
        mock_client = AsyncMock()
        mock_client.get = AsyncMock(side_effect=ConnectionError("unreachable"))

        with patch("app.health._get_health_http_client", return_value=mock_client):
            result = await check_keycloak()
        assert result is False


@pytest.mark.asyncio
class TestRunReadinessChecks:
    """Tests for the aggregated readiness check runner."""

    async def test_all_checks_pass(self):
        """All dependencies healthy → all_ready=True."""
        with (
            patch("app.health.check_database", AsyncMock(return_value=True)),
            patch("app.health.check_grpc", AsyncMock(return_value=True)),
            patch("app.health.check_proxy_client", AsyncMock(return_value=True)),
        ):
            checks, all_ready = await run_readiness_checks(include_keycloak=False)

        assert all_ready is True
        assert checks == {"database": True, "grpc": True, "proxy_client": True}
        assert "keycloak" not in checks

    async def test_one_check_fails(self):
        """One dependency down → all_ready=False."""
        with (
            patch("app.health.check_database", AsyncMock(return_value=True)),
            patch("app.health.check_grpc", AsyncMock(return_value=False)),
            patch("app.health.check_proxy_client", AsyncMock(return_value=True)),
        ):
            checks, all_ready = await run_readiness_checks(include_keycloak=False)

        assert all_ready is False
        assert checks["grpc"] is False
        assert checks["database"] is True

    async def test_keycloak_included_when_enabled(self):
        """Keycloak check appears in results when enabled."""
        with (
            patch("app.health.check_database", AsyncMock(return_value=True)),
            patch("app.health.check_grpc", AsyncMock(return_value=True)),
            patch("app.health.check_proxy_client", AsyncMock(return_value=True)),
            patch("app.health.check_keycloak", AsyncMock(return_value=True)),
        ):
            checks, all_ready = await run_readiness_checks(include_keycloak=True)

        assert all_ready is True
        assert "keycloak" in checks
        assert checks["keycloak"] is True

    async def test_keycloak_failure_reported(self):
        """Keycloak down → all_ready=False, other checks still run."""
        with (
            patch("app.health.check_database", AsyncMock(return_value=True)),
            patch("app.health.check_grpc", AsyncMock(return_value=True)),
            patch("app.health.check_proxy_client", AsyncMock(return_value=True)),
            patch("app.health.check_keycloak", AsyncMock(return_value=False)),
        ):
            checks, all_ready = await run_readiness_checks(include_keycloak=True)

        assert all_ready is False
        assert checks["keycloak"] is False
        assert checks["database"] is True
        assert checks["grpc"] is True
        assert checks["proxy_client"] is True

    async def test_exception_in_check_treated_as_failure(self):
        """If a check raises an exception, it's treated as False."""
        with (
            patch(
                "app.health.check_database",
                AsyncMock(side_effect=RuntimeError("unexpected")),
            ),
            patch("app.health.check_grpc", AsyncMock(return_value=True)),
            patch("app.health.check_proxy_client", AsyncMock(return_value=True)),
        ):
            checks, all_ready = await run_readiness_checks(include_keycloak=False)

        assert all_ready is False
        assert checks["database"] is False


@pytest.mark.asyncio
class TestHealthReadyEndpoint:
    """Integration tests for the /health/ready endpoint."""

    async def test_ready_returns_200(self, client):
        """All checks pass → HTTP 200."""
        with (
            patch("app.health.check_database", AsyncMock(return_value=True)),
            patch("app.health.check_grpc", AsyncMock(return_value=True)),
            patch("app.health.check_proxy_client", AsyncMock(return_value=True)),
        ):
            response = await client.get("/health/ready")

        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "ready"
        assert data["checks"]["database"] is True

    async def test_not_ready_returns_503(self, client):
        """A check fails → HTTP 503."""
        with (
            patch("app.health.check_database", AsyncMock(return_value=False)),
            patch("app.health.check_grpc", AsyncMock(return_value=True)),
            patch("app.health.check_proxy_client", AsyncMock(return_value=True)),
        ):
            response = await client.get("/health/ready")

        assert response.status_code == 503
        data = response.json()
        assert data["status"] == "not_ready"
        assert data["checks"]["database"] is False

    async def test_keycloak_included_when_setting_enabled(self, client):
        """HEALTH_CHECK_KEYCLOAK_ENABLED=True → keycloak check appears in response."""
        with (
            patch("app.health.check_database", AsyncMock(return_value=True)),
            patch("app.health.check_grpc", AsyncMock(return_value=True)),
            patch("app.health.check_proxy_client", AsyncMock(return_value=True)),
            patch("app.health.check_keycloak", AsyncMock(return_value=True)),
            patch("app.core.config.settings.HEALTH_CHECK_KEYCLOAK_ENABLED", True),
        ):
            response = await client.get("/health/ready")

        assert response.status_code == 200
        data = response.json()
        assert "keycloak" in data["checks"]
        assert data["checks"]["keycloak"] is True

    async def test_live_endpoint_unchanged(self, client):
        """Liveness probe still returns simple response."""
        response = await client.get("/health/live")
        assert response.status_code == 200
        assert response.json() == {"status": "alive"}
