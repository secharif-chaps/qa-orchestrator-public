from collections import OrderedDict
from dataclasses import dataclass

from pydantic import BaseModel

from app.constants.messages import (
    EVENT_LABEL_ALERT_TRIGGERED,
    EVENT_LABEL_COMPANY_CREATED,
    EVENT_LABEL_COMPANY_UPDATED,
    EVENT_LABEL_WATCHFILE_CREATED,
    EVENT_LABEL_WATCHFILE_UPDATED,
    SOURCE_LABEL_EXPLORE,
    SOURCE_LABEL_SCREEN,
    SOURCE_LABEL_TARGET,
)


@dataclass(frozen=True)
class EventCatalogEntry:
    """Immutable catalog entry describing a known event type."""

    event_type: str  # source.resource.action format
    source: str
    description: str
    label: str  # French label for frontend display
    available: bool  # Whether this event is currently implemented


EVENT_CATALOG: tuple[EventCatalogEntry, ...] = (
    # Screen module events (available)
    EventCatalogEntry(
        event_type="screen.company.created",
        source="screen",
        description="A new company card has been created",
        label=EVENT_LABEL_COMPANY_CREATED,
        available=True,
    ),
    EventCatalogEntry(
        event_type="screen.company.updated",
        source="screen",
        description="A company card has been updated",
        label=EVENT_LABEL_COMPANY_UPDATED,
        available=True,
    ),
    # Target module events (not yet available)
    EventCatalogEntry(
        event_type="target.watchfile.created",
        source="target",
        description="A new watchfile has been created",
        label=EVENT_LABEL_WATCHFILE_CREATED,
        available=False,
    ),
    EventCatalogEntry(
        event_type="target.watchfile.updated",
        source="target",
        description="A watchfile has been updated",
        label=EVENT_LABEL_WATCHFILE_UPDATED,
        available=False,
    ),
    EventCatalogEntry(
        event_type="target.alert.triggered",
        source="target",
        description="A monitoring alert has been triggered",
        label=EVENT_LABEL_ALERT_TRIGGERED,
        available=False,
    ),
)


# Pre-computed frozenset of available event types for fast lookup
AVAILABLE_EVENT_TYPES: frozenset[str] = frozenset(entry.event_type for entry in EVENT_CATALOG if entry.available)


def get_available_events() -> list[EventCatalogEntry]:
    """Return only currently available (implemented) events."""
    return [entry for entry in EVENT_CATALOG if entry.available]


def get_event_by_type(event_type: str) -> EventCatalogEntry | None:
    """Look up a catalog entry by its event_type string."""
    for entry in EVENT_CATALOG:
        if entry.event_type == event_type:
            return entry
    return None


def is_valid_event_type(event_type: str) -> bool:
    """Check if an event type exists in the catalog (available or not)."""
    return any(entry.event_type == event_type for entry in EVENT_CATALOG)


# --- Grouped catalog models for API response ---


class EventTypeEntry(BaseModel):
    type: str
    label: str


class EventSourceGroup(BaseModel):
    source: str
    label: str
    available: bool
    events: list[EventTypeEntry]


# Source label mapping
SOURCE_LABELS: dict[str, str] = {
    "screen": SOURCE_LABEL_SCREEN,
    "target": SOURCE_LABEL_TARGET,
    "explore": SOURCE_LABEL_EXPLORE,
}


def get_grouped_catalog() -> list[EventSourceGroup]:
    """Return the event catalog grouped by source, for the API response."""
    groups: OrderedDict[str, list[EventCatalogEntry]] = OrderedDict()
    for entry in EVENT_CATALOG:
        groups.setdefault(entry.source, []).append(entry)

    result = []
    for source, entries in groups.items():
        result.append(
            EventSourceGroup(
                source=source,
                label=SOURCE_LABELS.get(source, source.capitalize()),
                available=all(e.available for e in entries),
                events=[EventTypeEntry(type=e.event_type, label=e.label) for e in entries],
            )
        )
    return result
