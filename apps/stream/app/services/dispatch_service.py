"""Dispatch service — processes pending deliveries through channel adapters."""

from datetime import UTC, datetime, timedelta

from sqlalchemy import or_
from sqlalchemy.orm import Session

from app.adapters.base import DispatchResult
from app.adapters.factory import get_adapter
from app.core.logging_config import get_logger
from app.models.delivery import DeliveryStatus, StreamDelivery
from app.models.event import StreamEvent
from app.models.stream import ChannelType, Stream, StreamMode, StreamStatus

logger = get_logger(__name__)

# Exponential backoff for retries: base * 2^attempt_count (in minutes)
RETRY_BASE_MINUTES = 5
RETRY_MAX_MINUTES = 1440  # 24 hours


def _next_retry_at(attempt_count: int) -> datetime:
    """Calculate next retry time with exponential backoff."""
    delay_minutes = min(RETRY_BASE_MINUTES * (2**attempt_count), RETRY_MAX_MINUTES)
    return datetime.now(UTC) + timedelta(minutes=delay_minutes)


class DispatchService:
    """Processes pending deliveries by resolving adapters and sending payloads.

    Handles retry scheduling and status transitions.
    """

    def __init__(self, db: Session):
        self.db = db

    async def dispatch_delivery(
        self,
        delivery: StreamDelivery,
    ) -> StreamDelivery:
        """Dispatch a single pending delivery.

        Flow:
        1. Load stream + event
        2. Resolve adapter from stream.channel_type
        3. adapter.send(event, stream)
        4. On success: update status=DELIVERED, set delivered_at
        5. On failure: status=FAILED, increment attempt_count, set next_retry_at

        Args:
            delivery: The pending StreamDelivery record

        Returns:
            Updated StreamDelivery record
        """
        stream: Stream | None = self.db.query(Stream).filter(Stream.id == delivery.stream_id).first()
        event: StreamEvent | None = self.db.query(StreamEvent).filter(StreamEvent.id == delivery.event_id).first()

        if not stream or not event:
            delivery.status = DeliveryStatus.FAILED  # type: ignore[assignment]
            delivery.error_message = "Stream or event not found"  # type: ignore[assignment]
            self.db.commit()
            return delivery

        # Resolve adapter and send
        try:
            adapter = get_adapter(ChannelType(str(stream.channel_type)))
        except ValueError as e:
            delivery.status = DeliveryStatus.FAILED  # type: ignore[assignment]
            delivery.error_message = str(e)  # type: ignore[assignment]
            self.db.commit()
            return delivery

        result: DispatchResult = await adapter.send(event, stream)

        if result.success:
            delivery.status = DeliveryStatus.DELIVERED  # type: ignore[assignment]
            delivery.delivered_at = datetime.now(UTC)  # type: ignore[assignment]
            delivery.response_metadata = {  # type: ignore[assignment]
                "status_code": result.status_code,
                "response_body": result.response_body,
            }
            logger.info(
                "Delivery dispatched successfully",
                extra={"delivery_id": delivery.id, "stream_id": stream.id, "event_id": event.id},
            )
        else:
            delivery.status = DeliveryStatus.FAILED  # type: ignore[assignment]
            delivery.error_message = result.error  # type: ignore[assignment]
            delivery.attempt_count = int(delivery.attempt_count or 0) + 1  # type: ignore[assignment]
            delivery.next_retry_at = _next_retry_at(int(delivery.attempt_count))  # type: ignore[assignment]
            delivery.response_metadata = {  # type: ignore[assignment]
                "status_code": result.status_code,
                "response_body": result.response_body,
            }
            logger.warning(
                "Delivery dispatch failed",
                extra={
                    "delivery_id": delivery.id,
                    "stream_id": stream.id,
                    "error": result.error,
                    "attempt": delivery.attempt_count,
                },
            )

        self.db.commit()
        return delivery

    async def dispatch_pending_for_event(
        self,
        event_id: int,
        org_id: str,
    ) -> int:
        """Dispatch all pending deliveries for a given event (live mode trigger).

        Only dispatches for streams that are ACTIVE and in LIVE mode.

        Args:
            event_id: The event ID to dispatch deliveries for
            org_id: Organization ID (for scoping)

        Returns:
            Number of deliveries dispatched (success + failure)
        """
        deliveries = (
            self.db.query(StreamDelivery)
            .join(Stream, StreamDelivery.stream_id == Stream.id)
            .filter(
                StreamDelivery.event_id == event_id,
                StreamDelivery.status == DeliveryStatus.PENDING,
                Stream.organization_id == org_id,
                Stream.status == StreamStatus.ACTIVE,
                Stream.mode == StreamMode.LIVE,
            )
            .all()
        )

        count = 0
        for delivery in deliveries:
            await self.dispatch_delivery(delivery)
            count += 1

        logger.info(
            "Dispatched pending deliveries for event",
            extra={"event_id": event_id, "org_id": org_id, "count": count},
        )
        return count

    async def dispatch_pending_for_stream(
        self,
        stream_id: int,
        org_id: str,
    ) -> tuple[int, int]:
        """Dispatch all pending deliveries for a stream (manual trigger).

        Args:
            stream_id: The stream ID
            org_id: Organization ID (for scoping)

        Returns:
            Tuple of (dispatched_count, failed_count)
        """
        # Verify stream exists and belongs to org
        stream = (
            self.db.query(Stream)
            .filter(Stream.id == stream_id, Stream.organization_id == org_id)
            .first()
        )
        if not stream:
            return 0, 0

        now = datetime.now(UTC)
        deliveries = (
            self.db.query(StreamDelivery)
            .filter(
                StreamDelivery.stream_id == stream_id,
                StreamDelivery.status == DeliveryStatus.PENDING,
                or_(
                    StreamDelivery.next_retry_at.is_(None),
                    StreamDelivery.next_retry_at <= now,
                ),
            )
            .all()
        )

        dispatched = 0
        failed = 0
        for delivery in deliveries:
            result = await self.dispatch_delivery(delivery)
            if result.status == DeliveryStatus.DELIVERED:
                dispatched += 1
            else:
                failed += 1

        logger.info(
            "Dispatched pending deliveries for stream",
            extra={
                "stream_id": stream_id,
                "org_id": org_id,
                "dispatched": dispatched,
                "failed": failed,
            },
        )
        return dispatched, failed

    async def test_connection(
        self,
        channel_type: ChannelType,
        channel_config: dict,
    ) -> DispatchResult:
        """Send a test message to validate channel configuration.

        Creates a mock event and stream to test the adapter.

        Args:
            channel_type: The channel type to test
            channel_config: The channel configuration to validate

        Returns:
            DispatchResult from the test send
        """
        try:
            adapter = get_adapter(channel_type)
        except ValueError as e:
            return DispatchResult(success=False, error=str(e))

        # Create mock objects for the test
        mock_event = StreamEvent(
            id=0,
            event_type="stream.test.connection",
            source="stream",
            payload={"test": True, "message": "This is a test event from ChapsMind Stream"},
            organization_id="test",
            summary="Test connection from ChapsMind Stream service",
            entity_type="test",
            entity_id="0",
        )

        mock_stream = Stream(
            id=0,
            name="Test Connection",
            channel_type=channel_type,
            channel_config=channel_config,
            mode=StreamMode.LIVE,
            status=StreamStatus.ACTIVE,
            folder_id="test",
            organization_id="test",
            owner_id="test",
            subscribed_events=[],
        )

        result = await adapter.send(mock_event, mock_stream)

        logger.info(
            "Test connection result",
            extra={"channel_type": channel_type, "success": result.success, "error": result.error},
        )
        return result
