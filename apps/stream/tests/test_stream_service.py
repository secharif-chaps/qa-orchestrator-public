"""Unit tests for StreamService."""

import pytest

from app.models.stream import ChannelType, StreamMode, StreamStatus
from app.schemas.stream import StreamCreate, StreamUpdate
from app.services.stream_service import StreamService, StreamServiceError

ORG_ID = "test-org-123"
FOLDER_ID = "folder-abc"


def _make_stream_create(**overrides) -> StreamCreate:
    defaults = {
        "name": "Test Stream",
        "channel_type": ChannelType.WEBHOOK,
        "channel_config": {"url": "https://example.com/hook", "headers": {}, "secret": None},
        "mode": StreamMode.LIVE,
        "subscribed_events": ["screen.company.created"],
    }
    defaults.update(overrides)
    return StreamCreate(**defaults)


class TestStreamServiceCreate:
    def test_create_stream(self, stream_service: StreamService):
        data = _make_stream_create()
        stream = stream_service.create_stream(data, FOLDER_ID, ORG_ID, "user-1", "testuser")

        assert stream.id is not None
        assert stream.name == "Test Stream"
        assert stream.folder_id == FOLDER_ID
        assert stream.organization_id == ORG_ID
        assert stream.owner_id == "user-1"
        assert stream.status == StreamStatus.ACTIVE

    def test_create_stream_invalid_channel_config_rejected_by_schema(self):
        """Invalid channel config is now rejected at schema level (StreamCreate validator)."""
        from pydantic import ValidationError

        with pytest.raises(ValidationError, match="channel_config is invalid"):
            _make_stream_create(channel_config={"bad": "config"})

    def test_create_teams_stream(self, stream_service: StreamService):
        data = _make_stream_create(
            channel_type=ChannelType.TEAMS,
            channel_config={"workflow_url": "https://teams.example.com/hook"},
        )
        stream = stream_service.create_stream(data, FOLDER_ID, ORG_ID, "user-1", "testuser")
        assert stream.channel_type == ChannelType.TEAMS

    def test_create_slack_stream(self, stream_service: StreamService):
        data = _make_stream_create(
            channel_type=ChannelType.SLACK_WEBHOOK,
            channel_config={"webhook_url": "https://hooks.slack.com/services/xxx"},
        )
        stream = stream_service.create_stream(data, FOLDER_ID, ORG_ID, "user-1", "testuser")
        assert stream.channel_type == ChannelType.SLACK_WEBHOOK


class TestStreamServiceGet:
    def test_get_stream(self, stream_service: StreamService):
        data = _make_stream_create()
        created = stream_service.create_stream(data, FOLDER_ID, ORG_ID, "user-1", "testuser")
        fetched = stream_service.get_stream(created.id, ORG_ID)
        assert fetched.id == created.id

    def test_get_stream_not_found(self, stream_service: StreamService):
        with pytest.raises(StreamServiceError, match="not found"):
            stream_service.get_stream(999, ORG_ID)

    def test_get_stream_wrong_org(self, stream_service: StreamService):
        data = _make_stream_create()
        created = stream_service.create_stream(data, FOLDER_ID, ORG_ID, "user-1", "testuser")
        with pytest.raises(StreamServiceError, match="not found"):
            stream_service.get_stream(created.id, "other-org")


class TestStreamServiceList:
    def test_list_streams_empty(self, stream_service: StreamService):
        streams, total = stream_service.list_streams(ORG_ID, FOLDER_ID)
        assert streams == []
        assert total == 0

    def test_list_streams_with_data(self, stream_service: StreamService):
        for i in range(3):
            data = _make_stream_create(name=f"Stream {i}")
            stream_service.create_stream(data, FOLDER_ID, ORG_ID, "user-1", "testuser")

        streams, total = stream_service.list_streams(ORG_ID, FOLDER_ID)
        assert total == 3
        assert len(streams) == 3

    def test_list_streams_pagination(self, stream_service: StreamService):
        for i in range(5):
            data = _make_stream_create(name=f"Stream {i}")
            stream_service.create_stream(data, FOLDER_ID, ORG_ID, "user-1", "testuser")

        streams, total = stream_service.list_streams(ORG_ID, FOLDER_ID, offset=0, limit=2)
        assert total == 5
        assert len(streams) == 2

    def test_list_streams_folder_isolation(self, stream_service: StreamService):
        stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        stream_service.create_stream(_make_stream_create(), "other-folder", ORG_ID, "user-1", "testuser")

        streams, total = stream_service.list_streams(ORG_ID, FOLDER_ID)
        assert total == 1

    def test_list_streams_org_isolation(self, stream_service: StreamService):
        stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        stream_service.create_stream(_make_stream_create(), FOLDER_ID, "other-org", "user-1", "testuser")

        streams, total = stream_service.list_streams(ORG_ID, FOLDER_ID)
        assert total == 1


class TestStreamServiceUpdate:
    def test_update_stream(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        update = StreamUpdate(name="Updated Name")
        updated = stream_service.update_stream(created.id, update, ORG_ID)
        assert updated.name == "Updated Name"

    def test_update_archived_stream_fails(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        stream_service.update_status(created.id, StreamStatus.ARCHIVED, ORG_ID)

        with pytest.raises(StreamServiceError, match="archived"):
            stream_service.update_stream(created.id, StreamUpdate(name="Fail"), ORG_ID)

    def test_update_channel_config_validates(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        update = StreamUpdate(channel_config={"bad": "config"})
        with pytest.raises(StreamServiceError, match="Invalid channel config"):
            stream_service.update_stream(created.id, update, ORG_ID)

    def test_update_stream_wrong_org(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        with pytest.raises(StreamServiceError, match="not found"):
            stream_service.update_stream(created.id, StreamUpdate(name="Hacked"), "other-org")


class TestStreamServiceDelete:
    def test_delete_stream(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        stream_service.delete_stream(created.id, ORG_ID)
        with pytest.raises(StreamServiceError, match="not found"):
            stream_service.get_stream(created.id, ORG_ID)

    def test_delete_stream_not_found(self, stream_service: StreamService):
        with pytest.raises(StreamServiceError, match="not found"):
            stream_service.delete_stream(999, ORG_ID)

    def test_delete_stream_wrong_org(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        with pytest.raises(StreamServiceError, match="not found"):
            stream_service.delete_stream(created.id, "other-org")


class TestStreamServiceStatusTransitions:
    def test_active_to_paused(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        updated = stream_service.update_status(created.id, StreamStatus.PAUSED, ORG_ID)
        assert updated.status == StreamStatus.PAUSED

    def test_active_to_archived(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        updated = stream_service.update_status(created.id, StreamStatus.ARCHIVED, ORG_ID)
        assert updated.status == StreamStatus.ARCHIVED

    def test_paused_to_active(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        stream_service.update_status(created.id, StreamStatus.PAUSED, ORG_ID)
        updated = stream_service.update_status(created.id, StreamStatus.ACTIVE, ORG_ID)
        assert updated.status == StreamStatus.ACTIVE

    def test_archived_is_terminal(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        stream_service.update_status(created.id, StreamStatus.ARCHIVED, ORG_ID)
        with pytest.raises(StreamServiceError, match="Cannot transition"):
            stream_service.update_status(created.id, StreamStatus.ACTIVE, ORG_ID)

    def test_paused_to_archived(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        stream_service.update_status(created.id, StreamStatus.PAUSED, ORG_ID)
        updated = stream_service.update_status(created.id, StreamStatus.ARCHIVED, ORG_ID)
        assert updated.status == StreamStatus.ARCHIVED

    def test_invalid_transition_active_to_active(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        with pytest.raises(StreamServiceError, match="Cannot transition"):
            stream_service.update_status(created.id, StreamStatus.ACTIVE, ORG_ID)

    def test_update_status_wrong_org(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        with pytest.raises(StreamServiceError, match="not found"):
            stream_service.update_status(created.id, StreamStatus.PAUSED, "other-org")


class TestStreamServiceDeliveries:
    def test_list_deliveries_empty(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        deliveries, total = stream_service.list_deliveries(created.id, ORG_ID)
        assert deliveries == []
        assert total == 0

    def test_list_deliveries_not_found(self, stream_service: StreamService):
        with pytest.raises(StreamServiceError, match="not found"):
            stream_service.list_deliveries(999, ORG_ID)

    def test_list_deliveries_wrong_org(self, stream_service: StreamService):
        created = stream_service.create_stream(_make_stream_create(), FOLDER_ID, ORG_ID, "user-1", "testuser")
        with pytest.raises(StreamServiceError, match="not found"):
            stream_service.list_deliveries(created.id, "other-org")
