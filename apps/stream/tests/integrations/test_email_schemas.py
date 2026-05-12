"""Tests for the email integration DTOs (ADR-0020 §Sender Authentication).

These tests lock down the schema-layer rejection of caller-supplied sender
fields. The whole point of the seam is that NP6 controls the From address
end-to-end; allowing a caller to override it would defeat SPF / DKIM /
DMARC alignment and burn shared NP6 IP reputation.
"""

from datetime import UTC, datetime

import pytest
from pydantic import ValidationError

from app.integrations.email import (
    EmailDeliveryEvent,
    EmailSendRequest,
    EmailSendResult,
)


class TestEmailSendRequestForbidsSenderFields:
    """Defense-in-depth: caller cannot influence the From address."""

    @pytest.mark.parametrize(
        "forbidden_field",
        ["from", "sender", "reply_to", "From", "Sender", "Reply-To"],
    )
    def test_forbidden_sender_field_rejected(self, forbidden_field: str):
        with pytest.raises(ValidationError) as excinfo:
            EmailSendRequest.model_validate(
                {
                    "subject": "Hello",
                    "html": "<p>hi</p>",
                    "recipients": ["alice@example.com"],
                    forbidden_field: "attacker@evil.example",
                }
            )
        assert "extra" in str(excinfo.value).lower() or "forbidden" in str(excinfo.value).lower()

    def test_unknown_extra_field_rejected(self):
        """Any extra field is rejected — covers future-creep attempts."""
        with pytest.raises(ValidationError):
            EmailSendRequest.model_validate(
                {
                    "subject": "Hello",
                    "html": "<p>hi</p>",
                    "recipients": ["alice@example.com"],
                    "raw_mjml": "<mj-text>...</mj-text>",
                }
            )


class TestEmailSendRequestValidPayloads:
    def test_minimal_valid_request(self):
        req = EmailSendRequest(
            subject="Hello",
            html="<p>body</p>",
            recipients=["alice@example.com"],
        )
        assert req.subject == "Hello"
        assert req.recipients == ["alice@example.com"]
        assert req.list_unsubscribe is None
        assert req.tags == {}

    def test_round_trip_serialization(self):
        req = EmailSendRequest(
            subject="Digest",
            html="<p>body</p>",
            recipients=["a@example.com", "b@example.com"],
            list_unsubscribe="https://chapsmind.fr/u?token=abc",
            tags={"stream_id": "42", "batch_id": "b1"},
        )
        rebuilt = EmailSendRequest.model_validate(req.model_dump())
        assert rebuilt == req

    def test_recipients_min_one(self):
        with pytest.raises(ValidationError):
            EmailSendRequest(subject="x", html="<p/>", recipients=[])

    def test_subject_must_be_non_empty(self):
        with pytest.raises(ValidationError):
            EmailSendRequest(subject="", html="<p/>", recipients=["a@example.com"])

    def test_html_must_be_non_empty(self):
        with pytest.raises(ValidationError):
            EmailSendRequest(subject="x", html="", recipients=["a@example.com"])

    def test_recipients_must_be_valid_emails(self):
        with pytest.raises(ValidationError):
            EmailSendRequest(
                subject="x",
                html="<p/>",
                recipients=["not-an-email"],
            )

    def test_request_is_frozen(self):
        req = EmailSendRequest(subject="x", html="<p/>", recipients=["a@example.com"])
        with pytest.raises(ValidationError):
            req.subject = "tampered"  # type: ignore[misc]


class TestEmailSendResult:
    def test_success_result_round_trip(self):
        result = EmailSendResult(
            success=True,
            message_id="np6-msg-123",
            provider_response={"id": "np6-msg-123", "status": "queued"},
        )
        assert EmailSendResult.model_validate(result.model_dump()) == result

    def test_failure_result_carries_error(self):
        result = EmailSendResult(success=False, error="connection refused")
        assert not result.success
        assert result.error == "connection refused"
        assert result.message_id is None


class TestEmailDeliveryEvent:
    @pytest.mark.parametrize(
        "event_type",
        [
            "delivered",
            "bounce_hard",
            "bounce_soft",
            "complaint",
            "open",
            "click",
            "unsubscribe",
        ],
    )
    def test_each_event_type_accepted(self, event_type: str):
        event = EmailDeliveryEvent(
            event_id="evt-1",
            message_id="msg-1",
            event_type=event_type,  # type: ignore[arg-type]
            email="alice@example.com",
            timestamp=datetime.now(UTC),
        )
        assert event.event_type == event_type

    def test_message_id_optional(self):
        """Unsubscribe / generic events may not carry a message id."""
        event = EmailDeliveryEvent(
            event_id="evt-1",
            message_id=None,
            event_type="unsubscribe",
            email="alice@example.com",
            timestamp=datetime.now(UTC),
        )
        assert event.message_id is None

    def test_unknown_event_type_rejected(self):
        with pytest.raises(ValidationError):
            EmailDeliveryEvent(
                event_id="evt-1",
                message_id="msg-1",
                event_type="exploded",  # type: ignore[arg-type]
                email="alice@example.com",
                timestamp=datetime.now(UTC),
            )
