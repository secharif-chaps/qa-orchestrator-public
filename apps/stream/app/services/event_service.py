"""Event ingestion service — creates events and matches them to active streams."""

from datetime import UTC, datetime, timedelta

from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.models.delivery import DeliveryStatus, StreamDelivery
from app.models.event import StreamEvent
from app.models.stream import Stream, StreamStatus
from app.schemas.event import EventIngest

logger = get_logger(__name__)

# Events for the same entity within this window are deduplicated
DEDUP_WINDOW = timedelta(minutes=5)


class EventService:
    def __init__(self, db: Session):
        self.db = db

    def ingest_event(self, data: EventIngest, organization_id: str) -> StreamEvent:
        """Ingest an event and create pending deliveries for matching streams.

        Flow:
        1. Extract source from event_type first segment
        2. Deduplicate by (source, entity_id, organization_id) within a time window
        3. Create StreamEvent record (or update if within dedup window)
        4. Find active streams in same org + folder with matching subscribed_events
        5. Create StreamDelivery(status=PENDING) per match
        6. Commit

        Returns:
            The created StreamEvent
        """
        source = data.event_type.split(".")[0]

        # Deduplication: only within a time window to avoid spamming
        # Events outside the window create a fresh event + new deliveries
        if data.entity_id:
            dedup_cutoff = datetime.now(UTC) - DEDUP_WINDOW
            existing = (
                self.db.query(StreamEvent)
                .filter(
                    StreamEvent.event_type == data.event_type,
                    StreamEvent.source == source,
                    StreamEvent.entity_id == data.entity_id,
                    StreamEvent.organization_id == organization_id,
                    StreamEvent.is_deleted == False,  # noqa: E712
                    StreamEvent.created_at >= dedup_cutoff,
                )
                .first()
            )
            if existing:
                existing.payload = data.payload  # type: ignore[assignment]
                if data.summary:
                    existing.summary = data.summary  # type: ignore[assignment]
                self.db.commit()
                self.db.refresh(existing)
                logger.info(
                    "Event deduplicated (updated existing)",
                    extra={
                        "event_id": existing.id,
                        "event_type": data.event_type,
                        "org_id": organization_id,
                    },
                )
                return existing

        # Create new event
        event = StreamEvent(
            event_type=data.event_type,
            source=source,
            folder_id=data.folder_id,
            payload=data.payload,
            organization_id=organization_id,
            entity_id=data.entity_id,
            entity_type=data.entity_type,
            summary=data.summary,
        )
        self.db.add(event)
        self.db.flush()  # get event.id before creating deliveries

        # Find active streams in same org and folder that subscribe to this event type
        matching_streams = (
            self.db.query(Stream)
            .filter(
                Stream.organization_id == organization_id,
                Stream.folder_id == data.folder_id,
                Stream.status == StreamStatus.ACTIVE,
            )
            .all()
        )

        delivery_count = 0
        for stream in matching_streams:
            subscribed: list[str] = stream.subscribed_events or []  # type: ignore[assignment]
            if data.event_type in subscribed:
                delivery = StreamDelivery(
                    stream_id=stream.id,
                    event_id=event.id,
                    status=DeliveryStatus.PENDING,
                )
                self.db.add(delivery)
                delivery_count += 1

        self.db.commit()
        self.db.refresh(event)

        logger.info(
            "Event ingested",
            extra={
                "event_id": event.id,
                "event_type": data.event_type,
                "org_id": organization_id,
                "deliveries_created": delivery_count,
            },
        )
        return event

    def cleanup_events(self, before: datetime, organization_id: str) -> int:
        """Soft-delete events older than the given timestamp.

        Args:
            before: Delete events created before this datetime
            organization_id: Scope to organization

        Returns:
            Number of events soft-deleted
        """
        count = (
            self.db.query(StreamEvent)
            .filter(
                StreamEvent.organization_id == organization_id,
                StreamEvent.created_at < before,
                StreamEvent.is_deleted == False,  # noqa: E712
            )
            .update({"is_deleted": True})
        )
        self.db.commit()

        logger.info(
            "Events cleaned up",
            extra={
                "count": count,
                "before": before.isoformat(),
                "org_id": organization_id,
            },
        )
        return count
