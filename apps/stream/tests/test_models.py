"""Tests for Stream service SQLAlchemy models."""

from app.models import (
    ChannelType,
    DeliveryStatus,
    Stream,
    StreamDelivery,
    StreamEvent,
    StreamMode,
    StreamStatus,
)


class TestStreamModel:
    def test_create_stream(self, db_session, mock_user):
        """Test creating a basic stream."""
        stream = Stream(
            name="My Teams Stream",
            channel_type=ChannelType.TEAMS,
            channel_config={"webhook_url": "https://example.com/webhook"},
            mode=StreamMode.LIVE,
            status=StreamStatus.ACTIVE,
            organization_id=mock_user["organization_id"],
            owner_id=mock_user["sub"],
            owner_username=mock_user["preferred_username"],
            subscribed_events=["screen.company.created"],
        )
        db_session.add(stream)
        db_session.commit()
        db_session.refresh(stream)

        assert stream.id is not None
        assert stream.name == "My Teams Stream"
        assert stream.channel_type == ChannelType.TEAMS
        assert stream.mode == StreamMode.LIVE
        assert stream.status == StreamStatus.ACTIVE
        assert stream.organization_id == "test-org-123"
        assert stream.created_at is not None
        assert stream.updated_at is not None

    def test_create_recurrence_stream(self, db_session, mock_user):
        """Test creating a recurrence-mode stream with cron."""
        stream = Stream(
            name="Weekly Digest",
            channel_type=ChannelType.SLACK_WEBHOOK,
            channel_config={"webhook_url": "https://hooks.slack.com/test"},
            mode=StreamMode.RECURRENCE,
            cron_expression="0 9 * * MON",
            status=StreamStatus.ACTIVE,
            organization_id=mock_user["organization_id"],
            owner_id=mock_user["sub"],
            subscribed_events=[],
        )
        db_session.add(stream)
        db_session.commit()

        assert stream.cron_expression == "0 9 * * MON"
        assert stream.mode == StreamMode.RECURRENCE

    def test_stream_status_transitions(self, db_session, mock_user):
        """Test updating stream status."""
        stream = Stream(
            name="Test Stream",
            channel_type=ChannelType.WEBHOOK,
            channel_config={"url": "https://example.com/hook"},
            mode=StreamMode.LIVE,
            status=StreamStatus.ACTIVE,
            organization_id=mock_user["organization_id"],
            owner_id=mock_user["sub"],
            subscribed_events=[],
        )
        db_session.add(stream)
        db_session.commit()

        stream.status = StreamStatus.PAUSED
        db_session.commit()
        db_session.refresh(stream)
        assert stream.status == StreamStatus.PAUSED

        stream.status = StreamStatus.ARCHIVED
        db_session.commit()
        db_session.refresh(stream)
        assert stream.status == StreamStatus.ARCHIVED

    def test_query_streams_by_organization(self, db_session, mock_user):
        """Test querying streams filtered by organization."""
        for i in range(3):
            db_session.add(Stream(
                name=f"Stream {i}",
                channel_type=ChannelType.TEAMS,
                channel_config={},
                mode=StreamMode.LIVE,
                status=StreamStatus.ACTIVE,
                organization_id=mock_user["organization_id"],
                owner_id=mock_user["sub"],
                subscribed_events=[],
            ))
        # Another org's stream
        db_session.add(Stream(
            name="Other Org Stream",
            channel_type=ChannelType.TEAMS,
            channel_config={},
            mode=StreamMode.LIVE,
            status=StreamStatus.ACTIVE,
            organization_id="other-org-456",
            owner_id="other-user",
            subscribed_events=[],
        ))
        db_session.commit()

        results = db_session.query(Stream).filter(
            Stream.organization_id == mock_user["organization_id"]
        ).all()
        assert len(results) == 3


class TestStreamEventModel:
    def test_create_event(self, db_session):
        """Test creating a stream event."""
        event = StreamEvent(
            event_type="screen.company.created",
            source="screen",
            folder_id="folder-abc",
            payload={"company_id": 42, "name": "Acme Corp"},
            organization_id="test-org-123",
            entity_id="42",
            entity_type="company",
            summary="Company Acme Corp was created",
        )
        db_session.add(event)
        db_session.commit()
        db_session.refresh(event)

        assert event.id is not None
        assert event.event_type == "screen.company.created"
        assert event.source == "screen"
        assert event.folder_id == "folder-abc"
        assert event.payload["company_id"] == 42
        assert event.is_deleted is False
        assert event.created_at is not None

    def test_event_soft_delete(self, db_session):
        """Test soft-deleting an event."""
        event = StreamEvent(
            event_type="screen.company.updated",
            source="screen",
            folder_id="folder-abc",
            payload={},
            organization_id="test-org-123",
        )
        db_session.add(event)
        db_session.commit()

        event.is_deleted = True
        db_session.commit()
        db_session.refresh(event)

        assert event.is_deleted is True

        # Soft-deleted events are still queryable
        all_events = db_session.query(StreamEvent).all()
        assert len(all_events) == 1

        # But can be filtered out
        active_events = db_session.query(StreamEvent).filter(
            StreamEvent.is_deleted == False  # noqa: E712
        ).all()
        assert len(active_events) == 0

    def test_event_without_optional_fields(self, db_session):
        """Test creating an event with only required fields."""
        event = StreamEvent(
            event_type="screen.company.updated",
            source="screen",
            folder_id="folder-abc",
            payload={},
            organization_id="test-org-123",
        )
        db_session.add(event)
        db_session.commit()

        assert event.entity_id is None
        assert event.entity_type is None
        assert event.summary is None


class TestStreamDeliveryModel:
    def _create_stream_and_event(self, db_session, mock_user):
        """Helper to create a stream and event for delivery tests."""
        stream = Stream(
            name="Test Stream",
            channel_type=ChannelType.TEAMS,
            channel_config={"webhook_url": "https://example.com/webhook"},
            mode=StreamMode.LIVE,
            status=StreamStatus.ACTIVE,
            organization_id=mock_user["organization_id"],
            owner_id=mock_user["sub"],
            subscribed_events=[],
        )
        event = StreamEvent(
            event_type="screen.company.created",
            source="screen",
            folder_id="folder-abc",
            payload={"company_id": 1},
            organization_id=mock_user["organization_id"],
        )
        db_session.add_all([stream, event])
        db_session.commit()
        return stream, event

    def test_create_delivery(self, db_session, mock_user):
        """Test creating a delivery linking stream and event."""
        stream, event = self._create_stream_and_event(db_session, mock_user)

        delivery = StreamDelivery(
            stream_id=stream.id,
            event_id=event.id,
            status=DeliveryStatus.PENDING,
        )
        db_session.add(delivery)
        db_session.commit()
        db_session.refresh(delivery)

        assert delivery.id is not None
        assert delivery.stream_id == stream.id
        assert delivery.event_id == event.id
        assert delivery.status == DeliveryStatus.PENDING
        assert delivery.attempt_count == 0

    def test_delivery_fk_relationships(self, db_session, mock_user):
        """Test FK relationships between delivery, stream, and event."""
        stream, event = self._create_stream_and_event(db_session, mock_user)

        delivery = StreamDelivery(
            stream_id=stream.id,
            event_id=event.id,
            status=DeliveryStatus.DELIVERED,
        )
        db_session.add(delivery)
        db_session.commit()
        db_session.refresh(delivery)

        assert delivery.stream.name == "Test Stream"
        assert delivery.event.event_type == "screen.company.created"

    def test_delivery_status_update(self, db_session, mock_user):
        """Test updating delivery status through lifecycle."""
        stream, event = self._create_stream_and_event(db_session, mock_user)

        delivery = StreamDelivery(
            stream_id=stream.id,
            event_id=event.id,
            status=DeliveryStatus.PENDING,
        )
        db_session.add(delivery)
        db_session.commit()

        # Simulate failed delivery
        delivery.status = DeliveryStatus.FAILED
        delivery.error_message = "Connection timeout"
        delivery.attempt_count = 1
        db_session.commit()
        db_session.refresh(delivery)

        assert delivery.status == DeliveryStatus.FAILED
        assert delivery.error_message == "Connection timeout"
        assert delivery.attempt_count == 1

    def test_cascade_delete_stream_removes_deliveries(self, db_session, mock_user):
        """Test that deleting a stream cascades to its deliveries."""
        stream, event = self._create_stream_and_event(db_session, mock_user)

        delivery = StreamDelivery(
            stream_id=stream.id,
            event_id=event.id,
            status=DeliveryStatus.PENDING,
        )
        db_session.add(delivery)
        db_session.commit()

        assert db_session.query(StreamDelivery).count() == 1

        db_session.delete(stream)
        db_session.commit()

        assert db_session.query(StreamDelivery).count() == 0

    def test_cascade_delete_event_removes_deliveries(self, db_session, mock_user):
        """Test that deleting an event cascades to its deliveries."""
        stream, event = self._create_stream_and_event(db_session, mock_user)

        delivery = StreamDelivery(
            stream_id=stream.id,
            event_id=event.id,
            status=DeliveryStatus.PENDING,
        )
        db_session.add(delivery)
        db_session.commit()

        assert db_session.query(StreamDelivery).count() == 1

        db_session.delete(event)
        db_session.commit()

        assert db_session.query(StreamDelivery).count() == 0


class TestEnumValues:
    def test_channel_type_values(self):
        assert set(ChannelType) == {ChannelType.TEAMS, ChannelType.SLACK_WEBHOOK, ChannelType.WEBHOOK}

    def test_stream_mode_values(self):
        assert set(StreamMode) == {StreamMode.LIVE, StreamMode.RECURRENCE}

    def test_stream_status_values(self):
        assert set(StreamStatus) == {StreamStatus.DRAFT, StreamStatus.ACTIVE, StreamStatus.PAUSED, StreamStatus.ARCHIVED}

    def test_delivery_status_values(self):
        assert set(DeliveryStatus) == {
            DeliveryStatus.PENDING, DeliveryStatus.DELIVERED,
            DeliveryStatus.FAILED, DeliveryStatus.SKIPPED,
        }
