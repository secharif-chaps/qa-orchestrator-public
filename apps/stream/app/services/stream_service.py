"""Stream CRUD service with org-scoped access checks."""

from pydantic import ValidationError
from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.models.delivery import StreamDelivery
from app.models.stream import ChannelType, Stream, StreamStatus
from app.schemas.stream import (
    CHANNEL_CONFIG_MAP,
    StreamCreate,
    StreamUpdate,
)

logger = get_logger(__name__)

# Allowed status transitions: current -> set of valid next statuses
ALLOWED_TRANSITIONS: dict[StreamStatus, set[StreamStatus]] = {
    StreamStatus.ACTIVE: {StreamStatus.PAUSED, StreamStatus.ARCHIVED},
    StreamStatus.PAUSED: {StreamStatus.ACTIVE, StreamStatus.ARCHIVED},
    StreamStatus.ARCHIVED: set(),  # terminal
}


class StreamServiceError(Exception):
    """Base error for stream service operations."""

    def __init__(self, message: str, status_code: int = 400):
        self.message = message
        self.status_code = status_code
        super().__init__(message)


def validate_channel_config(channel_type: ChannelType, config: dict) -> None:
    """Validate channel_config against the channel-type Pydantic model.

    Raises:
        StreamServiceError: If config is invalid for the channel type
    """
    model = CHANNEL_CONFIG_MAP.get(channel_type)
    if not model:
        raise StreamServiceError(f"Unknown channel type: {channel_type}")
    try:
        model(**config)
    except ValidationError as e:
        raise StreamServiceError(f"Invalid channel config for {channel_type}: {e}") from e


class StreamService:
    def __init__(self, db: Session):
        self.db = db

    def list_streams(
        self,
        organization_id: str,
        folder_id: str,
        offset: int = 0,
        limit: int = 10,
    ) -> tuple[list[Stream], int]:
        """List streams in a folder, scoped to organization.

        Returns:
            Tuple of (streams list, total count)
        """
        query = self.db.query(Stream).filter(
            Stream.organization_id == organization_id,
            Stream.folder_id == folder_id,
        )
        total = query.count()
        streams = query.order_by(Stream.created_at.desc()).offset(offset).limit(limit).all()
        return streams, total

    def get_stream(self, stream_id: int, organization_id: str) -> Stream:
        """Get a stream by ID, verifying org access.

        Raises:
            StreamServiceError: If stream not found or not in org
        """
        stream = self.db.query(Stream).filter(Stream.id == stream_id).first()
        if not stream:
            raise StreamServiceError("Stream not found", status_code=404)
        if stream.organization_id != organization_id:
            raise StreamServiceError("Stream not found", status_code=404)
        return stream

    def create_stream(
        self,
        data: StreamCreate,
        folder_id: str,
        organization_id: str,
        owner_id: str,
        owner_username: str,
    ) -> Stream:
        """Create a new stream in a folder.

        Validates channel config before persisting.

        Raises:
            StreamServiceError: If channel config is invalid
        """
        validate_channel_config(data.channel_type, data.channel_config)

        stream = Stream(
            name=data.name,
            description=data.description,
            channel_type=data.channel_type,
            channel_config=data.channel_config,
            mode=data.mode,
            cron_expression=data.cron_expression,
            subscribed_events=data.subscribed_events,
            folder_id=folder_id,
            organization_id=organization_id,
            owner_id=owner_id,
            owner_username=owner_username,
            status=StreamStatus.ACTIVE,
        )
        self.db.add(stream)
        self.db.commit()
        self.db.refresh(stream)

        logger.info(
            "Stream created",
            extra={"stream_id": stream.id, "folder_id": folder_id, "org_id": organization_id},
        )
        return stream

    def update_stream(
        self,
        stream_id: int,
        data: StreamUpdate,
        organization_id: str,
    ) -> Stream:
        """Update a stream's mutable fields.

        Validates channel config if provided.

        Raises:
            StreamServiceError: If stream not found, archived, or config invalid
        """
        stream = self.get_stream(stream_id, organization_id)

        if stream.status == StreamStatus.ARCHIVED:
            raise StreamServiceError("Cannot update an archived stream")

        update_data = data.model_dump(exclude_unset=True)

        # Validate channel config if being updated
        if "channel_config" in update_data:
            validate_channel_config(stream.channel_type, update_data["channel_config"])  # type: ignore[arg-type]

        for field, value in update_data.items():
            setattr(stream, field, value)

        self.db.commit()
        self.db.refresh(stream)

        logger.info("Stream updated", extra={"stream_id": stream_id})
        return stream

    def delete_stream(self, stream_id: int, organization_id: str) -> None:
        """Delete a stream and cascade to deliveries.

        Raises:
            StreamServiceError: If stream not found or not in org
        """
        stream = self.get_stream(stream_id, organization_id)
        self.db.delete(stream)
        self.db.commit()

        logger.info("Stream deleted", extra={"stream_id": stream_id})

    def update_status(
        self,
        stream_id: int,
        new_status: StreamStatus,
        organization_id: str,
    ) -> Stream:
        """Transition a stream to a new status.

        Validates the transition is allowed.

        Raises:
            StreamServiceError: If transition is invalid
        """
        stream = self.get_stream(stream_id, organization_id)

        allowed = ALLOWED_TRANSITIONS.get(stream.status, set())  # type: ignore[call-overload]
        if new_status not in allowed:
            raise StreamServiceError(f"Cannot transition from {stream.status} to {new_status}")

        stream.status = new_status  # type: ignore[assignment]
        self.db.commit()
        self.db.refresh(stream)

        logger.info(
            "Stream status updated",
            extra={"stream_id": stream_id, "new_status": new_status},
        )
        return stream

    def list_deliveries(
        self,
        stream_id: int,
        organization_id: str,
        offset: int = 0,
        limit: int = 10,
    ) -> tuple[list[StreamDelivery], int]:
        """List deliveries for a stream, scoped to organization.

        Returns:
            Tuple of (deliveries list, total count)
        """
        # Verify stream access first
        self.get_stream(stream_id, organization_id)

        query = self.db.query(StreamDelivery).filter(
            StreamDelivery.stream_id == stream_id,
        )
        total = query.count()
        deliveries = query.order_by(StreamDelivery.created_at.desc()).offset(offset).limit(limit).all()
        return deliveries, total
