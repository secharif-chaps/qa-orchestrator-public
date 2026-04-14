"""Unit tests for DispatchService."""

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


@pytest.fixture
def dispatch_service(db_session):
    """Create a DispatchService."""
    return DispatchService(db=db_session)


class TestDispatchDelivery:
    @pytest.mark.asyncio
    async def test_dispatch_success(self, db_session, dispatch_service):
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(
                return_value=DispatchResult(success=True, status_code=200, response_body="ok")
            )
            mock_get_adapter.return_value = mock_adapter

            result = await dispatch_service.dispatch_delivery(delivery)

        assert result.status == DeliveryStatus.DELIVERED
        assert result.delivered_at is not None

    @pytest.mark.asyncio
    async def test_dispatch_failure(self, db_session, dispatch_service):
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(
                return_value=DispatchResult(success=False, status_code=500, error="Internal Server Error")
            )
            mock_get_adapter.return_value = mock_adapter

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
            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(
                return_value=DispatchResult(success=False, error="Timeout")
            )
            mock_get_adapter.return_value = mock_adapter

            # First failure
            result = await dispatch_service.dispatch_delivery(delivery)
            assert result.attempt_count == 1

            # Reset to pending for second attempt
            delivery.status = DeliveryStatus.PENDING
            db_session.commit()

            result = await dispatch_service.dispatch_delivery(delivery)
            assert result.attempt_count == 2


class TestDispatchPendingForEvent:
    @pytest.mark.asyncio
    async def test_dispatch_live_mode_deliveries(self, db_session, dispatch_service):
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(
                return_value=DispatchResult(success=True, status_code=200)
            )
            mock_get_adapter.return_value = mock_adapter

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
            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(return_value=DispatchResult(success=True, status_code=200))
            mock_get_adapter.return_value = mock_adapter

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

        stream = stream_svc.create_stream(
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
            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(return_value=DispatchResult(success=True, status_code=200))
            mock_get_adapter.return_value = mock_adapter

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


class TestDispatchPendingForStream:
    @pytest.mark.asyncio
    async def test_dispatch_manual(self, db_session, dispatch_service):
        stream, event, delivery = _create_stream_and_event(db_session)

        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(
                return_value=DispatchResult(success=True, status_code=200)
            )
            mock_get_adapter.return_value = mock_adapter

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
            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(
                return_value=DispatchResult(success=True, status_code=200)
            )
            mock_get_adapter.return_value = mock_adapter

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
            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(
                return_value=DispatchResult(success=True, status_code=200)
            )
            mock_get_adapter.return_value = mock_adapter

            dispatched, failed = await dispatch_service.dispatch_pending_for_stream(
                stream_id=stream.id,
                org_id=ORG_ID,
            )

        assert dispatched == 1


class TestTestConnection:
    @pytest.mark.asyncio
    async def test_connection_success(self, db_session, dispatch_service):
        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(
                return_value=DispatchResult(success=True, status_code=200)
            )
            mock_get_adapter.return_value = mock_adapter

            result = await dispatch_service.test_connection(
                channel_type=ChannelType.WEBHOOK,
                channel_config={"url": "https://example.com/hook", "headers": {}, "secret": None},
            )

        assert result.success is True

    @pytest.mark.asyncio
    async def test_connection_failure(self, db_session, dispatch_service):
        with patch("app.services.dispatch_service.get_adapter") as mock_get_adapter:
            mock_adapter = AsyncMock()
            mock_adapter.send = AsyncMock(
                return_value=DispatchResult(success=False, error="Connection refused")
            )
            mock_get_adapter.return_value = mock_adapter

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
