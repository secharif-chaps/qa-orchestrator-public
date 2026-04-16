"""Tests for OutboxService: emit, get_pending, mark_published.

Covers: transaction-safe event emission, pending retrieval, publish marking.
"""

from unittest.mock import MagicMock

import pytest
from sqlalchemy.orm import Session

from app.models.outbox import OutboxEvent
from app.services.outbox_service import OutboxService

# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------


@pytest.fixture
def mock_db():
    return MagicMock(spec=Session)


@pytest.fixture
def service(mock_db):
    return OutboxService(mock_db)


# ---------------------------------------------------------------------------
# emit
# ---------------------------------------------------------------------------


class TestEmit:
    def test_creates_event_and_adds_to_session(self, service, mock_db):
        event = service.emit(
            event_type="screen.company.created",
            aggregate_type="company",
            aggregate_id="42",
            organization_id="org-uuid-123",
            payload={"company_id": 42, "company_name": "Acme"},
            summary="Fiche entreprise Acme créée",
        )

        assert isinstance(event, OutboxEvent)
        assert event.event_type == "screen.company.created"
        assert event.aggregate_type == "company"
        assert event.aggregate_id == "42"
        assert event.organization_id == "org-uuid-123"
        assert event.payload == {"company_id": 42, "company_name": "Acme"}
        assert event.summary == "Fiche entreprise Acme créée"
        mock_db.add.assert_called_once_with(event)

    def test_does_not_commit(self, service, mock_db):
        """emit() must NOT commit — the caller controls the transaction."""
        service.emit(
            event_type="screen.company.deleted",
            aggregate_type="company",
            aggregate_id="1",
            organization_id="org-1",
        )

        mock_db.commit.assert_not_called()

    def test_defaults_payload_to_empty_dict(self, service, mock_db):
        event = service.emit(
            event_type="screen.company.updated",
            aggregate_type="company",
            aggregate_id="5",
            organization_id="org-2",
        )

        assert event.payload == {}

    def test_folder_id_is_optional(self, service, mock_db):
        event = service.emit(
            event_type="screen.company.updated",
            aggregate_type="company",
            aggregate_id="10",
            organization_id="org-3",
            folder_id="folder-abc",
        )

        assert event.folder_id == "folder-abc"

    def test_folder_id_defaults_to_none(self, service, mock_db):
        event = service.emit(
            event_type="screen.company.created",
            aggregate_type="company",
            aggregate_id="10",
            organization_id="org-3",
        )

        assert event.folder_id is None


# ---------------------------------------------------------------------------
# get_pending
# ---------------------------------------------------------------------------


class TestGetPending:
    def test_returns_unpublished_events(self, service, mock_db):
        mock_events = [MagicMock(spec=OutboxEvent), MagicMock(spec=OutboxEvent)]
        mock_db.query.return_value.filter.return_value.order_by.return_value.limit.return_value.all.return_value = (
            mock_events
        )

        result = service.get_pending()

        assert result == mock_events
        mock_db.query.assert_called_once_with(OutboxEvent)

    def test_respects_limit(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.order_by.return_value.limit.return_value.all.return_value = []

        service.get_pending(limit=50)

        mock_db.query.return_value.filter.return_value.order_by.return_value.limit.assert_called_with(50)

    def test_returns_empty_when_none_pending(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.order_by.return_value.limit.return_value.all.return_value = []

        result = service.get_pending()

        assert result == []


# ---------------------------------------------------------------------------
# mark_published
# ---------------------------------------------------------------------------


class TestMarkPublished:
    def test_updates_events_and_commits(self, service, mock_db):
        service.mark_published([1, 2, 3])

        mock_db.query.return_value.filter.return_value.update.assert_called_once()
        mock_db.commit.assert_called_once()

    def test_no_op_for_empty_list(self, service, mock_db):
        service.mark_published([])

        mock_db.query.assert_not_called()
        mock_db.commit.assert_not_called()
