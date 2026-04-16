"""Shared formatting helpers for Slack and Teams adapters."""

from datetime import UTC, datetime

from app.core.config import settings
from app.models.event import StreamEvent
from app.schemas.event_catalog import SOURCE_LABELS, get_event_by_type

# French month abbreviations
_FRENCH_MONTHS = [
    "janv.",
    "févr.",
    "mars",
    "avr.",
    "mai",
    "juin",
    "juil.",
    "août",
    "sept.",
    "oct.",
    "nov.",
    "déc.",
]


def get_event_label(event_type: str) -> str:
    """Return the French label for an event type, falling back to the raw event_type."""
    entry = get_event_by_type(event_type)
    return entry.label if entry else event_type


def get_source_label(source: str) -> str:
    """Return the display name for a source (e.g. 'screen' -> 'Screen')."""
    return SOURCE_LABELS.get(source, source.capitalize())


def format_timestamp(dt: datetime | None = None) -> str:
    """Format a datetime as French-style: '01 avr. 2026 à 23:17'."""
    if dt is None:
        dt = datetime.now(UTC)
    day = f"{dt.day:02d}"
    month = _FRENCH_MONTHS[dt.month - 1]
    return f"{day} {month} {dt.year} à {dt.hour:02d}:{dt.minute:02d}"


def build_entity_url(event: StreamEvent) -> str | None:
    """Build a frontend URL to the entity, or None if not applicable."""
    if event.entity_type == "company" and event.entity_id and event.folder_id:
        base = settings.APP_BASE_URL.rstrip("/")
        return f"{base}/folders/{event.folder_id}/companies/{event.entity_id}"
    return None


def extract_payload_details(event: StreamEvent) -> list[tuple[str, str]]:
    """Extract user-relevant key-value pairs from the event payload.

    Returns a list of (label, value) tuples for display in notifications.
    Internal fields (company_id, action, website) are ignored.
    """
    details: list[tuple[str, str]] = []
    payload: dict = event.payload or {}  # type: ignore[assignment]

    company_name = payload.get("company_name")
    if company_name:
        details.append(("Entreprise", str(company_name)))

    if event.event_type in ("screen.company.created", "screen.company.updated"):
        success = payload.get("success_count")
        errors = payload.get("error_count")
        if success is not None or errors is not None:
            parts = []
            if success is not None:
                plural = int(success) != 1
                parts.append(f"{success} collecte{'s' if plural else ''} réussie{'s' if plural else ''}")
            if errors is not None and int(errors) > 0:
                parts.append(f"{errors} erreur{'s' if int(errors) != 1 else ''}")
            if parts:
                details.append(("Résultat", ", ".join(parts)))

    return details
