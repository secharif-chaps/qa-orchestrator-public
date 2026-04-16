"""Integration tests for OutboxService using real PostgreSQL session.

Covers: emit + get_pending round-trip, mark_published, event ordering.
"""

from app.models.outbox import OutboxEvent
from app.services.outbox_service import OutboxService


class TestOutboxServiceIntegration:
    """Tests using real PostgreSQL session from integration conftest."""

    def test_emit_and_get_pending(self, db_session):
        service = OutboxService(db_session)

        service.emit(
            event_type="screen.company.created",
            aggregate_type="company",
            aggregate_id="100",
            organization_id="org-test-1",
            payload={"company_id": 100},
            summary="Test company created",
        )
        db_session.commit()

        pending = service.get_pending()
        assert len(pending) == 1
        assert pending[0].event_type == "screen.company.created"
        assert pending[0].aggregate_id == "100"
        assert pending[0].published_at is None

    def test_mark_published_sets_timestamp(self, db_session):
        service = OutboxService(db_session)

        service.emit(
            event_type="screen.company.deleted",
            aggregate_type="company",
            aggregate_id="200",
            organization_id="org-test-2",
        )
        db_session.commit()

        pending = service.get_pending()
        assert len(pending) == 1
        event_id = pending[0].id

        service.mark_published([event_id])

        # Re-query to verify
        remaining = service.get_pending()
        assert len(remaining) == 0

        # Verify the event was updated, not deleted
        published = db_session.query(OutboxEvent).filter(OutboxEvent.id == event_id).first()
        assert published is not None
        assert published.published_at is not None

    def test_multiple_events_ordering(self, db_session):
        service = OutboxService(db_session)

        service.emit(
            event_type="screen.company.created",
            aggregate_type="company",
            aggregate_id="1",
            organization_id="org-test",
        )
        service.emit(
            event_type="screen.company.deleted",
            aggregate_type="company",
            aggregate_id="2",
            organization_id="org-test",
        )
        db_session.commit()

        pending = service.get_pending()
        assert len(pending) == 2
        # Should be ordered by created_at
        assert pending[0].aggregate_id == "1"
        assert pending[1].aggregate_id == "2"
