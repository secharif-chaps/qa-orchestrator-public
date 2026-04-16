"""Tests for OutboxRelay: event relay to Stream service.

Covers: relay cycle, HTTP posting, error handling, start/stop lifecycle.
"""

from unittest.mock import AsyncMock, MagicMock, patch

import httpx
import pytest

from app.models.outbox import OutboxEvent
from app.services.outbox_relay import OutboxRelay


def _make_outbox_event(
    event_id: int = 1,
    event_type: str = "screen.company.created",
    aggregate_type: str = "company",
    aggregate_id: str = "42",
    organization_id: str = "org-uuid-123",
    folder_id: str | None = None,
    payload: dict | None = None,
    summary: str | None = "Test event",
) -> MagicMock:
    """Create a mock OutboxEvent."""
    event = MagicMock(spec=OutboxEvent)
    event.id = event_id
    event.event_type = event_type
    event.aggregate_type = aggregate_type
    event.aggregate_id = aggregate_id
    event.organization_id = organization_id
    event.folder_id = folder_id
    event.payload = payload or {"company_id": 42}
    event.summary = summary
    return event


# ---------------------------------------------------------------------------
# _relay_event
# ---------------------------------------------------------------------------


class TestRelayEvent:
    @pytest.mark.asyncio
    async def test_successful_relay_returns_true(self):
        relay = OutboxRelay()
        event = _make_outbox_event()
        mock_response = MagicMock()
        mock_response.status_code = 200

        mock_client = AsyncMock(spec=httpx.AsyncClient)
        mock_client.post = AsyncMock(return_value=mock_response)

        with patch("app.services.outbox_relay.create_internal_token", return_value="fake-jwt"):
            result = await relay._relay_event(mock_client, event)

        assert result is True
        mock_client.post.assert_called_once()

    @pytest.mark.asyncio
    async def test_relay_sends_correct_payload(self):
        relay = OutboxRelay()
        event = _make_outbox_event(
            event_type="screen.company.deleted",
            aggregate_id="99",
            aggregate_type="company",
            folder_id="folder-abc",
            payload={"company_id": 99, "company_name": "Acme"},
            summary="Acme deleted",
        )
        mock_response = MagicMock()
        mock_response.status_code = 200

        mock_client = AsyncMock(spec=httpx.AsyncClient)
        mock_client.post = AsyncMock(return_value=mock_response)

        with patch("app.services.outbox_relay.create_internal_token", return_value="fake-jwt"):
            await relay._relay_event(mock_client, event)

        call_args = mock_client.post.call_args
        json_payload = call_args.kwargs["json"]
        assert json_payload["event_type"] == "screen.company.deleted"
        assert json_payload["entity_id"] == "99"
        assert json_payload["entity_type"] == "company"
        assert json_payload["folder_id"] == "folder-abc"
        assert json_payload["payload"] == {"company_id": 99, "company_name": "Acme"}
        assert json_payload["summary"] == "Acme deleted"

    @pytest.mark.asyncio
    async def test_relay_uses_internal_jwt(self):
        relay = OutboxRelay()
        event = _make_outbox_event(organization_id="org-test-456")
        mock_response = MagicMock()
        mock_response.status_code = 200

        mock_client = AsyncMock(spec=httpx.AsyncClient)
        mock_client.post = AsyncMock(return_value=mock_response)

        with patch("app.services.outbox_relay.create_internal_token", return_value="test-token") as mock_jwt:
            await relay._relay_event(mock_client, event)

        mock_jwt.assert_called_once_with(
            user_id="system",
            username="screen-outbox-relay",
            org_id="org-test-456",
        )
        call_args = mock_client.post.call_args
        headers = call_args.kwargs["headers"]
        assert headers["Authorization"] == "Internal test-token"

    @pytest.mark.asyncio
    async def test_http_error_returns_false(self):
        relay = OutboxRelay()
        event = _make_outbox_event()
        mock_response = MagicMock()
        mock_response.status_code = 500
        mock_response.text = "Internal Server Error"

        mock_client = AsyncMock(spec=httpx.AsyncClient)
        mock_client.post = AsyncMock(return_value=mock_response)

        with patch("app.services.outbox_relay.create_internal_token", return_value="fake-jwt"):
            result = await relay._relay_event(mock_client, event)

        assert result is False

    @pytest.mark.asyncio
    async def test_network_error_returns_false(self):
        relay = OutboxRelay()
        event = _make_outbox_event()

        mock_client = AsyncMock(spec=httpx.AsyncClient)
        mock_client.post = AsyncMock(side_effect=httpx.ConnectError("Connection refused"))

        with patch("app.services.outbox_relay.create_internal_token", return_value="fake-jwt"):
            result = await relay._relay_event(mock_client, event)

        assert result is False

    @pytest.mark.asyncio
    async def test_folder_id_none_sends_empty_string(self):
        relay = OutboxRelay()
        event = _make_outbox_event(folder_id=None)
        mock_response = MagicMock()
        mock_response.status_code = 200

        mock_client = AsyncMock(spec=httpx.AsyncClient)
        mock_client.post = AsyncMock(return_value=mock_response)

        with patch("app.services.outbox_relay.create_internal_token", return_value="fake-jwt"):
            await relay._relay_event(mock_client, event)

        call_args = mock_client.post.call_args
        assert call_args.kwargs["json"]["folder_id"] == ""


# ---------------------------------------------------------------------------
# _relay_cycle
# ---------------------------------------------------------------------------


class TestRelayCycle:
    @pytest.mark.asyncio
    async def test_no_pending_events_does_nothing(self):
        relay = OutboxRelay()

        mock_session = MagicMock()
        mock_outbox_service = MagicMock()
        mock_outbox_service.get_pending.return_value = []

        with (
            patch("app.services.outbox_relay.SessionLocal", return_value=mock_session),
            patch("app.services.outbox_relay.OutboxService", return_value=mock_outbox_service),
        ):
            await relay._relay_cycle()

        mock_outbox_service.mark_published.assert_not_called()
        mock_session.close.assert_called_once()

    @pytest.mark.asyncio
    async def test_relays_pending_events_and_marks_published(self):
        relay = OutboxRelay()
        events = [_make_outbox_event(event_id=1), _make_outbox_event(event_id=2)]

        mock_session = MagicMock()
        mock_outbox_service = MagicMock()
        mock_outbox_service.get_pending.return_value = events

        mock_response = MagicMock()
        mock_response.status_code = 200

        with (
            patch("app.services.outbox_relay.SessionLocal", return_value=mock_session),
            patch("app.services.outbox_relay.OutboxService", return_value=mock_outbox_service),
            patch("app.services.outbox_relay.create_internal_token", return_value="jwt"),
            patch("httpx.AsyncClient") as mock_client_cls,
        ):
            mock_client = AsyncMock()
            mock_client.post = AsyncMock(return_value=mock_response)
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await relay._relay_cycle()

        mock_outbox_service.mark_published.assert_called_once_with([1, 2])

    @pytest.mark.asyncio
    async def test_partial_failure_only_marks_successful(self):
        relay = OutboxRelay()
        events = [_make_outbox_event(event_id=1), _make_outbox_event(event_id=2)]

        mock_session = MagicMock()
        mock_outbox_service = MagicMock()
        mock_outbox_service.get_pending.return_value = events

        success_response = MagicMock()
        success_response.status_code = 200
        fail_response = MagicMock()
        fail_response.status_code = 500
        fail_response.text = "Error"

        with (
            patch("app.services.outbox_relay.SessionLocal", return_value=mock_session),
            patch("app.services.outbox_relay.OutboxService", return_value=mock_outbox_service),
            patch("app.services.outbox_relay.create_internal_token", return_value="jwt"),
            patch("httpx.AsyncClient") as mock_client_cls,
        ):
            mock_client = AsyncMock()
            mock_client.post = AsyncMock(side_effect=[success_response, fail_response])
            mock_client_cls.return_value.__aenter__ = AsyncMock(return_value=mock_client)
            mock_client_cls.return_value.__aexit__ = AsyncMock(return_value=False)

            await relay._relay_cycle()

        # Only event 1 succeeded
        mock_outbox_service.mark_published.assert_called_once_with([1])

    @pytest.mark.asyncio
    async def test_session_closed_on_error(self):
        relay = OutboxRelay()

        mock_session = MagicMock()
        mock_outbox_service = MagicMock()
        mock_outbox_service.get_pending.side_effect = Exception("DB error")

        with (
            patch("app.services.outbox_relay.SessionLocal", return_value=mock_session),
            patch("app.services.outbox_relay.OutboxService", return_value=mock_outbox_service),
            pytest.raises(Exception, match="DB error"),
        ):
            await relay._relay_cycle()

        mock_session.close.assert_called_once()


# ---------------------------------------------------------------------------
# start / stop lifecycle
# ---------------------------------------------------------------------------


class TestLifecycle:
    @pytest.mark.asyncio
    async def test_start_creates_task(self):
        relay = OutboxRelay()
        assert relay._task is None

        with patch.object(relay, "_poll_loop", new_callable=AsyncMock):
            await relay.start()
            assert relay._task is not None
            await relay.stop()

    @pytest.mark.asyncio
    async def test_stop_cancels_task(self):
        relay = OutboxRelay()

        with patch.object(relay, "_poll_loop", new_callable=AsyncMock):
            await relay.start()
            task = relay._task
            await relay.stop()
            assert relay._task is None
            assert task.cancelled() or task.done()

    @pytest.mark.asyncio
    async def test_stop_without_start_is_noop(self):
        relay = OutboxRelay()
        await relay.stop()  # Should not raise
        assert relay._task is None
