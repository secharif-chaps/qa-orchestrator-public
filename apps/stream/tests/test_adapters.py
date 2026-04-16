"""Unit tests for channel adapters."""

import hashlib
import hmac
import json
from unittest.mock import AsyncMock, patch

import httpx
import pytest

from app.adapters.base import DispatchResult
from app.adapters.factory import get_adapter
from app.adapters.slack import SlackWebhookAdapter
from app.adapters.teams import TeamsAdapter
from app.adapters.webhook import WebhookAdapter
from app.models.event import StreamEvent
from app.models.stream import ChannelType, Stream, StreamMode, StreamStatus


def _make_event(**overrides) -> StreamEvent:
    defaults = {
        "id": 1,
        "event_type": "screen.company.created",
        "source": "screen",
        "payload": {"company_id": 42, "company_name": "Acme Corp"},
        "organization_id": "test-org-123",
        "folder_id": "folder-abc",
        "summary": "Company Acme Corp was created",
        "entity_type": "company",
        "entity_id": "42",
    }
    defaults.update(overrides)
    return StreamEvent(**defaults)


def _make_stream(channel_type: ChannelType = ChannelType.WEBHOOK, **config_overrides) -> Stream:
    configs = {
        ChannelType.WEBHOOK: {"url": "https://example.com/hook", "headers": {}, "secret": None},
        ChannelType.TEAMS: {"workflow_url": "https://teams.example.com/hook"},
        ChannelType.SLACK_WEBHOOK: {"webhook_url": "https://hooks.slack.com/services/xxx"},
    }
    config = configs[channel_type].copy()
    config.update(config_overrides)

    return Stream(
        id=1,
        name="Test Stream",
        channel_type=channel_type,
        channel_config=config,
        mode=StreamMode.LIVE,
        status=StreamStatus.ACTIVE,
        folder_id="folder-abc",
        organization_id="test-org-123",
        owner_id="user-1",
        subscribed_events=["screen.company.created"],
    )


# --- Factory tests ---


class TestAdapterFactory:
    def test_get_teams_adapter(self):
        adapter = get_adapter(ChannelType.TEAMS)
        assert isinstance(adapter, TeamsAdapter)

    def test_get_slack_adapter(self):
        adapter = get_adapter(ChannelType.SLACK_WEBHOOK)
        assert isinstance(adapter, SlackWebhookAdapter)

    def test_get_webhook_adapter(self):
        adapter = get_adapter(ChannelType.WEBHOOK)
        assert isinstance(adapter, WebhookAdapter)

    def test_unknown_channel_type_raises(self):
        with pytest.raises(ValueError, match="No adapter registered"):
            get_adapter("nonexistent")  # type: ignore[arg-type]


# --- Teams adapter tests ---


class TestTeamsAdapter:
    def test_format_payload_structure(self):
        adapter = TeamsAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.TEAMS)

        payload = adapter.format_payload(event, stream)

        assert payload["type"] == "message"
        assert len(payload["attachments"]) == 1
        card = payload["attachments"][0]["content"]
        assert card["type"] == "AdaptiveCard"
        assert card["version"] == "1.4"
        # Title should be stream name without "Stream:" prefix
        assert card["body"][0]["text"] == "Test Stream"

    def test_format_payload_includes_summary(self):
        adapter = TeamsAdapter()
        event = _make_event(summary="Test summary")
        stream = _make_stream(ChannelType.TEAMS)

        payload = adapter.format_payload(event, stream)
        card = payload["attachments"][0]["content"]
        texts = [b.get("text", "") for b in card["body"]]
        assert "Test summary" in texts

    def test_format_payload_without_summary(self):
        adapter = TeamsAdapter()
        event = _make_event(summary=None, payload={})
        stream = _make_stream(ChannelType.TEAMS)

        payload = adapter.format_payload(event, stream)
        card = payload["attachments"][0]["content"]
        # Title + footer only (no summary, no factset)
        assert len(card["body"]) == 2

    def test_format_payload_includes_company_details(self):
        adapter = TeamsAdapter()
        event = _make_event(payload={"company_name": "ChapsVision"})
        stream = _make_stream(ChannelType.TEAMS)

        payload = adapter.format_payload(event, stream)
        card = payload["attachments"][0]["content"]
        factsets = [b for b in card["body"] if b.get("type") == "FactSet"]
        assert len(factsets) == 1
        fact_titles = [f["title"] for f in factsets[0]["facts"]]
        assert "Entreprise" in fact_titles

    def test_format_payload_includes_action_link(self):
        adapter = TeamsAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.TEAMS)

        payload = adapter.format_payload(event, stream)
        card = payload["attachments"][0]["content"]
        assert "actions" in card
        assert card["actions"][0]["type"] == "Action.OpenUrl"
        assert "Voir la fiche entreprise" in card["actions"][0]["title"]

    def test_format_payload_no_action_without_entity(self):
        adapter = TeamsAdapter()
        event = _make_event(entity_type=None, entity_id=None, payload={})
        stream = _make_stream(ChannelType.TEAMS)

        payload = adapter.format_payload(event, stream)
        card = payload["attachments"][0]["content"]
        assert "actions" not in card

    def test_format_payload_footer(self):
        adapter = TeamsAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.TEAMS)

        payload = adapter.format_payload(event, stream)
        card = payload["attachments"][0]["content"]
        footer = card["body"][-1]
        assert footer["isSubtle"] is True
        assert "Screen" in footer["text"]

    @pytest.mark.asyncio
    async def test_send_success(self):
        adapter = TeamsAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.TEAMS)

        mock_response = httpx.Response(200, text="ok")

        with patch("app.adapters.teams.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is True
        assert result.status_code == 200

    @pytest.mark.asyncio
    async def test_send_http_error(self):
        adapter = TeamsAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.TEAMS)

        mock_response = httpx.Response(400, text="Bad Request")

        with patch("app.adapters.teams.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is False
        assert result.status_code == 400

    @pytest.mark.asyncio
    async def test_send_timeout(self):
        adapter = TeamsAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.TEAMS)

        with patch("app.adapters.teams.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.post = AsyncMock(side_effect=httpx.TimeoutException("timeout"))
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is False
        assert "timed out" in result.error

    @pytest.mark.asyncio
    async def test_send_missing_workflow_url(self):
        adapter = TeamsAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.TEAMS)
        stream.channel_config = {}

        result = await adapter.send(event, stream)
        assert result.success is False
        assert "Missing workflow_url" in result.error


# --- Slack adapter tests ---


class TestSlackWebhookAdapter:
    def test_format_payload_structure(self):
        adapter = SlackWebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.SLACK_WEBHOOK)

        payload = adapter.format_payload(event, stream)

        assert "blocks" in payload
        blocks = payload["blocks"]
        # Header block — stream name without "Stream:" prefix
        assert blocks[0]["type"] == "header"
        assert blocks[0]["text"]["text"] == "Test Stream"
        # Last block is context footer
        assert blocks[-1]["type"] == "context"
        assert "Screen" in blocks[-1]["elements"][0]["text"]

    def test_format_payload_includes_summary(self):
        adapter = SlackWebhookAdapter()
        event = _make_event(summary="Slack summary")
        stream = _make_stream(ChannelType.SLACK_WEBHOOK)

        payload = adapter.format_payload(event, stream)
        blocks = payload["blocks"]
        texts = [b.get("text", {}).get("text", "") for b in blocks if b["type"] == "section" and "text" in b]
        assert "Slack summary" in texts

    def test_format_payload_includes_company_details(self):
        adapter = SlackWebhookAdapter()
        event = _make_event(payload={"company_name": "ChapsVision"})
        stream = _make_stream(ChannelType.SLACK_WEBHOOK)

        payload = adapter.format_payload(event, stream)
        blocks = payload["blocks"]
        field_blocks = [b for b in blocks if b["type"] == "section" and "fields" in b]
        assert len(field_blocks) == 1
        field_texts = [f["text"] for f in field_blocks[0]["fields"]]
        assert any("ChapsVision" in t for t in field_texts)

    def test_format_payload_includes_entity_link(self):
        adapter = SlackWebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.SLACK_WEBHOOK)

        payload = adapter.format_payload(event, stream)
        blocks = payload["blocks"]
        link_blocks = [
            b
            for b in blocks
            if b["type"] == "section" and "text" in b and "Voir la fiche" in b.get("text", {}).get("text", "")
        ]
        assert len(link_blocks) == 1
        assert "folders/folder-abc/companies/42" in link_blocks[0]["text"]["text"]

    def test_format_payload_no_link_without_entity(self):
        adapter = SlackWebhookAdapter()
        event = _make_event(entity_type=None, entity_id=None)
        stream = _make_stream(ChannelType.SLACK_WEBHOOK)

        payload = adapter.format_payload(event, stream)
        blocks = payload["blocks"]
        link_blocks = [
            b for b in blocks if b["type"] == "section" and "Voir la fiche" in b.get("text", {}).get("text", "")
        ]
        assert len(link_blocks) == 0

    @pytest.mark.asyncio
    async def test_send_success(self):
        adapter = SlackWebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.SLACK_WEBHOOK)

        mock_response = httpx.Response(200, text="ok")

        with patch("app.adapters.slack.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is True

    @pytest.mark.asyncio
    async def test_send_server_error(self):
        adapter = SlackWebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.SLACK_WEBHOOK)

        mock_response = httpx.Response(500, text="Internal Server Error")

        with patch("app.adapters.slack.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is False
        assert result.status_code == 500


# --- Webhook adapter tests ---


class TestWebhookAdapter:
    def test_format_payload_structure(self):
        adapter = WebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.WEBHOOK)

        payload = adapter.format_payload(event, stream)

        assert payload["stream_id"] == 1
        assert payload["stream_name"] == "Test Stream"
        assert payload["event_type"] == "screen.company.created"
        assert payload["source"] == "screen"
        assert payload["payload"] == {"company_id": 42, "company_name": "Acme Corp"}
        assert payload["entity_id"] == "42"
        assert payload["entity_type"] == "company"
        assert payload["summary"] == "Company Acme Corp was created"
        assert "timestamp" in payload

    def test_hmac_signature(self):
        adapter = WebhookAdapter()
        body = b'{"test": "data"}'
        secret = "my-secret-key"

        signature = adapter._sign_payload(body, secret)

        # Verify the signature format
        assert signature.startswith("sha256=")

        # Verify the signature is correct
        expected = hmac.new(
            secret.encode("utf-8"),
            body,
            hashlib.sha256,
        ).hexdigest()
        assert signature == f"sha256={expected}"

    @pytest.mark.asyncio
    async def test_send_success(self):
        adapter = WebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.WEBHOOK)

        mock_response = httpx.Response(200, text='{"ok": true}')

        with patch("app.adapters.webhook.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.request = AsyncMock(return_value=mock_response)
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is True
        assert result.status_code == 200

    @pytest.mark.asyncio
    async def test_send_with_hmac_signature(self):
        adapter = WebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.WEBHOOK, secret="test-secret")

        mock_response = httpx.Response(200, text="ok")
        captured_headers = {}

        async def capture_request(method, url, content, headers):
            captured_headers.update(headers)
            return mock_response

        with patch("app.adapters.webhook.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.request = AsyncMock(side_effect=capture_request)
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is True
        assert "X-Signature-256" in captured_headers
        assert captured_headers["X-Signature-256"].startswith("sha256=")

    @pytest.mark.asyncio
    async def test_send_with_custom_headers(self):
        adapter = WebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.WEBHOOK, headers={"X-Custom": "value"})

        mock_response = httpx.Response(200, text="ok")
        captured_headers = {}

        async def capture_request(method, url, content, headers):
            captured_headers.update(headers)
            return mock_response

        with patch("app.adapters.webhook.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.request = AsyncMock(side_effect=capture_request)
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is True
        assert captured_headers.get("X-Custom") == "value"

    @pytest.mark.asyncio
    async def test_send_timeout(self):
        adapter = WebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.WEBHOOK)

        with patch("app.adapters.webhook.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.request = AsyncMock(side_effect=httpx.TimeoutException("timeout"))
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is False
        assert "timed out" in result.error

    @pytest.mark.asyncio
    async def test_send_4xx_error(self):
        adapter = WebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.WEBHOOK)

        mock_response = httpx.Response(403, text="Forbidden")

        with patch("app.adapters.webhook.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.request = AsyncMock(return_value=mock_response)
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is False
        assert result.status_code == 403

    @pytest.mark.asyncio
    async def test_send_missing_url(self):
        adapter = WebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.WEBHOOK)
        stream.channel_config = {}

        result = await adapter.send(event, stream)
        assert result.success is False
        assert "Missing url" in result.error

    @pytest.mark.asyncio
    async def test_send_connection_error(self):
        adapter = WebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.WEBHOOK)

        with patch("app.adapters.webhook.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.request = AsyncMock(side_effect=httpx.ConnectError("Connection refused"))
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is False
        assert "Connection refused" in result.error

    @pytest.mark.asyncio
    async def test_send_rejects_disallowed_http_method(self):
        adapter = WebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.WEBHOOK, method="DELETE")

        result = await adapter.send(event, stream)
        assert result.success is False
        assert "not allowed" in result.error

    @pytest.mark.asyncio
    async def test_send_accepts_post_method(self):
        adapter = WebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.WEBHOOK, method="post")

        mock_response = httpx.Response(200, text="ok")

        with patch("app.adapters.webhook.httpx.AsyncClient") as mock_client_cls:
            mock_client = AsyncMock()
            mock_client.request = AsyncMock(return_value=mock_response)
            mock_client.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client.__aexit__ = AsyncMock(return_value=False)
            mock_client_cls.return_value = mock_client

            result = await adapter.send(event, stream)

        assert result.success is True

    @pytest.mark.asyncio
    async def test_send_none_channel_config(self):
        adapter = WebhookAdapter()
        event = _make_event()
        stream = _make_stream(ChannelType.WEBHOOK)
        stream.channel_config = None

        result = await adapter.send(event, stream)
        assert result.success is False
        assert "Missing url" in result.error
