"""Unit tests for the TokenClient and credit cost utilities.

Covers:
- Credit cost per channel type (configurable via settings)
- TokenClient.consume() — success, 402, 403, network error, timeout, unexpected status
- JWT creation failure
- Factory function (create_token_client) with/without GLOBAL_SERVICE_URL
"""

from __future__ import annotations

from unittest.mock import AsyncMock, MagicMock, patch

import httpx
import pytest

from app.models.stream import ChannelType
from app.services.token_client import (
    TokenClient,
    TokenConsumeResult,
    TokenErrorCode,
    create_token_client,
    get_credit_cost,
)

# ── Credit cost tests ────────────────────────────────────────────────


class TestGetCreditCost:
    def test_teams_default_cost(self) -> None:
        assert get_credit_cost(ChannelType.TEAMS) == 5

    def test_slack_default_cost(self) -> None:
        assert get_credit_cost(ChannelType.SLACK_WEBHOOK) == 5

    def test_webhook_default_cost(self) -> None:
        assert get_credit_cost(ChannelType.WEBHOOK) == 2

    def test_custom_cost_via_settings(self) -> None:
        with patch("app.services.token_client.settings") as mock_settings:
            mock_settings.STREAM_COST_TEAMS = 10
            mock_settings.STREAM_COST_SLACK = 8
            mock_settings.STREAM_COST_WEBHOOK = 3
            assert get_credit_cost(ChannelType.TEAMS) == 10
            assert get_credit_cost(ChannelType.SLACK_WEBHOOK) == 8
            assert get_credit_cost(ChannelType.WEBHOOK) == 3


# ── Factory tests ────────────────────────────────────────────────────


class TestCreateTokenClient:
    def test_returns_client_when_url_set(self) -> None:
        with patch("app.services.token_client.settings") as mock_settings:
            mock_settings.GLOBAL_SERVICE_URL = "http://gateway:8000/api"
            client = create_token_client()
            assert client is not None
            assert isinstance(client, TokenClient)

    def test_returns_none_when_url_empty(self) -> None:
        with patch("app.services.token_client.settings") as mock_settings:
            mock_settings.GLOBAL_SERVICE_URL = ""
            client = create_token_client()
            assert client is None

    def test_strips_trailing_slash(self) -> None:
        with patch("app.services.token_client.settings") as mock_settings:
            mock_settings.GLOBAL_SERVICE_URL = "http://gateway:8000/api/"
            client = create_token_client()
            assert client is not None
            assert client._base_url == "http://gateway:8000/api"


# ── TokenClient.consume() tests ─────────────────────────────────────

_CONSUME_KWARGS = {
    "org_id": "org-123",
    "amount": 5,
    "reference_id": "delivery-42",
    "user_id": "user-abc",
    "username": "testuser",
}


def _make_client() -> TokenClient:
    return TokenClient(base_url="http://gateway:8000/api")


class TestTokenClientConsume:
    @pytest.mark.asyncio
    async def test_success(self) -> None:
        response = MagicMock()
        response.status_code = 200
        response.json.return_value = {"success": True, "balance": 95, "transaction": None}

        mock_http_client = AsyncMock()
        mock_http_client.post = AsyncMock(return_value=response)
        mock_http_client.__aenter__ = AsyncMock(return_value=mock_http_client)
        mock_http_client.__aexit__ = AsyncMock(return_value=False)

        client = _make_client()
        with (
            patch("app.services.token_client.create_internal_token", return_value="jwt-token"),
            patch("app.services.token_client.httpx.AsyncClient", return_value=mock_http_client),
        ):
            result = await client.consume(**_CONSUME_KWARGS)

        assert result.success is True
        assert result.balance == 95
        assert result.error is None
        assert result.error_code is None

    @pytest.mark.asyncio
    async def test_success_sends_correct_payload(self) -> None:
        """Verify the HTTP request contains the expected body and headers."""
        response = MagicMock()
        response.status_code = 200
        response.json.return_value = {"success": True, "balance": 90}

        mock_http_client = AsyncMock()
        mock_http_client.post = AsyncMock(return_value=response)
        mock_http_client.__aenter__ = AsyncMock(return_value=mock_http_client)
        mock_http_client.__aexit__ = AsyncMock(return_value=False)

        client = _make_client()
        with (
            patch("app.services.token_client.create_internal_token", return_value="my-jwt"),
            patch("app.services.token_client.httpx.AsyncClient", return_value=mock_http_client),
        ):
            await client.consume(**_CONSUME_KWARGS)

        mock_http_client.post.assert_called_once()
        call_args = mock_http_client.post.call_args

        assert call_args.kwargs["headers"] == {"Authorization": "Internal my-jwt"}
        body = call_args.kwargs["json"]
        assert body["amount"] == 5
        assert body["module_name"] == "stream"
        assert body["reference_type"] == "dispatch"
        assert body["reference_id"] == "delivery-42"
        assert body["created_by"] == "user-abc"

    @pytest.mark.asyncio
    async def test_success_url_construction(self) -> None:
        """Verify the URL includes the org_id."""
        response = MagicMock()
        response.status_code = 200
        response.json.return_value = {"success": True, "balance": 90}

        mock_http_client = AsyncMock()
        mock_http_client.post = AsyncMock(return_value=response)
        mock_http_client.__aenter__ = AsyncMock(return_value=mock_http_client)
        mock_http_client.__aexit__ = AsyncMock(return_value=False)

        client = _make_client()
        with (
            patch("app.services.token_client.create_internal_token", return_value="t"),
            patch("app.services.token_client.httpx.AsyncClient", return_value=mock_http_client),
        ):
            await client.consume(**_CONSUME_KWARGS)

        url = mock_http_client.post.call_args.args[0]
        assert url == "http://gateway:8000/api/internal/organizations/org-123/tokens/consume"

    @pytest.mark.asyncio
    async def test_insufficient_tokens_402(self) -> None:
        response = MagicMock()
        response.status_code = 402
        response.json.return_value = {
            "detail": {
                "message": "Insufficient tokens. Current balance: 2, required: 5",
                "current_balance": 2,
                "required_tokens": 5,
            }
        }

        mock_http_client = AsyncMock()
        mock_http_client.post = AsyncMock(return_value=response)
        mock_http_client.__aenter__ = AsyncMock(return_value=mock_http_client)
        mock_http_client.__aexit__ = AsyncMock(return_value=False)

        client = _make_client()
        with (
            patch("app.services.token_client.create_internal_token", return_value="jwt"),
            patch("app.services.token_client.httpx.AsyncClient", return_value=mock_http_client),
        ):
            result = await client.consume(**_CONSUME_KWARGS)

        assert result.success is False
        assert result.error_code == TokenErrorCode.INSUFFICIENT_TOKENS
        assert result.balance == 2
        assert "Insufficient" in (result.error or "")

    @pytest.mark.asyncio
    async def test_module_not_enabled_403(self) -> None:
        response = MagicMock()
        response.status_code = 403
        response.json.return_value = {"detail": {"message": "Module 'stream' is not enabled for this organization"}}

        mock_http_client = AsyncMock()
        mock_http_client.post = AsyncMock(return_value=response)
        mock_http_client.__aenter__ = AsyncMock(return_value=mock_http_client)
        mock_http_client.__aexit__ = AsyncMock(return_value=False)

        client = _make_client()
        with (
            patch("app.services.token_client.create_internal_token", return_value="jwt"),
            patch("app.services.token_client.httpx.AsyncClient", return_value=mock_http_client),
        ):
            result = await client.consume(**_CONSUME_KWARGS)

        assert result.success is False
        assert result.error_code == TokenErrorCode.MODULE_NOT_ENABLED
        assert "not enabled" in (result.error or "").lower()

    @pytest.mark.asyncio
    async def test_403_org_id_mismatch(self) -> None:
        """403 with a string detail (org mismatch) should not crash."""
        response = MagicMock()
        response.status_code = 403
        response.json.return_value = {
            "detail": {"message": "Organization ID in path does not match token organization"}
        }

        mock_http_client = AsyncMock()
        mock_http_client.post = AsyncMock(return_value=response)
        mock_http_client.__aenter__ = AsyncMock(return_value=mock_http_client)
        mock_http_client.__aexit__ = AsyncMock(return_value=False)

        client = _make_client()
        with (
            patch("app.services.token_client.create_internal_token", return_value="jwt"),
            patch("app.services.token_client.httpx.AsyncClient", return_value=mock_http_client),
        ):
            result = await client.consume(**_CONSUME_KWARGS)

        assert result.success is False
        assert result.error_code == TokenErrorCode.MODULE_NOT_ENABLED

    @pytest.mark.asyncio
    async def test_network_timeout(self) -> None:
        mock_http_client = AsyncMock()
        mock_http_client.post = AsyncMock(side_effect=httpx.TimeoutException("timed out"))
        mock_http_client.__aenter__ = AsyncMock(return_value=mock_http_client)
        mock_http_client.__aexit__ = AsyncMock(return_value=False)

        client = _make_client()
        with (
            patch("app.services.token_client.create_internal_token", return_value="jwt"),
            patch("app.services.token_client.httpx.AsyncClient", return_value=mock_http_client),
        ):
            result = await client.consume(**_CONSUME_KWARGS)

        assert result.success is False
        assert result.error_code == TokenErrorCode.NETWORK_ERROR
        assert "timed out" in (result.error or "").lower()

    @pytest.mark.asyncio
    async def test_network_connection_error(self) -> None:
        mock_http_client = AsyncMock()
        mock_http_client.post = AsyncMock(side_effect=httpx.ConnectError("Connection refused"))
        mock_http_client.__aenter__ = AsyncMock(return_value=mock_http_client)
        mock_http_client.__aexit__ = AsyncMock(return_value=False)

        client = _make_client()
        with (
            patch("app.services.token_client.create_internal_token", return_value="jwt"),
            patch("app.services.token_client.httpx.AsyncClient", return_value=mock_http_client),
        ):
            result = await client.consume(**_CONSUME_KWARGS)

        assert result.success is False
        assert result.error_code == TokenErrorCode.NETWORK_ERROR

    @pytest.mark.asyncio
    async def test_unexpected_status_code(self) -> None:
        response = MagicMock()
        response.status_code = 500
        response.text = "Internal Server Error"
        response.json.return_value = {}

        mock_http_client = AsyncMock()
        mock_http_client.post = AsyncMock(return_value=response)
        mock_http_client.__aenter__ = AsyncMock(return_value=mock_http_client)
        mock_http_client.__aexit__ = AsyncMock(return_value=False)

        client = _make_client()
        with (
            patch("app.services.token_client.create_internal_token", return_value="jwt"),
            patch("app.services.token_client.httpx.AsyncClient", return_value=mock_http_client),
        ):
            result = await client.consume(**_CONSUME_KWARGS)

        assert result.success is False
        assert result.error_code == TokenErrorCode.UNEXPECTED_ERROR
        assert "500" in (result.error or "")

    @pytest.mark.asyncio
    async def test_jwt_creation_failure(self) -> None:
        """If INTERNAL_JWT_SECRET is not set, consume returns AUTH_ERROR."""
        from app.core.internal_jwt import InternalJWTError

        client = _make_client()
        with patch(
            "app.services.token_client.create_internal_token",
            side_effect=InternalJWTError("INTERNAL_JWT_SECRET not configured"),
        ):
            result = await client.consume(**_CONSUME_KWARGS)

        assert result.success is False
        assert result.error_code == TokenErrorCode.AUTH_ERROR
        assert "JWT" in (result.error or "")


# ── TokenConsumeResult ───────────────────────────────────────────────


class TestTokenConsumeResult:
    def test_success_result_is_frozen(self) -> None:
        r = TokenConsumeResult(success=True, balance=100)
        with pytest.raises(AttributeError):
            r.success = False  # type: ignore[misc]

    def test_default_fields(self) -> None:
        r = TokenConsumeResult(success=True)
        assert r.balance is None
        assert r.error is None
        assert r.error_code is None
