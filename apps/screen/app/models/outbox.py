"""Outbox event model for transactional event production.

Stores domain events atomically alongside business entities,
to be relayed asynchronously to the Stream service by the OutboxRelay.
"""

from sqlalchemy import Column, DateTime, Integer, String
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.sql import func

from app.database import SCREEN_SCHEMA, Base


class OutboxEvent(Base):
    """Transactional outbox event.

    Events are written within the same transaction as the business operation,
    then polled and relayed to Stream by the OutboxRelay background task.

    Attributes:
        id: Primary key
        event_type: Dot-separated event type (e.g. "screen.company.created")
        aggregate_type: Entity type (e.g. "company", "task")
        aggregate_id: Entity ID as string
        folder_id: Optional folder scope
        organization_id: Organization UUID
        payload: Event payload as JSONB
        summary: Human-readable summary
        created_at: When the event was created
        published_at: When the event was relayed (null = pending)
    """

    __tablename__ = "outbox"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    event_type = Column(String(100), nullable=False)
    aggregate_type = Column(String(50), nullable=False)
    aggregate_id = Column(String(100), nullable=False)
    folder_id = Column(String(100), nullable=True)
    organization_id = Column(String(100), nullable=False)
    payload = Column(JSONB, nullable=False, server_default="{}")
    summary = Column(String(500), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    published_at = Column(DateTime(timezone=True), nullable=True)
