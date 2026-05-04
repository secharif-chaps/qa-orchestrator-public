"""Shared formatting helpers for Slack and Teams adapters."""

from datetime import UTC, datetime

from babel.dates import format_datetime

from app.constants.messages import (
    LABEL_COMPANY,
    LABEL_RESULT,
    NOTIFICATION_DATETIME_LOCALE,
    NOTIFICATION_DATETIME_PATTERN,
    format_errors,
    format_successful_collections,
)
from app.core.config import settings
from app.models.event import StreamEvent
from app.schemas.event_catalog import SOURCE_LABELS, get_event_by_type


def get_event_label(event_type: str) -> str:
    """Return the French label for an event type, falling back to the raw event_type."""
    entry = get_event_by_type(event_type)
    return entry.label if entry else event_type


def get_source_label(source: str) -> str:
    """Return the display name for a source (e.g. 'screen' -> 'Screen')."""
    return SOURCE_LABELS.get(source, source.capitalize())


def format_timestamp(dt: datetime | None = None) -> str:
    """Format a datetime for notifications using French locale (e.g. ``01 avr. 2026 à 23:17``)."""
    if dt is None:
        dt = datetime.now(UTC)
    return format_datetime(dt, NOTIFICATION_DATETIME_PATTERN, locale=NOTIFICATION_DATETIME_LOCALE)


def build_entity_url(event: StreamEvent) -> str | None:
    """Build a frontend URL to the entity, or None if not applicable."""
    if event.entity_type == "company" and event.entity_id and event.folder_id:
        base = settings.APP_BASE_URL.rstrip("/")
        return f"{base}/folders/{event.folder_id}/companies/{event.entity_id}"
    return None


def _build_result_summary(payload: dict) -> str | None:
    """Return a comma-joined success/error summary, or None if no counters are set."""
    success = payload.get("success_count")
    errors = payload.get("error_count")
    if success is None and errors is None:
        return None

    parts: list[str] = []
    if success is not None:
        parts.append(format_successful_collections(int(success)))
    if errors is not None and int(errors) > 0:
        parts.append(format_errors(int(errors)))
    return ", ".join(parts) if parts else None


def extract_payload_details(event: StreamEvent) -> list[tuple[str, str]]:
    """Extract user-relevant key-value pairs from the event payload.

    Returns a list of (label, value) tuples for display in notifications.
    Internal fields (company_id, action, website) are ignored.
    """
    details: list[tuple[str, str]] = []
    payload: dict = event.payload or {}  # type: ignore[assignment]

    company_name = payload.get("company_name")
    if company_name:
        details.append((LABEL_COMPANY, str(company_name)))

    if event.event_type in ("screen.company.created", "screen.company.updated"):
        result_summary = _build_result_summary(payload)
        if result_summary:
            details.append((LABEL_RESULT, result_summary))

    return details
