"""Dispatch service — processes pending deliveries through channel adapters.

After a successful send, consumes credits from the organization's token
balance via the Global Service (ADR-0016 credit system).  Credits are
**only** debited on success so that failed deliveries never cost tokens.

If the organisation has insufficient credits the delivery is marked SKIPPED
on the *next* dispatch attempt (post-success consumption returning 402).

A proper pre-flight balance gate requires a read-only check or lock/release
endpoint on the Global Service that does not yet exist — tracked as a
follow-up improvement.
"""

from __future__ import annotations

from datetime import UTC, datetime, timedelta

from sqlalchemy import or_
from sqlalchemy.orm import Session

from app.adapters.base import DispatchResult
from app.adapters.factory import get_adapter
from app.constants.messages import (
    TEST_CONNECTION_PAYLOAD_MESSAGE,
    TEST_CONNECTION_STREAM_NAME,
    TEST_CONNECTION_SUMMARY,
)
from app.core.logging_config import get_logger
from app.models.delivery import DeliveryStatus, StreamDelivery
from app.models.event import StreamEvent
from app.models.stream import ChannelType, Stream, StreamMode, StreamStatus
from app.services.token_client import (
    TokenClient,
    TokenConsumeResult,
    TokenErrorCode,
    get_credit_cost,
)

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

    Handles token consumption, retry scheduling, and status transitions.
    """

    def __init__(self, db: Session, token_client: TokenClient | None = None) -> None:
        self.db = db
        self._token_client = token_client

    # ------------------------------------------------------------------
    # Token consumption (post-success)
    # ------------------------------------------------------------------

    async def _consume_tokens_after_success(
        self,
        stream: Stream,
        delivery: StreamDelivery,
    ) -> TokenConsumeResult | None:
        """Consume credits for a delivery that was already sent successfully.

        Returns ``None`` when no token client is configured (dev / test).
        """
        if self._token_client is None:
            return None

        channel_type = ChannelType(str(stream.channel_type))
        cost = get_credit_cost(channel_type)

        return await self._token_client.consume(
            org_id=str(stream.organization_id),
            amount=cost,
            reference_id=str(delivery.id),
            user_id=str(stream.owner_id),
            username=str(stream.owner_username or "unknown"),
        )

    # ------------------------------------------------------------------
    # Dispatch
    # ------------------------------------------------------------------

    async def dispatch_delivery(
        self,
        delivery: StreamDelivery,
    ) -> StreamDelivery:
        """Dispatch a single pending delivery.

        Flow:
        1. Load stream + event
        2. Resolve adapter from stream.channel_type
        3. adapter.send(event, stream)
        4. On success  → consume tokens, mark DELIVERED
        5. On failure  → mark FAILED with retry (no tokens consumed)

        Token consumption happens **after** a successful send so that
        failed deliveries are never charged.  If the organisation runs
        out of credits between deliveries the consumption call returns
        402 and we log a warning (the message was already sent).
        """
        stream: Stream | None = self.db.query(Stream).filter(Stream.id == delivery.stream_id).first()
        event: StreamEvent | None = self.db.query(StreamEvent).filter(StreamEvent.id == delivery.event_id).first()

        if not stream or not event:
            delivery.status = DeliveryStatus.FAILED  # type: ignore[assignment]
            delivery.error_message = "Stream or event not found"  # type: ignore[assignment]
            self.db.commit()
            return delivery

        # ── Adapter resolution & send ────────────────────────────────
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

            # ── Post-success token consumption ───────────────────────
            token_result = await self._consume_tokens_after_success(stream, delivery)
            if token_result is not None:
                cost = get_credit_cost(ChannelType(str(stream.channel_type)))
                delivery.response_metadata = {  # type: ignore[assignment]
                    **dict(delivery.response_metadata or {}),
                    "credits_consumed": cost if token_result.success else 0,
                    "credits_balance": token_result.balance,
                }
                if not token_result.success:
                    self._log_token_failure(delivery, stream, token_result)

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
                "Delivery dispatch failed — no tokens consumed",
                extra={
                    "delivery_id": delivery.id,
                    "stream_id": stream.id,
                    "error": result.error,
                    "attempt": delivery.attempt_count,
                },
            )

        self.db.commit()
        return delivery

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    @staticmethod
    def _log_token_failure(
        delivery: StreamDelivery,
        stream: Stream,
        token_result: TokenConsumeResult,
    ) -> None:
        """Log a structured warning when post-success token consumption fails."""
        if token_result.error_code in (
            TokenErrorCode.INSUFFICIENT_TOKENS,
            TokenErrorCode.MODULE_NOT_ENABLED,
        ):
            logger.warning(
                "Delivery sent but token consumption refused — organisation may have exhausted credits",
                extra={
                    "delivery_id": delivery.id,
                    "stream_id": stream.id,
                    "org_id": stream.organization_id,
                    "error_code": token_result.error_code,
                    "error": token_result.error,
                },
            )
        else:
            logger.warning(
                "Delivery sent but token consumption failed (transient) — credits were NOT debited",
                extra={
                    "delivery_id": delivery.id,
                    "stream_id": stream.id,
                    "org_id": stream.organization_id,
                    "error_code": token_result.error_code,
                    "error": token_result.error,
                },
            )

    # ------------------------------------------------------------------
    # Batch dispatchers
    # ------------------------------------------------------------------

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
        stream = self.db.query(Stream).filter(Stream.id == stream_id, Stream.organization_id == org_id).first()
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
        channel_config: dict,  # type: ignore[type-arg]
    ) -> DispatchResult:
        """Send a test message to validate channel configuration.

        Test connections never consume tokens (ADR-0016: 0 credits).

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
            payload={"test": True, "message": TEST_CONNECTION_PAYLOAD_MESSAGE},
            organization_id="test",
            summary=TEST_CONNECTION_SUMMARY,
            entity_type="test",
            entity_id="0",
        )

        mock_stream = Stream(
            id=0,
            name=TEST_CONNECTION_STREAM_NAME,
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
