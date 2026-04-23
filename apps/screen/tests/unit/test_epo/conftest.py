"""Shared fixtures and helpers for EPO client tests."""

from __future__ import annotations

from unittest.mock import AsyncMock

import httpx
import pytest

from app.infrastructure.epo.client import EpoClient


@pytest.fixture
def client() -> EpoClient:
    """A fresh EpoClient with fake credentials and a short timeout."""
    return EpoClient(
        consumer_key="fake-consumer-key",
        consumer_secret="fake-consumer-secret",
        timeout=5.0,
    )


def make_response(
    status_code: int,
    content: bytes = b"",
    headers: dict[str, str] | None = None,
    method: str = "GET",
    url: str = "https://ops.epo.org/3.2/test",
) -> httpx.Response:
    """Build a realistic `httpx.Response` for a mocked API call."""
    response_headers = {"content-type": "application/xml"} if headers is None else dict(headers)
    return httpx.Response(
        status_code=status_code,
        content=content,
        headers=response_headers,
        request=httpx.Request(method, url),
    )


def make_json_response(
    status_code: int,
    payload: dict,
    url: str = "https://ops.epo.org/3.2/auth/accesstoken",
) -> httpx.Response:
    import json

    return httpx.Response(
        status_code=status_code,
        content=json.dumps(payload).encode("utf-8"),
        headers={"content-type": "application/json"},
        request=httpx.Request("POST", url),
    )


def make_async_client_mock(*responses: httpx.Response) -> AsyncMock:
    """Mock `httpx.AsyncClient` so both `.post` and `.request` return `responses` in order."""
    mock_client = AsyncMock()
    mock_client.request = AsyncMock(side_effect=list(responses))
    mock_client.post = AsyncMock(side_effect=list(responses))
    return mock_client


@pytest.fixture
def fake_auth_payload() -> dict:
    """Typical EPO access-token JSON payload."""
    return {
        "access_token": "fake-bearer-token-xyz",
        "token_type": "BearerToken",
        "expires_in": 1200,
        "application_name": "test-app",
    }
