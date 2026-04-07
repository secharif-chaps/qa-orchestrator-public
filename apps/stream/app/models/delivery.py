from enum import StrEnum

from sqlalchemy import Column, DateTime, ForeignKey, Integer, Text
from sqlalchemy import Enum as SQLEnum
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import STREAM_SCHEMA, Base


class DeliveryStatus(StrEnum):
    PENDING = "pending"
    DELIVERED = "delivered"
    FAILED = "failed"
    SKIPPED = "skipped"


class StreamDelivery(Base):
    __tablename__ = "stream_deliveries"
    __table_args__ = {"schema": STREAM_SCHEMA}

    id = Column(Integer, primary_key=True, index=True)

    stream_id = Column(
        Integer,
        ForeignKey(f"{STREAM_SCHEMA}.streams.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    event_id = Column(
        Integer,
        ForeignKey(f"{STREAM_SCHEMA}.stream_events.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )

    status: Column[str] = Column(
        SQLEnum(
            DeliveryStatus,
            values_callable=lambda obj: [e.value for e in obj],
            name="delivery_status_enum",
            schema=STREAM_SCHEMA,
        ),
        nullable=False,
        default=DeliveryStatus.PENDING,
    )

    # Delivery details
    error_message = Column(Text, nullable=True)
    response_metadata = Column(JSONB, nullable=True)

    delivered_at = Column(DateTime(timezone=True), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Retry tracking
    attempt_count = Column(Integer, nullable=False, default=0)
    next_retry_at = Column(DateTime(timezone=True), nullable=True)

    # Relationships
    stream = relationship("Stream", back_populates="deliveries")
    event = relationship("StreamEvent", back_populates="deliveries")
