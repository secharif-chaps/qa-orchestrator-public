"""Tests for `EpoClient` with mocked HTTP calls.

Covers: OAuth2 auth OK/KO, token caching and refresh, concurrent single-flight
refresh, mid-flight 401 recovery, 403 quota-exceeded retry with backoff,
non-quota 403 hard failure, 404, network errors, and credential redaction.
"""

from __future__ import annotations

import asyncio
import json
from unittest.mock import AsyncMock, MagicMock, patch

import httpx
import pytest

from app.infrastructure.epo import client as epo_client_module
from app.infrastructure.epo.client import TOKEN_SAFETY_WINDOW_SECONDS, EpoClient
from app.infrastructure.epo.exceptions import (
    EpoAuthError,
    EpoError,
    EpoNotFoundError,
    EpoQuotaExceededError,
)

from .conftest import make_json_response, make_response

MINIMAL_BIBLIO_XML = b"""<?xml version="1.0" encoding="UTF-8"?>
<ops:world-patent-data xmlns:ops="http://ops.epo.org" xmlns:ex="http://www.epo.org/exchange">
  <ex:exchange-documents>
    <ex:exchange-document country="EP" doc-number="1000000" kind="A1">
      <ex:bibliographic-data/>
    </ex:exchange-document>
  </ex:exchange-documents>
</ops:world-patent-data>
"""


def _build_async_client_mock(
    auth_responses: list[httpx.Response] | None = None,
    data_responses: list[httpx.Response] | None = None,
) -> MagicMock:
    """Build the nested mock replacing `httpx.AsyncClient` as a context manager.

    Auth calls go through `.post` (OAuth2 token endpoint); data calls go
    through `.request` (bibliographic / abstract / etc.). Splitting the two
    lists keeps counts and ordering independent.
    """
    mock_class = MagicMock()
    mock_instance = AsyncMock()
    mock_class.return_value.__aenter__ = AsyncMock(return_value=mock_instance)
    mock_class.return_value.__aexit__ = AsyncMock(return_value=False)

    if auth_responses is not None:
        mock_instance.post = AsyncMock(side_effect=list(auth_responses))
    if data_responses is not None:
        mock_instance.request = AsyncMock(side_effect=list(data_responses))

    return mock_class


class TestAuth:
    """Tests for `_get_token` / `_fetch_token`."""

    @pytest.mark.asyncio
    async def test_get_token_success(self, client, fake_auth_payload):
        mock_class = _build_async_client_mock(auth_responses=[make_json_response(200, fake_auth_payload)])

        with patch.object(epo_client_module.httpx, "AsyncClient", mock_class):
            token = await client._get_token()

        assert token == "fake-bearer-token-xyz"
        instance = mock_class.return_value.__aenter__.return_value
        instance.post.assert_awaited_once()
        call_args = instance.post.await_args
        posted_url = call_args.args[0] if call_args.args else call_args.kwargs.get("url")
        assert posted_url.endswith("/auth/accesstoken")
        headers = call_args.kwargs["headers"]
        assert headers["Authorization"].startswith("Basic ")
        assert headers["Content-Type"] == "application/x-www-form-urlencoded"

    @pytest.mark.asyncio
    async def test_get_token_invalid_credentials_raises_auth_error(self, client):
        mock_class = _build_async_client_mock(
            auth_responses=[make_response(400, content=b"invalid_client", headers={"content-type": "text/plain"})]
        )
        with (
            patch.object(epo_client_module.httpx, "AsyncClient", mock_class),
            pytest.raises(EpoAuthError) as exc_info,
        ):
            await client._get_token()

        # The raised message must NOT contain credentials
        assert "fake-consumer-key" not in str(exc_info.value)
        assert "fake-consumer-secret" not in str(exc_info.value)

    @pytest.mark.asyncio
    async def test_fetch_token_transport_error_raises_auth_error(self, client):
        mock_class = MagicMock()
        mock_instance = AsyncMock()
        mock_class.return_value.__aenter__ = AsyncMock(return_value=mock_instance)
        mock_class.return_value.__aexit__ = AsyncMock(return_value=False)
        mock_instance.post = AsyncMock(side_effect=httpx.ConnectError("boom"))

        with (
            patch.object(epo_client_module.httpx, "AsyncClient", mock_class),
            pytest.raises(EpoAuthError),
        ):
            await client._get_token()

    @pytest.mark.asyncio
    async def test_missing_access_token_raises_auth_error(self, client):
        mock_class = _build_async_client_mock(auth_responses=[make_json_response(200, {"expires_in": 1200})])
        with (
            patch.object(epo_client_module.httpx, "AsyncClient", mock_class),
            pytest.raises(EpoAuthError),
        ):
            await client._get_token()


class TestTokenCaching:
    """Cache reuse, expiry and single-flight refresh."""

    @pytest.mark.asyncio
    async def test_token_reused_within_ttl(self, client, fake_auth_payload):
        mock_class = _build_async_client_mock(
            auth_responses=[make_json_response(200, fake_auth_payload)],
            data_responses=[
                make_response(200, MINIMAL_BIBLIO_XML),
                make_response(200, MINIMAL_BIBLIO_XML),
            ],
        )
        with patch.object(epo_client_module.httpx, "AsyncClient", mock_class):
            await client.get_biblio("EP.1000000.A1")
            await client.get_biblio("EP.1000000.A1")

        instance = mock_class.return_value.__aenter__.return_value
        # One auth POST, two data GETs
        assert instance.post.await_count == 1
        assert instance.request.await_count == 2

    @pytest.mark.asyncio
    async def test_token_refreshes_past_safety_window(self, client, fake_auth_payload):
        mock_class = _build_async_client_mock(
            auth_responses=[
                make_json_response(200, fake_auth_payload),
                make_json_response(200, {**fake_auth_payload, "access_token": "new-token"}),
            ],
            data_responses=[
                make_response(200, MINIMAL_BIBLIO_XML),
                make_response(200, MINIMAL_BIBLIO_XML),
            ],
        )

        # First get_biblio: 3 monotonic calls (fast-path check, slow-path recheck, store
        # expires_at). Second get_biblio: the 4th call is the fast-path check and must
        # return a time past `expires_at - safety_window` to force a refresh.
        monotonic_calls = iter([0.0, 0.0, 0.0, 2_000.0, 2_000.0, 2_000.0, 2_000.0])

        def fake_monotonic() -> float:
            try:
                return next(monotonic_calls)
            except StopIteration:
                return 2_000.0

        with (
            patch.object(epo_client_module.httpx, "AsyncClient", mock_class),
            patch.object(epo_client_module.time, "monotonic", side_effect=fake_monotonic),
        ):
            await client.get_biblio("EP.1000000.A1")
            await client.get_biblio("EP.1000000.A1")

        instance = mock_class.return_value.__aenter__.return_value
        assert instance.post.await_count == 2
        assert client._token == "new-token"

    @pytest.mark.asyncio
    async def test_concurrent_refresh_single_flight(self, fake_auth_payload):
        client = EpoClient(consumer_key="k", consumer_secret="s", timeout=1.0)

        call_counter = {"posts": 0}

        async def post_side_effect(*args, **kwargs):
            call_counter["posts"] += 1
            await asyncio.sleep(0)
            return make_json_response(200, fake_auth_payload)

        async def request_side_effect(*args, **kwargs):
            return make_response(200, MINIMAL_BIBLIO_XML)

        mock_class = MagicMock()
        mock_instance = AsyncMock()
        mock_class.return_value.__aenter__ = AsyncMock(return_value=mock_instance)
        mock_class.return_value.__aexit__ = AsyncMock(return_value=False)
        mock_instance.post = AsyncMock(side_effect=post_side_effect)
        mock_instance.request = AsyncMock(side_effect=request_side_effect)

        with patch.object(epo_client_module.httpx, "AsyncClient", mock_class):
            await asyncio.gather(*(client.get_biblio("EP.1000000.A1") for _ in range(10)))

        assert call_counter["posts"] == 1


class TestMidFlight401:
    """401 mid-request should invalidate the token and retry once."""

    @pytest.mark.asyncio
    async def test_401_on_data_endpoint_invalidates_token_and_retries(self, client, fake_auth_payload):
        mock_class = _build_async_client_mock(
            auth_responses=[
                make_json_response(200, fake_auth_payload),  # initial auth
                make_json_response(200, {**fake_auth_payload, "access_token": "second"}),  # re-auth
            ],
            data_responses=[
                make_response(401, content=b"expired"),  # first data call fails
                make_response(200, MINIMAL_BIBLIO_XML),  # second data call succeeds
            ],
        )
        with patch.object(epo_client_module.httpx, "AsyncClient", mock_class):
            result = await client.get_biblio("EP.1000000.A1")

        assert result.doc_id == "EP.1000000.A1"
        instance = mock_class.return_value.__aenter__.return_value
        assert instance.post.await_count == 2  # two auths
        assert instance.request.await_count == 2  # two data attempts
        assert client._token == "second"

    @pytest.mark.asyncio
    async def test_second_401_raises_auth_error(self, client, fake_auth_payload):
        mock_class = _build_async_client_mock(
            auth_responses=[
                make_json_response(200, fake_auth_payload),
                make_json_response(200, {**fake_auth_payload, "access_token": "second"}),
            ],
            data_responses=[
                make_response(401, content=b"expired"),
                make_response(401, content=b"still denied"),
            ],
        )
        with (
            patch.object(epo_client_module.httpx, "AsyncClient", mock_class),
            pytest.raises(EpoAuthError),
        ):
            await client.get_biblio("EP.1000000.A1")


class TestQuotaRetry:
    """HTTP 403 with quota-exceeded header triggers exponential retry."""

    @pytest.mark.asyncio
    async def test_403_quota_retries_then_raises(self, client, fake_auth_payload):
        quota_response = make_response(
            403,
            content=b"quota exceeded",
            headers={"X-Rejection-Reason": "IndividualQuotaPerHour"},
        )
        mock_class = _build_async_client_mock(
            auth_responses=[make_json_response(200, fake_auth_payload)],
            data_responses=[quota_response, quota_response, quota_response],
        )

        with (
            patch.object(epo_client_module.httpx, "AsyncClient", mock_class),
            patch.object(epo_client_module.asyncio, "sleep", new_callable=AsyncMock) as mock_sleep,
            pytest.raises(EpoQuotaExceededError),
        ):
            await client.get_biblio("EP.1000000.A1")

        instance = mock_class.return_value.__aenter__.return_value
        assert instance.request.await_count == 3
        # Exponential backoff: 1s then 2s between attempts 1, 2, 3
        delays = [call.args[0] for call in mock_sleep.await_args_list]
        assert delays == [1.0, 2.0]

    @pytest.mark.asyncio
    async def test_403_non_quota_raises_auth_error_immediately(self, client, fake_auth_payload):
        mock_class = _build_async_client_mock(
            auth_responses=[make_json_response(200, fake_auth_payload)],
            data_responses=[make_response(403, content=b"forbidden")],
        )
        with (
            patch.object(epo_client_module.httpx, "AsyncClient", mock_class),
            pytest.raises(EpoAuthError),
        ):
            await client.get_biblio("EP.1000000.A1")

        instance = mock_class.return_value.__aenter__.return_value
        assert instance.request.await_count == 1  # no retry

    @pytest.mark.asyncio
    async def test_retry_after_header_is_honoured(self, client, fake_auth_payload):
        quota_response = make_response(
            403,
            content=b"quota exceeded",
            headers={
                "X-Rejection-Reason": "IndividualQuotaPerHour",
                "Retry-After": "7",
            },
        )
        mock_class = _build_async_client_mock(
            auth_responses=[make_json_response(200, fake_auth_payload)],
            data_responses=[quota_response, make_response(200, MINIMAL_BIBLIO_XML)],
        )
        with (
            patch.object(epo_client_module.httpx, "AsyncClient", mock_class),
            patch.object(epo_client_module.asyncio, "sleep", new_callable=AsyncMock) as mock_sleep,
        ):
            result = await client.get_biblio("EP.1000000.A1")

        assert result.doc_id == "EP.1000000.A1"
        delays = [call.args[0] for call in mock_sleep.await_args_list]
        assert delays == [7.0]


class TestOtherErrors:
    """404, non-retryable HTTP errors, and timeouts."""

    @pytest.mark.asyncio
    async def test_404_raises_not_found(self, client, fake_auth_payload):
        mock_class = _build_async_client_mock(
            auth_responses=[make_json_response(200, fake_auth_payload)],
            data_responses=[make_response(404, content=b"not found")],
        )
        with (
            patch.object(epo_client_module.httpx, "AsyncClient", mock_class),
            pytest.raises(EpoNotFoundError),
        ):
            await client.get_biblio("EP.9999999.A1")

    @pytest.mark.asyncio
    async def test_timeout_retries_then_fails(self, client, fake_auth_payload):
        mock_class = MagicMock()
        mock_instance = AsyncMock()
        mock_class.return_value.__aenter__ = AsyncMock(return_value=mock_instance)
        mock_class.return_value.__aexit__ = AsyncMock(return_value=False)
        mock_instance.post = AsyncMock(return_value=make_json_response(200, fake_auth_payload))
        mock_instance.request = AsyncMock(side_effect=httpx.TimeoutException("slow"))

        with (
            patch.object(epo_client_module.httpx, "AsyncClient", mock_class),
            patch.object(epo_client_module.asyncio, "sleep", new_callable=AsyncMock),
            pytest.raises(EpoError),
        ):
            await client.get_biblio("EP.1000000.A1")

        assert mock_instance.request.await_count == 3

    @pytest.mark.asyncio
    async def test_unexpected_status_raises_generic_error(self, client, fake_auth_payload):
        mock_class = _build_async_client_mock(
            auth_responses=[make_json_response(200, fake_auth_payload)],
            data_responses=[make_response(500, content=b"boom")],
        )
        with (
            patch.object(epo_client_module.httpx, "AsyncClient", mock_class),
            pytest.raises(EpoError),
        ):
            await client.get_biblio("EP.1000000.A1")


class TestInputValidation:
    """`_validate_doc_id` and empty inputs."""

    def test_init_requires_both_credentials(self):
        with pytest.raises(EpoAuthError):
            EpoClient(consumer_key="", consumer_secret="s")
        with pytest.raises(EpoAuthError):
            EpoClient(consumer_key="k", consumer_secret="")

    @pytest.mark.asyncio
    async def test_get_biblio_rejects_path_traversal(self, client):
        with pytest.raises(ValueError):
            await client.get_biblio("../../etc/passwd")

    @pytest.mark.asyncio
    async def test_get_biblio_rejects_empty(self, client):
        with pytest.raises(ValueError):
            await client.get_biblio("")

    @pytest.mark.asyncio
    async def test_search_patents_rejects_empty(self, client):
        with pytest.raises(ValueError):
            await client.search_patents("   ")


class TestCredentialRedaction:
    """Credentials, Basic header and bearer token must never appear in logs."""

    @pytest.mark.asyncio
    async def test_credentials_not_in_logs(self, client, fake_auth_payload, caplog):
        caplog.set_level("DEBUG", logger="app.infrastructure.epo.client")
        mock_class = _build_async_client_mock(
            auth_responses=[make_json_response(200, fake_auth_payload)],
            data_responses=[make_response(200, MINIMAL_BIBLIO_XML)],
        )
        with patch.object(epo_client_module.httpx, "AsyncClient", mock_class):
            await client.get_biblio("EP.1000000.A1")

        transcript = "\n".join(
            [r.getMessage() for r in caplog.records]
            + [json.dumps(getattr(r, "__dict__", {}), default=str) for r in caplog.records]
        )
        assert "fake-consumer-key" not in transcript
        assert "fake-consumer-secret" not in transcript
        assert "fake-bearer-token-xyz" not in transcript

    def test_safety_window_constant_is_reasonable(self):
        # Guard against accidental edits that would make refresh ineffective.
        assert 10 <= TOKEN_SAFETY_WINDOW_SECONDS <= 300
