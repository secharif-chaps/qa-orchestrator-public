"""Outbox service for transactional event production.

Provides methods to emit events within a caller's transaction,
query pending events, and mark them as published after relay.
"""

from datetime import UTC, datetime

from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.models.outbox import OutboxEvent

logger = get_logger(__name__)


class OutboxService:
    """Transaction-safe outbox event service.

    The emit() method writes an event within the caller's existing transaction
    (no commit). The caller's db.commit() atomically persists both the business
    entity and the outbox event.
    """

    def __init__(self, db: Session):
        self.db = db

    def emit(
        self,
        *,
        event_type: str,
        aggregate_type: str,
        aggregate_id: str,
        organization_id: str,
        folder_id: str | None = None,
        payload: dict | None = None,
        summary: str | None = None,
    ) -> OutboxEvent:
        """Write an outbox event within the caller's transaction.

        Does NOT commit — the caller's db.commit() persists this atomically
        alongside the business operation.

        Args:
            event_type: Dot-separated event type (e.g. "screen.company.created")
            aggregate_type: Entity type (e.g. "company", "task")
            aggregate_id: Entity ID as string
            organization_id: Organization UUID
            folder_id: Optional folder scope
            payload: Event payload dict
            summary: Human-readable summary

        Returns:
            The created OutboxEvent (not yet committed)
        """
        event = OutboxEvent(
            event_type=event_type,
            aggregate_type=aggregate_type,
            aggregate_id=aggregate_id,
            organization_id=organization_id,
            folder_id=folder_id,
            payload=payload or {},
            summary=summary,
        )
        self.db.add(event)
        logger.debug(
            "Outbox event emitted",
            extra={
                "event_type": event_type,
                "aggregate_type": aggregate_type,
                "aggregate_id": aggregate_id,
                "organization_id": organization_id,
            },
        )
        return event

    def get_pending(self, limit: int = 100) -> list[OutboxEvent]:
        """Get unpublished events ordered by creation time.

        Args:
            limit: Maximum number of events to return

        Returns:
            List of unpublished OutboxEvent instances
        """
        return (
            self.db.query(OutboxEvent)
            .filter(OutboxEvent.published_at.is_(None))
            .order_by(OutboxEvent.created_at)
            .limit(limit)
            .all()
        )

    def mark_published(self, event_ids: list[int]) -> None:
        """Set published_at on events and commit.

        Args:
            event_ids: List of outbox event IDs to mark as published
        """
        if not event_ids:
            return
        now = datetime.now(UTC)
        self.db.query(OutboxEvent).filter(OutboxEvent.id.in_(event_ids)).update(
            {"published_at": now},
            synchronize_session=False,
        )
        self.db.commit()
        logger.info(
            "Marked outbox events as published",
            extra={"count": len(event_ids), "event_ids": event_ids},
        )
