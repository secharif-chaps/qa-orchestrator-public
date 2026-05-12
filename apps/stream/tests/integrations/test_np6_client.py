"""Tests for the low-level NP6Client wrapper.

Locks down the wire-level invariants we cannot afford to drift on:
- `X-Key` header on every call (NP6 OpenAPI 8.1.0)
- `application/vnd.np6.cm.email` content-type on /execution(s)
- 5xx and transport errors retried up to 3 times with backoff
- 4xx surfaces immediately (in particular 409, which the orchestrator
  uses as an idempotency signal for create_target / validate_action)
"""

from __future__ import annotations

import json
from collections.abc import Callable
from unittest.mock import patch

import httpx
import pytest

from app.integrations.email.np6_client import NP6Client


def _make_client(
    handler: Callable[[httpx.Request], httpx.Response],
    *,
    api_key: str = "test-key",
    base_url: str = "https://np6.example",
) -> NP6Client:
    def factory(*args, **kwargs):
        kwargs.pop("transport", None)
        return httpx.AsyncClient(*args, transport=httpx.MockTransport(handler), **kwargs)

    return NP6Client(base_url=base_url, api_key=api_key, client_factory=factory)


class TestConstructor:
    def test_rejects_empty_base_url(self):
        with pytest.raises(ValueError):
            NP6Client(base_url="", api_key="k")

    def test_rejects_empty_api_key(self):
        with pytest.raises(ValueError):
            NP6Client(base_url="https://x", api_key="")


class TestAuthAndContentType:
    @pytest.mark.asyncio
    async def test_x_key_header_on_create_action(self):
        captured: list[dict] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured.append(dict(request.headers))
            return httpx.Response(201, json={"id": "act-1"})

        client = _make_client(handler)
        await client.create_action({"type": "mailMessage"})

        assert captured[0].get("x-key") == "test-key"
        assert captured[0].get("content-type") == "application/json"

    @pytest.mark.asyncio
    async def test_email_mime_on_executions_plural(self):
        captured: list[dict] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured.append(dict(request.headers))
            return httpx.Response(200, json=[])

        client = _make_client(handler)
        await client.execute_recipients(
            "act-1",
            [{"recipient": {"type": "unicity", "value": ["a@example.com"]}}],
        )

        assert captured[0].get("content-type") == "application/vnd.np6.cm.email"

    @pytest.mark.asyncio
    async def test_email_mime_on_execution_singular(self):
        captured: list[dict] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured.append(dict(request.headers))
            return httpx.Response(200, json={"type": "success", "id": "msg-1"})

        client = _make_client(handler)
        await client.execute_recipient(
            "act-1",
            {"recipient": {"type": "unicity", "value": ["a@example.com"]}},
        )

        assert captured[0].get("content-type") == "application/vnd.np6.cm.email"

    @pytest.mark.asyncio
    async def test_validation_uses_json_mime(self):
        captured: list[dict] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured.append(dict(request.headers))
            return httpx.Response(204)

        client = _make_client(handler)
        await client.validate_action("act-1", {"fortest": False})

        assert captured[0].get("content-type") == "application/json"


class TestRetryPolicy:
    @pytest.mark.asyncio
    async def test_5xx_retried_three_times_then_fails(self):
        attempts: list[int] = []

        def handler(request: httpx.Request) -> httpx.Response:
            attempts.append(1)
            return httpx.Response(503, json={"error": "down"})

        client = _make_client(handler)
        with (
            patch("app.integrations.email.np6_client.asyncio.sleep"),
            pytest.raises(httpx.HTTPStatusError),
        ):
            await client.create_action({"type": "mailMessage"})

        assert len(attempts) == 3

    @pytest.mark.asyncio
    async def test_5xx_then_201_succeeds(self):
        attempts: list[int] = []

        def handler(request: httpx.Request) -> httpx.Response:
            attempts.append(1)
            if len(attempts) < 3:
                return httpx.Response(503, json={"error": "transient"})
            return httpx.Response(201, json={"id": "act-1"})

        client = _make_client(handler)
        with patch("app.integrations.email.np6_client.asyncio.sleep"):
            response = await client.create_action({"type": "mailMessage"})

        assert response == {"id": "act-1"}
        assert len(attempts) == 3

    @pytest.mark.asyncio
    async def test_4xx_does_not_retry(self):
        attempts: list[int] = []

        def handler(request: httpx.Request) -> httpx.Response:
            attempts.append(1)
            return httpx.Response(400, json={"error": "bad"})

        client = _make_client(handler)
        with pytest.raises(httpx.HTTPStatusError):
            await client.create_action({"type": "mailMessage"})

        assert len(attempts) == 1

    @pytest.mark.asyncio
    async def test_409_propagates_immediately(self):
        attempts: list[int] = []

        def handler(request: httpx.Request) -> httpx.Response:
            attempts.append(1)
            return httpx.Response(409, json={"error": "target already exists"})

        client = _make_client(handler)
        with pytest.raises(httpx.HTTPStatusError) as exc_info:
            await client.create_target({"Email": "a@example.com"})

        assert exc_info.value.response.status_code == 409
        assert len(attempts) == 1

    @pytest.mark.asyncio
    async def test_transport_error_triggers_retry(self):
        attempts: list[int] = []

        def handler(request: httpx.Request) -> httpx.Response:
            attempts.append(1)
            if len(attempts) == 1:
                raise httpx.ConnectError("network blip")
            return httpx.Response(201, json={"id": "act-1"})

        client = _make_client(handler)
        with patch("app.integrations.email.np6_client.asyncio.sleep"):
            response = await client.create_action({"type": "mailMessage"})

        assert response == {"id": "act-1"}
        assert len(attempts) == 2


class TestValidationEndpoints:
    @pytest.mark.asyncio
    async def test_validate_action_post_with_body(self):
        captured_paths: list[str] = []
        captured_bodies: list[dict] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured_paths.append(request.url.path)
            captured_bodies.append(json.loads(request.content) if request.content else {})
            return httpx.Response(204)

        client = _make_client(handler)
        await client.validate_action("act-1", {"fortest": True, "testSegments": [1319]})

        assert captured_paths == ["/actions/act-1/validation"]
        assert captured_bodies[0] == {"fortest": True, "testSegments": [1319]}

    @pytest.mark.asyncio
    async def test_unvalidate_action_uses_delete(self):
        captured_methods: list[str] = []
        captured_paths: list[str] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured_methods.append(request.method)
            captured_paths.append(request.url.path)
            return httpx.Response(204)

        client = _make_client(handler)
        await client.unvalidate_action("act-1")

        assert captured_methods == ["DELETE"]
        assert captured_paths == ["/actions/act-1/validation"]


class TestTargets:
    @pytest.mark.asyncio
    async def test_create_target_uses_field_name_at_top_level(self):
        captured: list[dict] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured.append(json.loads(request.content))
            return httpx.Response(201, json={"id": "tgt-1"})

        client = _make_client(handler)
        await client.create_target({"Email": "alice@example.com"})

        # NP6 quirk: POST /targets uses field NAMES at top-level (asymmetric vs GET).
        assert captured[0] == {"Email": "alice@example.com"}

    @pytest.mark.asyncio
    async def test_create_target_409_propagates(self):
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(409, json={"error": "target already exists"})

        client = _make_client(handler)
        with pytest.raises(httpx.HTTPStatusError) as exc_info:
            await client.create_target({"Email": "alice@example.com"})
        assert exc_info.value.response.status_code == 409

    @pytest.mark.asyncio
    async def test_find_target_by_unicity_returns_first_match(self):
        def handler(request: httpx.Request) -> httpx.Response:
            assert request.url.params.get("unicity") == "alice@example.com"
            return httpx.Response(200, json=[{"id": "tgt-7"}])

        client = _make_client(handler)
        result = await client.find_target_by_unicity("alice@example.com")
        assert result == {"id": "tgt-7"}

    @pytest.mark.asyncio
    async def test_find_target_by_unicity_returns_none_when_empty(self):
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(200, json=[])

        client = _make_client(handler)
        result = await client.find_target_by_unicity("nobody@example.com")
        assert result is None


class TestExecutions:
    @pytest.mark.asyncio
    async def test_execute_recipient_singular_path(self):
        captured_paths: list[str] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured_paths.append(request.url.path)
            return httpx.Response(200, json={"type": "success", "id": "msg-1"})

        client = _make_client(handler)
        await client.execute_recipient(
            "act-7",
            {"recipient": {"type": "unicity", "value": ["a@example.com"]}},
        )

        assert captured_paths == ["/actions/act-7/execution"]

    @pytest.mark.asyncio
    async def test_execute_recipients_plural_path_and_returns_list(self):
        captured_paths: list[str] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured_paths.append(request.url.path)
            return httpx.Response(
                200,
                json=[
                    {"type": "success", "id": "msg-1"},
                    {"type": "error", "value": {"type": "recipient not found"}},
                ],
            )

        client = _make_client(handler)
        results = await client.execute_recipients(
            "act-7",
            [
                {"recipient": {"type": "unicity", "value": ["a@example.com"]}},
                {"recipient": {"type": "unicity", "value": ["b@example.com"]}},
            ],
        )

        assert captured_paths == ["/actions/act-7/executions"]
        assert len(results) == 2
        assert results[0]["type"] == "success"
        assert results[1]["type"] == "error"
