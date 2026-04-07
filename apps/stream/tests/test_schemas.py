"""Tests for Stream service Pydantic schemas."""

import pytest
from pydantic import ValidationError

from app.models.delivery import DeliveryStatus
from app.models.stream import ChannelType, StreamMode, StreamStatus
from app.schemas.delivery import DeliveryRead
from app.schemas.event import EventIngest, EventRead
from app.schemas.stream import StreamCreate, StreamRead, StreamStatusUpdate, StreamUpdate


class TestStreamCreate:
    def test_valid_live_stream(self):
        schema = StreamCreate(
            name="My Stream",
            channel_type=ChannelType.TEAMS,
            channel_config={"webhook_url": "https://example.com/webhook"},
            mode=StreamMode.LIVE,
        )
        assert schema.name == "My Stream"
        assert schema.cron_expression is None

    def test_valid_recurrence_stream(self):
        schema = StreamCreate(
            name="Weekly Digest",
            channel_type=ChannelType.SLACK_WEBHOOK,
            channel_config={"webhook_url": "https://hooks.slack.com/test"},
            mode=StreamMode.RECURRENCE,
            cron_expression="0 9 * * MON",
        )
        assert schema.cron_expression == "0 9 * * MON"

    def test_recurrence_without_cron_fails(self):
        with pytest.raises(ValidationError, match="cron_expression is required"):
            StreamCreate(
                name="Bad Stream",
                channel_type=ChannelType.TEAMS,
                channel_config={},
                mode=StreamMode.RECURRENCE,
            )

    def test_live_with_cron_fails(self):
        with pytest.raises(ValidationError, match="cron_expression is forbidden"):
            StreamCreate(
                name="Bad Stream",
                channel_type=ChannelType.TEAMS,
                channel_config={},
                mode=StreamMode.LIVE,
                cron_expression="0 9 * * MON",
            )

    def test_empty_name_fails(self):
        with pytest.raises(ValidationError):
            StreamCreate(
                name="",
                channel_type=ChannelType.TEAMS,
                channel_config={},
                mode=StreamMode.LIVE,
            )

    def test_subscribed_events_default(self):
        schema = StreamCreate(
            name="Test",
            channel_type=ChannelType.WEBHOOK,
            channel_config={"url": "https://example.com"},
            mode=StreamMode.LIVE,
        )
        assert schema.subscribed_events == []

    def test_subscribed_events_with_values(self):
        schema = StreamCreate(
            name="Test",
            channel_type=ChannelType.WEBHOOK,
            channel_config={"url": "https://example.com"},
            mode=StreamMode.LIVE,
            subscribed_events=["screen.company.created", "screen.company.updated"],
        )
        assert len(schema.subscribed_events) == 2

    def test_subscribed_events_invalid_format_fails(self):
        with pytest.raises(ValidationError, match="source.resource.action"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.WEBHOOK,
                channel_config={"url": "https://example.com"},
                mode=StreamMode.LIVE,
                subscribed_events=["garbage"],
            )

    def test_subscribed_events_two_parts_fails(self):
        with pytest.raises(ValidationError, match="source.resource.action"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.WEBHOOK,
                channel_config={"url": "https://example.com"},
                mode=StreamMode.LIVE,
                subscribed_events=["screen.company"],
            )

    def test_invalid_channel_config_for_teams_fails(self):
        with pytest.raises(ValidationError, match="channel_config is invalid"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.TEAMS,
                channel_config={"bad_key": "not_a_url"},
                mode=StreamMode.LIVE,
            )

    def test_valid_channel_config_for_webhook(self):
        schema = StreamCreate(
            name="Test",
            channel_type=ChannelType.WEBHOOK,
            channel_config={"url": "https://example.com/hook", "secret": "s3cret"},
            mode=StreamMode.LIVE,
        )
        assert schema.channel_config["secret"] == "s3cret"

    # --- Channel config validation per type ---

    def test_teams_missing_webhook_url_fails(self):
        with pytest.raises(ValidationError, match="channel_config is invalid"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.TEAMS,
                channel_config={},
                mode=StreamMode.LIVE,
            )

    def test_teams_invalid_url_fails(self):
        with pytest.raises(ValidationError, match="channel_config is invalid"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.TEAMS,
                channel_config={"webhook_url": "not-a-url"},
                mode=StreamMode.LIVE,
            )

    def test_slack_missing_webhook_url_fails(self):
        with pytest.raises(ValidationError, match="channel_config is invalid"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.SLACK_WEBHOOK,
                channel_config={},
                mode=StreamMode.LIVE,
            )

    def test_slack_invalid_url_fails(self):
        with pytest.raises(ValidationError, match="channel_config is invalid"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.SLACK_WEBHOOK,
                channel_config={"webhook_url": "not-a-url"},
                mode=StreamMode.LIVE,
            )

    def test_webhook_missing_url_fails(self):
        with pytest.raises(ValidationError, match="channel_config is invalid"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.WEBHOOK,
                channel_config={"headers": {}},
                mode=StreamMode.LIVE,
            )

    def test_webhook_invalid_url_fails(self):
        with pytest.raises(ValidationError, match="channel_config is invalid"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.WEBHOOK,
                channel_config={"url": "not-a-url"},
                mode=StreamMode.LIVE,
            )

    def test_webhook_with_custom_headers(self):
        schema = StreamCreate(
            name="Test",
            channel_type=ChannelType.WEBHOOK,
            channel_config={"url": "https://example.com", "headers": {"Authorization": "Bearer tok"}},
            mode=StreamMode.LIVE,
        )
        assert schema.channel_config["headers"]["Authorization"] == "Bearer tok"

    # --- subscribed_events edge cases ---

    def test_subscribed_events_four_parts_fails(self):
        with pytest.raises(ValidationError, match="source.resource.action"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.WEBHOOK,
                channel_config={"url": "https://example.com"},
                mode=StreamMode.LIVE,
                subscribed_events=["a.b.c.d"],
            )

    def test_subscribed_events_whitespace_parts_fails(self):
        with pytest.raises(ValidationError, match="source.resource.action"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.WEBHOOK,
                channel_config={"url": "https://example.com"},
                mode=StreamMode.LIVE,
                subscribed_events=[" . . "],
            )

    def test_subscribed_events_empty_string_fails(self):
        with pytest.raises(ValidationError, match="source.resource.action"):
            StreamCreate(
                name="Test",
                channel_type=ChannelType.WEBHOOK,
                channel_config={"url": "https://example.com"},
                mode=StreamMode.LIVE,
                subscribed_events=[""],
            )


class TestStreamUpdate:
    def test_partial_update(self):
        schema = StreamUpdate(name="New Name")
        assert schema.name == "New Name"
        assert schema.description is None

    def test_empty_update(self):
        schema = StreamUpdate()
        assert schema.name is None
        assert schema.channel_config is None

    def test_cron_expression_update(self):
        """StreamUpdate allows cron_expression without mode — validation is service-side."""
        schema = StreamUpdate(cron_expression="0 9 * * MON")
        assert schema.cron_expression == "0 9 * * MON"

    def test_name_too_long_fails(self):
        with pytest.raises(ValidationError):
            StreamUpdate(name="x" * 256)

    def test_name_empty_string_fails(self):
        with pytest.raises(ValidationError):
            StreamUpdate(name="")

    def test_multiple_fields_update(self):
        schema = StreamUpdate(
            name="Updated Name",
            description="New description",
            subscribed_events=["screen.company.created"],
        )
        assert schema.name == "Updated Name"
        assert schema.description == "New description"
        assert schema.subscribed_events == ["screen.company.created"]


class TestStreamStatusUpdate:
    def test_status_update(self):
        schema = StreamStatusUpdate(status=StreamStatus.PAUSED)
        assert schema.status == StreamStatus.PAUSED


class TestStreamRead:
    def test_from_attributes(self):
        """Test StreamRead can be constructed from ORM-like attributes."""

        class FakeORM:
            id = 1
            name = "Test Stream"
            description = None
            folder_id = "folder-abc"
            channel_type = ChannelType.TEAMS
            channel_config = {"webhook_url": "https://example.com"}
            mode = StreamMode.LIVE
            cron_expression = None
            status = StreamStatus.ACTIVE
            organization_id = "org-123"
            owner_id = "user-123"
            owner_username = "testuser"
            subscribed_events = ["screen.company.created"]
            created_at = "2026-01-01T00:00:00Z"
            updated_at = "2026-01-01T00:00:00Z"

        schema = StreamRead.model_validate(FakeORM())
        assert schema.id == 1
        assert schema.name == "Test Stream"
        assert schema.folder_id == "folder-abc"


class TestEventIngest:
    def test_valid_event_type(self):
        schema = EventIngest(
            event_type="screen.company.created",
            payload={"id": 42},
            folder_id="folder-abc",
        )
        assert schema.event_type == "screen.company.created"

    def test_two_parts_fails(self):
        with pytest.raises(ValidationError, match="3 dot-separated parts"):
            EventIngest(event_type="screen.company", folder_id="folder-abc")

    def test_one_part_fails(self):
        with pytest.raises(ValidationError, match="3 dot-separated parts"):
            EventIngest(event_type="screencreated", folder_id="folder-abc")

    def test_four_parts_fails(self):
        with pytest.raises(ValidationError, match="3 dot-separated parts"):
            EventIngest(event_type="screen.company.created.extra", folder_id="folder-abc")

    def test_empty_parts_fails(self):
        with pytest.raises(ValidationError, match="non-empty"):
            EventIngest(event_type="screen..created", folder_id="folder-abc")

    def test_optional_fields_default(self):
        schema = EventIngest(event_type="screen.company.created", folder_id="folder-abc")
        assert schema.payload == {}
        assert schema.entity_id is None
        assert schema.summary is None

    def test_leading_dot_fails(self):
        with pytest.raises(ValidationError, match="non-empty"):
            EventIngest(event_type=".company.created", folder_id="folder-abc")

    def test_trailing_dot_fails(self):
        with pytest.raises(ValidationError, match="3 dot-separated parts"):
            EventIngest(event_type="screen.company.created.", folder_id="folder-abc")

    def test_whitespace_only_parts_fails(self):
        with pytest.raises(ValidationError, match="non-empty"):
            EventIngest(event_type="screen. .created", folder_id="folder-abc")

    def test_missing_folder_id_fails(self):
        with pytest.raises(ValidationError):
            EventIngest(event_type="screen.company.created")  # type: ignore[call-arg]

    def test_empty_folder_id_allowed(self):
        """Empty string is technically allowed — validation is service-side."""
        schema = EventIngest(event_type="screen.company.created", folder_id="")
        assert schema.folder_id == ""


class TestEventRead:
    def test_from_attributes(self):

        class FakeORM:
            id = 1
            event_type = "screen.company.created"
            folder_id = "folder-abc"
            source = "screen"
            payload = {"company_id": 42}
            organization_id = "org-123"
            is_deleted = False
            created_at = "2026-01-01T00:00:00Z"
            entity_id = "42"
            entity_type = "company"
            summary = "Company created"

        schema = EventRead.model_validate(FakeORM())
        assert schema.event_type == "screen.company.created"
        assert schema.is_deleted is False


class TestDeliveryRead:
    def test_from_attributes(self):

        class FakeORM:
            id = 1
            stream_id = 10
            event_id = 20
            status = DeliveryStatus.DELIVERED
            error_message = None
            response_metadata = {"status_code": 200}
            delivered_at = "2026-01-01T00:00:00Z"
            created_at = "2026-01-01T00:00:00Z"
            attempt_count = 1
            next_retry_at = None

        schema = DeliveryRead.model_validate(FakeORM())
        assert schema.stream_id == 10
        assert schema.status == DeliveryStatus.DELIVERED
        assert schema.attempt_count == 1
