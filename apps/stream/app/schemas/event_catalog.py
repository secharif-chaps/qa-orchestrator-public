from dataclasses import dataclass


@dataclass(frozen=True)
class EventCatalogEntry:
    """Immutable catalog entry describing a known event type."""

    event_type: str  # source.resource.action format
    source: str
    description: str
    available: bool  # Whether this event is currently implemented


EVENT_CATALOG: tuple[EventCatalogEntry, ...] = (
    # Screen module events (available)
    EventCatalogEntry(
        event_type="screen.company.created",
        source="screen",
        description="A new company card has been created",
        available=True,
    ),
    EventCatalogEntry(
        event_type="screen.company.updated",
        source="screen",
        description="A company card has been updated",
        available=True,
    ),
    # Target module events (not yet available)
    EventCatalogEntry(
        event_type="target.watchfile.created",
        source="target",
        description="A new watch file has been created",
        available=False,
    ),
    EventCatalogEntry(
        event_type="target.watchfile.updated",
        source="target",
        description="A watch file has been updated",
        available=False,
    ),
    EventCatalogEntry(
        event_type="target.alert.triggered",
        source="target",
        description="A monitoring alert has been triggered",
        available=False,
    ),
)


# Pre-computed frozenset of available event types for fast lookup
AVAILABLE_EVENT_TYPES: frozenset[str] = frozenset(
    entry.event_type for entry in EVENT_CATALOG if entry.available
)


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
