from sqlalchemy import Boolean, Column, DateTime, Integer, String, Text
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import STREAM_SCHEMA, Base


class StreamEvent(Base):
    __tablename__ = "stream_events"
    __table_args__ = ({"schema": STREAM_SCHEMA},)

    id = Column(Integer, primary_key=True, index=True)

    # Event identification (format: source.resource.action)
    event_type = Column(String(255), nullable=False, index=True)
    folder_id = Column(String, index=True, nullable=False)
    source = Column(String(100), nullable=False, index=True)

    # Event payload
    payload = Column(JSONB, nullable=False, default=dict)

    # Multi-tenancy
    organization_id = Column(String, index=True, nullable=False)

    # Soft-delete
    is_deleted = Column(Boolean, nullable=False, default=False)

    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Optional reference to the entity that triggered the event
    entity_id = Column(String(255), nullable=True)
    entity_type = Column(String(100), nullable=True)

    # Human-readable summary
    summary = Column(Text, nullable=True)

    # Relationships
    deliveries = relationship("StreamDelivery", back_populates="event", cascade="all, delete-orphan")
