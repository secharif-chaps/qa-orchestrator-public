"""Unit tests for DispatchService.

Covers:
- Dispatch success / failure / missing stream / retry logic
- Post-success token consumption (happy path, 402, 403, network error)
- No tokens consumed on dispatch failure
- Credits metadata stored in response_metadata
- Batch dispatchers (for_event, for_stream)
- Test connection
"""

from __future__ import annotations

from datetime import UTC, datetime, timedelta
from unittest.mock import AsyncMock, patch

import pytest

from app.adapters.base import DispatchResult
from app.models.delivery import DeliveryStatus, StreamDelivery
from app.models.event import StreamEvent
from app.models.stream import ChannelType, Stream, StreamMode, StreamStatus
from app.schemas.stream import StreamCreate
from app.services.dispatch_service import DispatchService
from app.services.event_service import EventService
from app.services.stream_service import StreamService
from app.services.token_client import TokenClient, TokenConsumeResult, TokenErrorCode

ORG_ID = "test-org-123"
USER_ID = "test-user-123"
USERNAME = "testuser"
FOLDER_ID = "folder-abc"


def _make_stream_create(**overrides) -> StreamCreate:
    defaults = {
        "name": "Test Stream",
        "channel_type": ChannelType.WEBHOOK,
        "channel_config": {"url": "https://example.com/hook", "headers": {}, "secret": None},
        "mode": StreamMode.LIVE,
        "subscribed_events": ["screen.company.created"],
    }
    defaults.update(overrides)
    return StreamCreate(**defaults)


def _create_stream_and_event(db_session) -> tuple[Stream, StreamEvent, StreamDelivery]:
    """Helper: create a stream, event, and pending delivery in the DB."""
    stream_svc = StreamService(db_session)
    event_svc = EventService(db_session)

    stream = stream_svc.create_stream(
        _make_stream_create(),
        FOLDER_ID,
        ORG_ID,
        USER_ID,
        USERNAME,
    )

    from app.schemas.event import EventIngest

    event = event_svc.ingest_event(
        EventIngest(
            event_type="screen.company.created",
            folder_id=FOLDER_ID,
            payload={"company_id": 42},
            summary="Test event",
        ),
        ORG_ID,
    )

    # Fetch the delivery that was auto-created
    delivery = (
        db_session.query(StreamDelivery)
        .filter(StreamDelivery.event_id == event.id, StreamDelivery.stream_id == stream.id)
        .first()
    )
    assert delivery is not None
    assert delivery.status == DeliveryStatus.PENDING

    return stream, event, delivery


def _mock_adapter(*, success: bool = True, **kwargs) -> AsyncMock:
    """Return a patched get_adapter that yields a mock adapter."""
    defaults: dict = {"success": success, "status_code": 200 if success else 500}
    if not success and "error" not in kwargs:
        defaults["error"] = "Adapter error"
    defaults.update(kwargs)

    mock = AsyncMock()
    mock.send = AsyncMock(return_value=DispatchResult(**defaults))
    return mock


@pytest.fixture
def dispatch_service(db_session):
    """DispatchService without token client (dev/test mode)."""
    return DispatchService(db=db_session)


@pytest.fixture
def token_client() -> AsyncMock:
    """A mock TokenClient with a default success response."""
    client = AsyncMock(spec=TokenClient)
    client.consume = AsyncMock(return_value=TokenConsumeResult(success=True, balance=95))
    return client


@pytest.fixture
def dispatch_service_with_tokens(db_session, token_client) -> DispatchService:
    """DispatchService wired with a mock token client."""
    return DispatchService(db=db_session, token_client=token_client)


# =====================================================================
# Dispatch delivery — basic behaviour (no token client)
# =====================================================================


class TestDispatchDelivery:
    @pytest.mark.asyncio
    async def test_dispatch_success(self, db_session, dispatch_service):
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True, response_body="ok")
            result = await dispatch_service.dispatch_delivery(delivery)

        assert result.status == DeliveryStatus.DELIVERED
        assert result.delivered_at is not None

    @pytest.mark.asyncio
    async def test_dispatch_failure(self, db_session, dispatch_service):
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=False, error="Internal Server Error")
            result = await dispatch_service.dispatch_delivery(delivery)

        assert result.status == DeliveryStatus.FAILED
        assert result.error_message == "Internal Server Error"
        assert result.attempt_count == 1
        assert result.next_retry_at is not None

    @pytest.mark.asyncio
    async def test_dispatch_missing_stream(self, db_session, dispatch_service):
        """Delivery referencing a deleted stream -> FAILED."""
        delivery = StreamDelivery(
            stream_id=999,
            event_id=999,
            status=DeliveryStatus.PENDING,
        )
        db_session.add(delivery)
        db_session.commit()

        result = await dispatch_service.dispatch_delivery(delivery)

        assert result.status == DeliveryStatus.FAILED
        assert "not found" in result.error_message

    @pytest.mark.asyncio
    async def test_dispatch_increments_attempt_count(self, db_session, dispatch_service):
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=False, error="Timeout")

            # First failure
            result = await dispatch_service.dispatch_delivery(delivery)
            assert result.attempt_count == 1

            # Reset to pending for second attempt
            delivery.status = DeliveryStatus.PENDING
            db_session.commit()

            result = await dispatch_service.dispatch_delivery(delivery)
            assert result.attempt_count == 2


# =====================================================================
# Token consumption — post-success behaviour
# =====================================================================


class TestDispatchWithTokens:
    """Tests for the token consumption flow integrated into dispatch."""

    @pytest.mark.asyncio
    async def test_tokens_consumed_on_success(self, db_session, dispatch_service_with_tokens, token_client):
        """On successful dispatch, tokens are consumed and metadata recorded."""
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True, response_body="ok")
            result = await dispatch_service_with_tokens.dispatch_delivery(delivery)

        assert result.status == DeliveryStatus.DELIVERED
        token_client.consume.assert_awaited_once()

        # Verify credits metadata stored
        meta = result.response_metadata
        assert meta["credits_consumed"] == 2  # WEBHOOK default cost
        assert meta["credits_balance"] == 95

    @pytest.mark.asyncio
    async def test_no_tokens_consumed_on_failure(self, db_session, dispatch_service_with_tokens, token_client):
        """On failed dispatch, tokens are NOT consumed."""
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=False, error="Connection refused")
            result = await dispatch_service_with_tokens.dispatch_delivery(delivery)

        assert result.status == DeliveryStatus.FAILED
        token_client.consume.assert_not_awaited()

    @pytest.mark.asyncio
    async def test_token_402_after_success_logged_but_delivered(self, db_session, token_client):
        """If token API returns 402 after successful send, delivery stays DELIVERED."""
        token_client.consume = AsyncMock(
            return_value=TokenConsumeResult(
                success=False,
                balance=0,
                error="Insufficient tokens",
                error_code=TokenErrorCode.INSUFFICIENT_TOKENS,
            )
        )
        svc = DispatchService(db=db_session, token_client=token_client)

        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)
            result = await svc.dispatch_delivery(delivery)

        # Message was sent — still DELIVERED
        assert result.status == DeliveryStatus.DELIVERED
        assert result.response_metadata["credits_consumed"] == 0

    @pytest.mark.asyncio
    async def test_token_403_after_success_logged_but_delivered(self, db_session, token_client):
        """If module is disabled, delivery stays DELIVERED (message was sent)."""
        token_client.consume = AsyncMock(
            return_value=TokenConsumeResult(
                success=False,
                error="Module not enabled",
                error_code=TokenErrorCode.MODULE_NOT_ENABLED,
            )
        )
        svc = DispatchService(db=db_session, token_client=token_client)

        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)
            result = await svc.dispatch_delivery(delivery)

        assert result.status == DeliveryStatus.DELIVERED
        assert result.response_metadata["credits_consumed"] == 0

    @pytest.mark.asyncio
    async def test_token_network_error_after_success(self, db_session, token_client):
        """Transient token API failure: delivery still DELIVERED, credits=0."""
        token_client.consume = AsyncMock(
            return_value=TokenConsumeResult(
                success=False,
                error="Token API network error: Connection refused",
                error_code=TokenErrorCode.NETWORK_ERROR,
            )
        )
        svc = DispatchService(db=db_session, token_client=token_client)

        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)
            result = await svc.dispatch_delivery(delivery)

        assert result.status == DeliveryStatus.DELIVERED
        assert result.response_metadata["credits_consumed"] == 0

    @pytest.mark.asyncio
    async def test_no_token_client_skips_consumption(self, db_session):
        """Without a token client (dev mode), dispatch works normally."""
        svc = DispatchService(db=db_session, token_client=None)
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)
            result = await svc.dispatch_delivery(delivery)

        assert result.status == DeliveryStatus.DELIVERED
        # No credits metadata when token client is None
        assert "credits_consumed" not in (result.response_metadata or {})

    @pytest.mark.asyncio
    async def test_correct_cost_per_channel_type(self, db_session, token_client):
        """Teams streams should consume 5 credits, not 2."""
        svc = DispatchService(db=db_session, token_client=token_client)

        stream_svc = StreamService(db_session)
        event_svc = EventService(db_session)

        stream = stream_svc.create_stream(
            _make_stream_create(
                channel_type=ChannelType.TEAMS,
                channel_config={"workflow_url": "https://teams.example.com/hook"},
            ),
            FOLDER_ID,
            ORG_ID,
            USER_ID,
            USERNAME,
        )

        from app.schemas.event import EventIngest

        event = event_svc.ingest_event(
            EventIngest(
                event_type="screen.company.created",
                folder_id=FOLDER_ID,
                payload={"company_id": 99},
                summary="Teams event",
            ),
            ORG_ID,
        )

        delivery = (
            db_session.query(StreamDelivery)
            .filter(StreamDelivery.event_id == event.id, StreamDelivery.stream_id == stream.id)
            .first()
        )
        assert delivery is not None

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)
            result = await svc.dispatch_delivery(delivery)

        # Verify consume was called with amount=5 (Teams cost)
        call_kwargs = token_client.consume.call_args.kwargs
        assert call_kwargs["amount"] == 5

        assert result.response_metadata["credits_consumed"] == 5

    @pytest.mark.asyncio
    async def test_consume_receives_correct_org_and_user(self, db_session, token_client):
        """Token client receives the stream owner's org_id, user_id, and username."""
        svc = DispatchService(db=db_session, token_client=token_client)
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)
            await svc.dispatch_delivery(delivery)

        call_kwargs = token_client.consume.call_args.kwargs
        assert call_kwargs["org_id"] == ORG_ID
        assert call_kwargs["user_id"] == USER_ID
        assert call_kwargs["username"] == USERNAME
        assert call_kwargs["reference_id"] == str(delivery.id)


# =====================================================================
# Batch dispatch — for_event
# =====================================================================


class TestDispatchPendingForEvent:
    @pytest.mark.asyncio
    async def test_dispatch_live_mode_deliveries(self, db_session, dispatch_service):
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)

            count = await dispatch_service.dispatch_pending_for_event(
                event_id=event.id,
                org_id=ORG_ID,
            )

        assert count == 1

    @pytest.mark.asyncio
    async def test_skip_paused_streams(self, db_session, dispatch_service):
        """Paused streams should NOT have their deliveries dispatched."""
        stream_svc = StreamService(db_session)
        stream, event, delivery = _create_stream_and_event(db_session)

        # Pause the stream
        stream_svc.update_status(stream.id, StreamStatus.PAUSED, ORG_ID)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)

            count = await dispatch_service.dispatch_pending_for_event(
                event_id=event.id,
                org_id=ORG_ID,
            )

        assert count == 0

    @pytest.mark.asyncio
    async def test_skip_recurrence_mode_streams(self, db_session):
        """Recurrence-mode streams should NOT be dispatched by event trigger."""
        stream_svc = StreamService(db_session)
        event_svc = EventService(db_session)

        stream_svc.create_stream(
            _make_stream_create(mode=StreamMode.RECURRENCE, cron_expression="0 9 * * *"),
            FOLDER_ID,
            ORG_ID,
            USER_ID,
            USERNAME,
        )

        from app.schemas.event import EventIngest

        event = event_svc.ingest_event(
            EventIngest(event_type="screen.company.created", folder_id=FOLDER_ID, payload={}),
            ORG_ID,
        )

        dispatch_svc = DispatchService(db=db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)

            count = await dispatch_svc.dispatch_pending_for_event(
                event_id=event.id,
                org_id=ORG_ID,
            )

        assert count == 0

    @pytest.mark.asyncio
    async def test_no_pending_deliveries(self, db_session, dispatch_service):
        count = await dispatch_service.dispatch_pending_for_event(
            event_id=999,
            org_id=ORG_ID,
        )
        assert count == 0


# =====================================================================
# Batch dispatch — for_stream
# =====================================================================


class TestDispatchPendingForStream:
    @pytest.mark.asyncio
    async def test_dispatch_manual(self, db_session, dispatch_service):
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)

            dispatched, failed = await dispatch_service.dispatch_pending_for_stream(
                stream_id=stream.id,
                org_id=ORG_ID,
            )

        assert dispatched == 1
        assert failed == 0

    @pytest.mark.asyncio
    async def test_dispatch_manual_with_failures(self, db_session, dispatch_service):
        """Mix of success and failure deliveries."""
        stream_svc = StreamService(db_session)
        event_svc = EventService(db_session)

        stream = stream_svc.create_stream(
            _make_stream_create(subscribed_events=["screen.company.created", "screen.company.updated"]),
            FOLDER_ID,
            ORG_ID,
            USER_ID,
            USERNAME,
        )

        from app.schemas.event import EventIngest

        event_svc.ingest_event(
            EventIngest(event_type="screen.company.created", folder_id=FOLDER_ID, payload={}),
            ORG_ID,
        )
        event_svc.ingest_event(
            EventIngest(event_type="screen.company.updated", folder_id=FOLDER_ID, payload={}),
            ORG_ID,
        )

        call_count = 0

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:

            async def alternating_send(*args, **kwargs):
                nonlocal call_count
                call_count += 1
                if call_count == 1:
                    return DispatchResult(success=True, status_code=200)
                return DispatchResult(success=False, error="Failure")

            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(side_effect=alternating_send)
            mock_get_adapter.return_value = mock_adapter

            dispatched, failed = await dispatch_service.dispatch_pending_for_stream(
                stream_id=stream.id,
                org_id=ORG_ID,
            )

        assert dispatched == 1
        assert failed == 1

    @pytest.mark.asyncio
    async def test_dispatch_nonexistent_stream(self, db_session, dispatch_service):
        dispatched, failed = await dispatch_service.dispatch_pending_for_stream(
            stream_id=999,
            org_id=ORG_ID,
        )
        assert dispatched == 0
        assert failed == 0

    @pytest.mark.asyncio
    async def test_dispatch_skips_deliveries_in_retry_cooldown(self, db_session, dispatch_service):
        """Deliveries with next_retry_at in the future should not be dispatched."""
        stream, event, delivery = _create_stream_and_event(db_session)

        # Simulate a failed delivery with retry scheduled in the future
        delivery.status = DeliveryStatus.PENDING
        delivery.attempt_count = 1
        delivery.next_retry_at = datetime.now(UTC) + timedelta(hours=1)
        db_session.commit()

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)

            dispatched, failed = await dispatch_service.dispatch_pending_for_stream(
                stream_id=stream.id,
                org_id=ORG_ID,
            )

        assert dispatched == 0
        assert failed == 0

    @pytest.mark.asyncio
    async def test_dispatch_includes_deliveries_past_retry_cooldown(self, db_session, dispatch_service):
        """Deliveries with next_retry_at in the past should be dispatched."""
        stream, event, delivery = _create_stream_and_event(db_session)

        # Simulate a retry that's past its cooldown
        delivery.status = DeliveryStatus.PENDING
        delivery.attempt_count = 1
        delivery.next_retry_at = datetime.now(UTC) - timedelta(minutes=1)
        db_session.commit()

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)

            dispatched, failed = await dispatch_service.dispatch_pending_for_stream(
                stream_id=stream.id,
                org_id=ORG_ID,
            )

        assert dispatched == 1


# =====================================================================
# Test connection (no tokens)
# =====================================================================


class TestTestConnection:
    @pytest.mark.asyncio
    async def test_connection_success(self, db_session, dispatch_service):
        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)

            result = await dispatch_service.test_connection(
                channel_type=ChannelType.WEBHOOK,
                channel_config={"url": "https://example.com/hook", "headers": {}, "secret": None},
            )

        assert result.success is True

    @pytest.mark.asyncio
    async def test_connection_failure(self, db_session, dispatch_service):
        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=False, error="Connection refused")

            result = await dispatch_service.test_connection(
                channel_type=ChannelType.TEAMS,
                channel_config={"workflow_url": "https://teams.example.com/hook"},
            )

        assert result.success is False
        assert result.error == "Connection refused"

    @pytest.mark.asyncio
    async def test_connection_unknown_channel_type(self, db_session, dispatch_service):
        """Unknown channel_type should return a failure, not raise ValueError."""
        result = await dispatch_service.test_connection(
            channel_type="nonexistent",  # type: ignore[arg-type]
            channel_config={"url": "https://example.com"},
        )

        assert result.success is False
        assert "No adapter registered" in result.error

    @pytest.mark.asyncio
    async def test_connection_does_not_consume_tokens(self, db_session, dispatch_service_with_tokens, token_client):
        """Test connections are free (0 credits per ADR-0020)."""
        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_get_adapter.return_value = _mock_adapter(success=True)

            await dispatch_service_with_tokens.test_connection(
                channel_type=ChannelType.WEBHOOK,
                channel_config={"url": "https://example.com/hook"},
            )

        token_client.consume.assert_not_awaited()
