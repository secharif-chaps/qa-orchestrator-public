from enum import StrEnum

from sqlalchemy import Column, DateTime, Integer, String, Text
from sqlalchemy import Enum as SQLEnum
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import STREAM_SCHEMA, Base


class ChannelType(StrEnum):
    TEAMS = "teams"
    SLACK_WEBHOOK = "slack_webhook"
    WEBHOOK = "webhook"


class StreamMode(StrEnum):
    LIVE = "live"
    RECURRENCE = "recurrence"


class StreamStatus(StrEnum):
    DRAFT = "draft"
    ACTIVE = "active"
    PAUSED = "paused"
    ARCHIVED = "archived"


class Stream(Base):
    __tablename__ = "streams"
    __table_args__ = {"schema": STREAM_SCHEMA}

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    description = Column(Text, nullable=True)

    channel_type: Column[str] = Column(
        SQLEnum(
            ChannelType,
            values_callable=lambda obj: [e.value for e in obj],
            name="channel_type_enum",
            schema=STREAM_SCHEMA,
        ),
        nullable=False,
    )
    channel_config = Column(JSONB, nullable=False, default=dict)

    mode: Column[str] = Column(
        SQLEnum(
            StreamMode,
            values_callable=lambda obj: [e.value for e in obj],
            name="stream_mode_enum",
            schema=STREAM_SCHEMA,
        ),
        nullable=False,
    )
    cron_expression = Column(String(100), nullable=True)

    status: Column[str] = Column(
        SQLEnum(
            StreamStatus,
            values_callable=lambda obj: [e.value for e in obj],
            name="stream_status_enum",
            schema=STREAM_SCHEMA,
        ),
        nullable=False,
        default=StreamStatus.ACTIVE,
    )

    # Folder scoping
    folder_id = Column(String, index=True, nullable=False)

    # Multi-tenancy
    organization_id = Column(String, index=True, nullable=False)
    owner_id = Column(String, nullable=False)
    owner_username = Column(String, nullable=True)

    # Event type filter (list of subscribed event types)
    subscribed_events = Column(JSONB, nullable=False, default=list)

    # NP6 action id — populated lazily by NP6EmailProvider on the first
    # dispatch. Nullable because non-newsletter streams (Teams, Slack,
    # Webhook) never reach NP6. See ADR-0020 §"Mapping Newsletter Concepts
    # to NP6 Primitives" (amended 2026-05-04). No `np6_segment_id`: the
    # validated workflow addresses recipients directly by unicity.
    np6_action_id = Column(String, nullable=True)

    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)

    # Relationships
    deliveries = relationship("StreamDelivery", back_populates="stream", cascade="all, delete-orphan")
