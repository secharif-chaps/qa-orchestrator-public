"""Tests for `NP6EmailProvider` (validated workflow, audit 2026-05-04).

Pin the externally observable contract:
- Action payload shape (mailMessage discriminator, sender stamped from
  NP6_FROM_EMAIL, X-Key header).
- 2-phase validation: GET state precheck, POST phase-1 with testSegments,
  POST phase-2 with `fortest:false`, idempotent on state>=50 and on 409.
- `upsert_target`: POST /targets with field NAME at top-level, 409 → no-op.
- `execute_to_recipients`: array body with `unicity` wrapped, normalized
  results.
- `send` orchestrates create_action → validate_action → upsert_target →
  execute_to_recipients on the same action id.
- Event normalization unchanged (bounce/hit/complaint).
"""

from __future__ import annotations

import json
from collections.abc import Callable
from datetime import UTC, datetime
from unittest.mock import patch

import httpx
import pytest

from app.core.config import Settings
from app.integrations.email import (
    EmailSendRequest,
    NP6EmailProvider,
)
from app.integrations.email.np6_client import NP6Client


def _make_settings(**overrides) -> Settings:
    base = {
        "NP6_BASE_URL": "https://np6.example",
        "NP6_API_KEY": "test-key",
        "NP6_FROM_EMAIL": "noreply@chapsmind.com",
        "NP6_BAT_TEST_SEGMENT_ID": 1319,
    }
    base.update(overrides)
    return Settings(_env_file=None, **base)


def _make_provider_with_handler(
    handler: Callable[[httpx.Request], httpx.Response],
    **settings_overrides,
) -> NP6EmailProvider:
    """Build a provider whose NP6Client speaks to a MockTransport handler."""
    settings = _make_settings(**settings_overrides)

    def _client_factory(*args, **kwargs):
        kwargs.pop("transport", None)
        return httpx.AsyncClient(*args, transport=httpx.MockTransport(handler), **kwargs)

    np6_client = NP6Client(
        base_url=settings.NP6_BASE_URL,
        api_key=settings.NP6_API_KEY,
        client_factory=_client_factory,
    )
    return NP6EmailProvider(settings=settings, client=np6_client)


# -------------------------------------------------------------------- constructor


class TestNP6ProviderConstructor:
    def test_rejects_missing_base_url(self):
        with pytest.raises(ValueError, match="NP6_BASE_URL"):
            NP6EmailProvider(settings=_make_settings(NP6_BASE_URL=""))

    def test_rejects_missing_api_key(self):
        with pytest.raises(ValueError, match="NP6_API_KEY"):
            NP6EmailProvider(settings=_make_settings(NP6_API_KEY=""))

    def test_rejects_missing_from_email(self):
        with pytest.raises(ValueError, match="NP6_FROM_EMAIL"):
            NP6EmailProvider(settings=_make_settings(NP6_FROM_EMAIL=""))


# ---------------------------------------------------------------- create_action


class TestCreateAction:
    @pytest.mark.asyncio
    async def test_payload_uses_mailMessage_discriminator(self):
        captured: list[dict] = []

        def handler(request: httpx.Request) -> httpx.Response:
            if request.url.path == "/actions" and request.method == "POST":
                captured.append(json.loads(request.content))
                return httpx.Response(201, json={"id": "act-1"})
            return httpx.Response(404)

        provider = _make_provider_with_handler(handler)
        action_id = await provider.create_action(name="ad-hoc", subject="Hello", html="<p>body</p>")

        assert action_id == "act-1"
        assert len(captured) == 1
        assert captured[0]["type"] == "mailMessage"
        assert captured[0]["content"]["subject"] == "Hello"
        assert captured[0]["content"]["html"] == "<p>body</p>"

    @pytest.mark.asyncio
    async def test_sender_invariant_from_components_stamped(self):
        """`content.headers.from` must be {prefix, domain, label} from NP6_FROM_EMAIL."""
        captured: list[dict] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured.append(json.loads(request.content))
            return httpx.Response(201, json={"id": "act-1"})

        provider = _make_provider_with_handler(handler)
        await provider.create_action(name="x", subject="y", html="<p/>")

        from_obj = captured[0]["content"]["headers"]["from"]
        assert from_obj["prefix"] == "noreply"
        assert from_obj["domain"] == "chapsmind.com"
        assert from_obj["label"]  # non-empty label, content not asserted

    @pytest.mark.asyncio
    async def test_x_key_header_carries_api_key(self):
        captured_headers: list[dict] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured_headers.append(dict(request.headers))
            return httpx.Response(201, json={"id": "act-1"})

        provider = _make_provider_with_handler(handler)
        await provider.create_action(name="x", subject="y", html="<p/>")

        assert captured_headers[0].get("x-key") == "test-key"
        # Negative assertion: never the speculative Bearer scheme
        assert "bearer" not in captured_headers[0].get("authorization", "").lower()

    @pytest.mark.asyncio
    async def test_missing_id_in_response_raises(self):
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(201, json={})

        provider = _make_provider_with_handler(handler)
        with pytest.raises(ValueError, match="missing id"):
            await provider.create_action(name="x", subject="y", html="<p/>")


# ------------------------------------------------------------------- validation


class TestValidateAction:
    @pytest.mark.asyncio
    async def test_two_phase_order_when_action_in_state_20(self):
        """GET first; if state < 50, POST phase-1 (BAT) then phase-2 (prod)."""
        calls: list[tuple[str, str, dict]] = []

        def handler(request: httpx.Request) -> httpx.Response:
            body = json.loads(request.content) if request.content else {}
            calls.append((request.method, request.url.path, body))
            if request.method == "GET" and request.url.path == "/actions/act-1":
                return httpx.Response(200, json={"id": "act-1", "state": 20})
            if request.method == "POST" and request.url.path == "/actions/act-1/validation":
                return httpx.Response(204)
            return httpx.Response(404)

        provider = _make_provider_with_handler(handler)
        await provider.validate_action("act-1", bat_test_segment_id=1319)

        assert calls[0] == ("GET", "/actions/act-1", {})
        assert calls[1] == (
            "POST",
            "/actions/act-1/validation",
            {"fortest": True, "testSegments": [1319]},
        )
        assert calls[2] == ("POST", "/actions/act-1/validation", {"fortest": False})

    @pytest.mark.asyncio
    async def test_idempotent_when_already_state_50(self):
        """If GET reports state >= 50, no validation calls are made."""
        calls: list[str] = []

        def handler(request: httpx.Request) -> httpx.Response:
            calls.append(f"{request.method} {request.url.path}")
            if request.method == "GET":
                return httpx.Response(200, json={"id": "act-1", "state": 50})
            return httpx.Response(500, json={"error": "should not have been called"})

        provider = _make_provider_with_handler(handler)
        await provider.validate_action("act-1", bat_test_segment_id=1319)

        assert calls == ["GET /actions/act-1"]

    @pytest.mark.asyncio
    async def test_409_treated_as_already_validated(self):
        """A 409 from a validation phase is concurrent-success, not an error."""
        calls: list[tuple[str, dict]] = []

        def handler(request: httpx.Request) -> httpx.Response:
            if request.method == "GET":
                return httpx.Response(200, json={"id": "act-1", "state": 20})
            body = json.loads(request.content) if request.content else {}
            calls.append((request.url.path, body))
            return httpx.Response(409, json={"error": "already validated"})

        provider = _make_provider_with_handler(handler)
        # Should not raise — both phases swallow 409.
        await provider.validate_action("act-1", bat_test_segment_id=1319)

        # Both phase calls were attempted.
        assert len(calls) == 2

    @pytest.mark.asyncio
    async def test_non_409_validation_error_propagates(self):
        def handler(request: httpx.Request) -> httpx.Response:
            if request.method == "GET":
                return httpx.Response(200, json={"id": "act-1", "state": 20})
            return httpx.Response(400, json={"error": "bad"})

        provider = _make_provider_with_handler(handler)
        with pytest.raises(httpx.HTTPStatusError):
            await provider.validate_action("act-1", bat_test_segment_id=1319)


# ----------------------------------------------------------------- upsert_target


class TestUpsertTarget:
    @pytest.mark.asyncio
    async def test_creates_with_email_field_name_at_top_level(self):
        captured: list[dict] = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured.append(json.loads(request.content))
            return httpx.Response(201, json={"id": "tgt-1"})

        provider = _make_provider_with_handler(handler)
        await provider.upsert_target("alice@example.com")

        # Field NAME at top-level — NP6 asymmetric quirk (docs/np6/README.md piège #3).
        assert captured[0] == {"Email": "alice@example.com"}

    @pytest.mark.asyncio
    async def test_409_already_exists_is_idempotent_success(self):
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(409, json={"error": "target already exists"})

        provider = _make_provider_with_handler(handler)
        # No raise expected — 409 is success per the validated workflow.
        await provider.upsert_target("alice@example.com")

    @pytest.mark.asyncio
    async def test_other_4xx_propagates(self):
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(400, json={"error": "bad payload"})

        provider = _make_provider_with_handler(handler)
        with pytest.raises(httpx.HTTPStatusError):
            await provider.upsert_target("alice@example.com")


# ------------------------------------------------------------ execute_to_recipients


class TestExecuteToRecipients:
    @pytest.mark.asyncio
    async def test_unicity_value_wrapped_as_array_per_recipient(self):
        captured: list = []

        def handler(request: httpx.Request) -> httpx.Response:
            captured.append(json.loads(request.content))
            return httpx.Response(
                200,
                json=[
                    {"type": "success", "id": "msg-1", "recipient": {"unicity": "a@example.com"}},
                    {"type": "success", "id": "msg-2", "recipient": {"unicity": "b@example.com"}},
                ],
            )

        provider = _make_provider_with_handler(handler)
        results = await provider.execute_to_recipients(
            "act-1",
            ["a@example.com", "b@example.com"],
        )

        # Body is a list with one item per recipient, each addressing by unicity (array value).
        assert isinstance(captured[0], list)
        assert len(captured[0]) == 2
        assert captured[0][0]["recipient"] == {"type": "unicity", "value": ["a@example.com"]}
        assert captured[0][1]["recipient"] == {"type": "unicity", "value": ["b@example.com"]}
        assert len(results) == 2
        assert all(r.success for r in results)
        assert results[0].message_id == "msg-1"

    @pytest.mark.asyncio
    async def test_mixed_success_and_error_normalized(self):
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(
                200,
                json=[
                    {"type": "success", "id": "msg-1", "recipient": {}},
                    {
                        "type": "error",
                        "value": {"type": "recipient not found"},
                    },
                ],
            )

        provider = _make_provider_with_handler(handler)
        results = await provider.execute_to_recipients("act-1", ["ok@x.com", "bad@x.com"])

        assert len(results) == 2
        assert results[0].success
        assert not results[1].success
        assert results[1].error_type == "recipient not found"

    @pytest.mark.asyncio
    async def test_empty_email_list_short_circuits(self):
        def handler(request: httpx.Request) -> httpx.Response:
            raise AssertionError("client must not be called for empty input")

        provider = _make_provider_with_handler(handler)
        results = await provider.execute_to_recipients("act-1", [])
        assert results == []


# ------------------------------------------------------------- send (ad-hoc path)


class TestSend:
    @pytest.mark.asyncio
    async def test_happy_path_orchestrates_full_workflow(self):
        """create_action → GET state → 2-phase validation → upsert_target → execute."""
        calls: list[str] = []

        def handler(request: httpx.Request) -> httpx.Response:
            calls.append(f"{request.method} {request.url.path}")
            if request.method == "POST" and request.url.path == "/actions":
                return httpx.Response(201, json={"id": "act-7"})
            if request.method == "GET" and request.url.path == "/actions/act-7":
                return httpx.Response(200, json={"id": "act-7", "state": 20})
            if request.method == "POST" and request.url.path == "/actions/act-7/validation":
                return httpx.Response(204)
            if request.method == "POST" and request.url.path == "/targets":
                return httpx.Response(201, json={"id": "tgt-1"})
            if request.method == "POST" and request.url.path == "/actions/act-7/executions":
                return httpx.Response(
                    200,
                    json=[
                        {
                            "type": "success",
                            "id": "msg-42",
                            "recipient": {"unicity": "alice@example.com"},
                        }
                    ],
                )
            return httpx.Response(404)

        provider = _make_provider_with_handler(handler)
        result = await provider.send(
            EmailSendRequest(
                subject="Hello",
                html="<p>body</p>",
                recipients=["alice@example.com"],
            )
        )

        assert result.success
        assert result.message_id == "msg-42"
        # Order: create action, GET state, validation x2, upsert target, executions.
        assert calls == [
            "POST /actions",
            "GET /actions/act-7",
            "POST /actions/act-7/validation",
            "POST /actions/act-7/validation",
            "POST /targets",
            "POST /actions/act-7/executions",
        ]

    @pytest.mark.asyncio
    async def test_5xx_during_create_retries_then_succeeds(self):
        attempts: list[int] = []

        def handler(request: httpx.Request) -> httpx.Response:
            attempts.append(1)
            if request.url.path == "/actions" and len(attempts) < 3:
                return httpx.Response(503, json={"error": "transient"})
            if request.method == "POST" and request.url.path == "/actions":
                return httpx.Response(201, json={"id": "act-1"})
            if request.method == "GET" and request.url.path == "/actions/act-1":
                return httpx.Response(200, json={"state": 20})
            if request.url.path == "/actions/act-1/validation":
                return httpx.Response(204)
            if request.url.path == "/targets":
                return httpx.Response(201, json={"id": "tgt-1"})
            if request.url.path == "/actions/act-1/executions":
                return httpx.Response(
                    200,
                    json=[{"type": "success", "id": "msg-1", "recipient": {}}],
                )
            return httpx.Response(404)

        with patch("app.integrations.email.np6_client.asyncio.sleep") as mock_sleep:
            provider = _make_provider_with_handler(handler)
            result = await provider.send(EmailSendRequest(subject="x", html="<p/>", recipients=["a@example.com"]))

        assert result.success
        assert mock_sleep.call_count >= 1

    @pytest.mark.asyncio
    async def test_execution_error_returns_failure(self):
        def handler(request: httpx.Request) -> httpx.Response:
            if request.method == "POST" and request.url.path == "/actions":
                return httpx.Response(201, json={"id": "act-1"})
            if request.method == "GET" and request.url.path == "/actions/act-1":
                return httpx.Response(200, json={"state": 20})
            if request.url.path == "/actions/act-1/validation":
                return httpx.Response(204)
            if request.url.path == "/targets":
                return httpx.Response(409, json={"error": "exists"})
            if request.url.path == "/actions/act-1/executions":
                return httpx.Response(
                    200,
                    json=[{"type": "error", "value": {"type": "recipient not found"}}],
                )
            return httpx.Response(404)

        provider = _make_provider_with_handler(handler)
        result = await provider.send(EmailSendRequest(subject="x", html="<p/>", recipients=["a@example.com"]))

        assert not result.success
        assert result.error == "recipient not found"


# ------------------------------------------------------------------- pull_events


class TestPullEvents:
    @pytest.mark.asyncio
    async def test_bounce_hard_normalised(self):
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(
                200,
                json=[
                    {
                        "type": "bounce",
                        "id": "evt-1",
                        "timestamp": 1614089593000,
                        "source": {
                            "type": "email",
                            "detail": {
                                "activation": {
                                    "id": "act-1",
                                    "kind": "message",
                                    "receiver": {
                                        "type": "id",
                                        "value": "tgt-1",
                                    },
                                    "stamp": {"id": "msg-1", "time": 1614089576625},
                                    "recipient": "alice@example.com",
                                },
                                "bounce": {
                                    "analysis": {"verdict": "hard"},
                                },
                            },
                        },
                    }
                ],
            )

        provider = _make_provider_with_handler(handler)
        now = datetime.now(UTC)
        events = await provider.pull_events(since=now, until=now)

        assert len(events) == 1
        assert events[0].event_type == "bounce_hard"
        assert events[0].email == "alice@example.com"
        assert events[0].message_id == "msg-1"

    @pytest.mark.asyncio
    async def test_hit_open_normalised(self):
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(
                200,
                json=[
                    {
                        "type": "hit",
                        "id": "evt-2",
                        "timestamp": 1623673176622,
                        "source": {
                            "type": "email",
                            "detail": {
                                "activation": {
                                    "stamp": {"id": "msg-2"},
                                    "recipient": "bob@example.com",
                                },
                                "link": {"type": "open"},
                            },
                        },
                    }
                ],
            )

        provider = _make_provider_with_handler(handler)
        now = datetime.now(UTC)
        events = await provider.pull_events(since=now, until=now)

        assert events[0].event_type == "open"

    @pytest.mark.asyncio
    async def test_unknown_event_skipped(self):
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(
                200,
                json=[
                    {"type": "mystery", "id": "evt-x", "timestamp": 0},
                    {
                        "type": "complaint",
                        "id": "evt-y",
                        "timestamp": 0,
                        "source": {"detail": {"activation": {"recipient": "c@example.com"}}},
                    },
                ],
            )

        provider = _make_provider_with_handler(handler)
        now = datetime.now(UTC)
        events = await provider.pull_events(since=now, until=now)

        # Mystery event dropped, complaint kept.
        assert len(events) == 1
        assert events[0].event_type == "complaint"
