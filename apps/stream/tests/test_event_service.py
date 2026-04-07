"""Unit tests for EventService."""

from datetime import UTC, datetime, timedelta

from app.models.delivery import DeliveryStatus, StreamDelivery
from app.models.event import StreamEvent
from app.models.stream import ChannelType, Stream, StreamMode, StreamStatus
from app.schemas.event import EventIngest
from app.services.event_service import EventService

ORG_ID = "test-org-123"


def _create_stream(db_session, **overrides) -> Stream:
    defaults = {
        "name": "Test Stream",
        "channel_type": ChannelType.WEBHOOK,
        "channel_config": {"url": "https://example.com/hook", "headers": {}, "secret": None},
        "mode": StreamMode.LIVE,
        "subscribed_events": ["screen.company.created"],
        "folder_id": "folder-abc",
        "organization_id": ORG_ID,
        "owner_id": "user-1",
        "owner_username": "testuser",
        "status": StreamStatus.ACTIVE,
    }
    defaults.update(overrides)
    stream = Stream(**defaults)
    db_session.add(stream)
    db_session.commit()
    db_session.refresh(stream)
    return stream


class TestEventIngestion:
    def test_ingest_creates_event(self, event_service: EventService):
        data = EventIngest(
            event_type="screen.company.created",
            folder_id="folder-abc",
            payload={"company_id": 42},
            entity_id="42",
            entity_type="company",
            summary="Company Acme created",
        )
        event = event_service.ingest_event(data, ORG_ID)

        assert event.id is not None
        assert event.event_type == "screen.company.created"
        assert event.source == "screen"
        assert event.folder_id == "folder-abc"
        assert event.organization_id == ORG_ID
        assert event.payload == {"company_id": 42}

    def test_ingest_creates_deliveries_for_matching_streams(self, db_session, event_service: EventService):
        _create_stream(db_session, subscribed_events=["screen.company.created"])
        _create_stream(db_session, name="Stream 2", subscribed_events=["screen.company.created"])

        data = EventIngest(event_type="screen.company.created", folder_id="folder-abc", payload={})
        event = event_service.ingest_event(data, ORG_ID)

        deliveries = db_session.query(StreamDelivery).filter(StreamDelivery.event_id == event.id).all()
        assert len(deliveries) == 2
        assert all(d.status == DeliveryStatus.PENDING for d in deliveries)

    def test_ingest_no_deliveries_for_unmatched_events(self, db_session, event_service: EventService):
        _create_stream(db_session, subscribed_events=["screen.company.created"])

        data = EventIngest(event_type="screen.company.updated", folder_id="folder-abc", payload={})
        event = event_service.ingest_event(data, ORG_ID)

        deliveries = db_session.query(StreamDelivery).filter(StreamDelivery.event_id == event.id).all()
        assert len(deliveries) == 0

    def test_ingest_skips_paused_streams(self, db_session, event_service: EventService):
        _create_stream(db_session, status=StreamStatus.PAUSED)

        data = EventIngest(event_type="screen.company.created", folder_id="folder-abc", payload={})
        event = event_service.ingest_event(data, ORG_ID)

        deliveries = db_session.query(StreamDelivery).filter(StreamDelivery.event_id == event.id).all()
        assert len(deliveries) == 0

    def test_ingest_skips_archived_streams(self, db_session, event_service: EventService):
        _create_stream(db_session, status=StreamStatus.ARCHIVED)

        data = EventIngest(event_type="screen.company.created", folder_id="folder-abc", payload={})
        event = event_service.ingest_event(data, ORG_ID)

        deliveries = db_session.query(StreamDelivery).filter(StreamDelivery.event_id == event.id).all()
        assert len(deliveries) == 0

    def test_ingest_org_isolation(self, db_session, event_service: EventService):
        _create_stream(db_session, organization_id="other-org")

        data = EventIngest(event_type="screen.company.created", folder_id="folder-abc", payload={})
        event = event_service.ingest_event(data, ORG_ID)

        deliveries = db_session.query(StreamDelivery).filter(StreamDelivery.event_id == event.id).all()
        assert len(deliveries) == 0

    def test_ingest_extracts_source(self, event_service: EventService):
        data = EventIngest(event_type="target.watchfile.created", folder_id="folder-abc", payload={})
        event = event_service.ingest_event(data, ORG_ID)
        assert event.source == "target"

    def test_ingest_deduplicates_within_time_window(self, db_session, event_service: EventService):
        """Rapid duplicate events within the dedup window are merged."""
        _create_stream(db_session, subscribed_events=["screen.company.created"])

        data1 = EventIngest(
            event_type="screen.company.created",
            folder_id="folder-abc",
            payload={"version": 1},
            entity_id="42",
            entity_type="company",
        )
        event1 = event_service.ingest_event(data1, ORG_ID)

        data2 = EventIngest(
            event_type="screen.company.created",
            folder_id="folder-abc",
            payload={"version": 2},
            entity_id="42",
            entity_type="company",
        )
        event2 = event_service.ingest_event(data2, ORG_ID)

        assert event1.id == event2.id  # Same event returned
        assert event2.payload == {"version": 2}  # Payload updated

    def test_ingest_creates_new_event_after_dedup_window(self, db_session, event_service: EventService):
        """Events for the same entity outside the dedup window create a new event + deliveries."""
        _create_stream(db_session, subscribed_events=["screen.company.created"])

        # First event
        data1 = EventIngest(
            event_type="screen.company.created",
            folder_id="folder-abc",
            payload={"version": 1},
            entity_id="42",
            entity_type="company",
        )
        event1 = event_service.ingest_event(data1, ORG_ID)

        # Push created_at back beyond the dedup window
        event1.created_at = datetime.now(UTC) - timedelta(minutes=10)
        db_session.commit()

        # Second event — outside dedup window
        data2 = EventIngest(
            event_type="screen.company.created",
            folder_id="folder-abc",
            payload={"version": 2},
            entity_id="42",
            entity_type="company",
        )
        event2 = event_service.ingest_event(data2, ORG_ID)

        assert event2.id != event1.id  # New event created
        assert event2.payload == {"version": 2}

        # New deliveries should be created for the new event
        deliveries = db_session.query(StreamDelivery).filter(StreamDelivery.event_id == event2.id).all()
        assert len(deliveries) == 1
        assert deliveries[0].status == DeliveryStatus.PENDING

    def test_ingest_folder_isolation(self, db_session, event_service: EventService):
        """Stream in folder-abc should not receive events from other-folder."""
        _create_stream(db_session, folder_id="folder-abc", subscribed_events=["screen.company.created"])

        data = EventIngest(event_type="screen.company.created", folder_id="other-folder", payload={})
        event = event_service.ingest_event(data, ORG_ID)

        deliveries = db_session.query(StreamDelivery).filter(StreamDelivery.event_id == event.id).all()
        assert len(deliveries) == 0


class TestEventCleanup:
    def test_cleanup_soft_deletes_old_events(self, db_session, event_service: EventService):
        # Create an event manually with old timestamp
        old_event = StreamEvent(
            event_type="screen.company.created",
            source="screen",
            folder_id="folder-abc",
            payload={},
            organization_id=ORG_ID,
        )
        db_session.add(old_event)
        db_session.commit()

        # Cleanup events before "now + 1 hour" (catches everything)
        cutoff = datetime.now(UTC) + timedelta(hours=1)
        count = event_service.cleanup_events(cutoff, ORG_ID)

        assert count == 1

        db_session.refresh(old_event)
        assert old_event.is_deleted is True

    def test_cleanup_ignores_already_deleted(self, db_session, event_service: EventService):
        old_event = StreamEvent(
            event_type="screen.company.created",
            source="screen",
            folder_id="folder-abc",
            payload={},
            organization_id=ORG_ID,
            is_deleted=True,
        )
        db_session.add(old_event)
        db_session.commit()

        cutoff = datetime.now(UTC) + timedelta(hours=1)
        count = event_service.cleanup_events(cutoff, ORG_ID)
        assert count == 0

    def test_cleanup_org_isolation(self, db_session, event_service: EventService):
        event = StreamEvent(
            event_type="screen.company.created",
            source="screen",
            folder_id="folder-abc",
            payload={},
            organization_id="other-org",
        )
        db_session.add(event)
        db_session.commit()

        cutoff = datetime.now(UTC) + timedelta(hours=1)
        count = event_service.cleanup_events(cutoff, ORG_ID)
        assert count == 0
