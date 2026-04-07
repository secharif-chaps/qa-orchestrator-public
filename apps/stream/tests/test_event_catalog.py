"""Tests for the event catalog module."""

import pytest

from app.schemas.event_catalog import (
    AVAILABLE_EVENT_TYPES,
    EVENT_CATALOG,
    EventCatalogEntry,
    get_available_events,
    get_event_by_type,
    get_grouped_catalog,
    is_valid_event_type,
)


class TestEventCatalog:
    def test_catalog_has_5_entries(self):
        assert len(EVENT_CATALOG) == 5

    def test_2_available_events(self):
        available = get_available_events()
        assert len(available) == 2

    def test_3_unavailable_events(self):
        unavailable = [e for e in EVENT_CATALOG if not e.available]
        assert len(unavailable) == 3

    def test_available_events_are_screen(self):
        """All currently available events come from the screen module."""
        for entry in get_available_events():
            assert entry.source == "screen"

    def test_unavailable_events_are_target(self):
        """All unavailable events come from the target module."""
        unavailable = [e for e in EVENT_CATALOG if not e.available]
        for entry in unavailable:
            assert entry.source == "target"

    def test_naming_convention(self):
        """All event types follow source.resource.action format."""
        for entry in EVENT_CATALOG:
            parts = entry.event_type.split(".")
            assert len(parts) == 3, f"Bad format: {entry.event_type}"
            assert parts[0] == entry.source, f"Source mismatch: {entry.event_type}"

    def test_all_entries_have_labels(self):
        """All catalog entries have a non-empty French label."""
        for entry in EVENT_CATALOG:
            assert entry.label, f"Missing label for {entry.event_type}"
            assert isinstance(entry.label, str)


class TestEventCatalogEntry:
    def test_frozen_dataclass(self):
        """EventCatalogEntry is immutable."""
        entry = EventCatalogEntry(
            event_type="test.resource.action",
            source="test",
            description="Test event",
            label="Test label",
            available=True,
        )
        with pytest.raises(AttributeError):
            entry.event_type = "modified"

    def test_catalog_entries_immutable(self):
        """Catalog entries cannot be modified in place."""
        entry = EVENT_CATALOG[0]
        with pytest.raises(AttributeError):
            entry.available = False


class TestLookupHelpers:
    def test_get_event_by_type_found(self):
        entry = get_event_by_type("screen.company.created")
        assert entry is not None
        assert entry.source == "screen"
        assert entry.available is True

    def test_get_event_by_type_not_found(self):
        assert get_event_by_type("nonexistent.event.type") is None

    def test_is_valid_event_type_true(self):
        assert is_valid_event_type("screen.company.created") is True

    def test_is_valid_event_type_unavailable_still_valid(self):
        """Unavailable events are still valid (they exist in catalog)."""
        assert is_valid_event_type("target.watchfile.created") is True

    def test_is_valid_event_type_false(self):
        assert is_valid_event_type("unknown.resource.action") is False


class TestAvailableEventTypes:
    def test_frozenset_type(self):
        assert isinstance(AVAILABLE_EVENT_TYPES, frozenset)

    def test_contains_2_types(self):
        assert len(AVAILABLE_EVENT_TYPES) == 2

    def test_membership_check(self):
        assert "screen.company.created" in AVAILABLE_EVENT_TYPES
        assert "screen.company.updated" in AVAILABLE_EVENT_TYPES
        assert "target.watchfile.created" not in AVAILABLE_EVENT_TYPES

    def test_frozenset_immutable(self):
        with pytest.raises(AttributeError):
            AVAILABLE_EVENT_TYPES.add("hacked.event.type")


class TestGroupedCatalog:
    def test_grouped_catalog_returns_list(self):
        groups = get_grouped_catalog()
        assert isinstance(groups, list)

    def test_grouped_catalog_has_2_sources(self):
        """Catalog groups into screen and target sources."""
        groups = get_grouped_catalog()
        assert len(groups) == 2

    def test_grouped_catalog_source_names(self):
        groups = get_grouped_catalog()
        sources = [g.source for g in groups]
        assert "screen" in sources
        assert "target" in sources

    def test_grouped_catalog_source_labels(self):
        groups = get_grouped_catalog()
        label_map = {g.source: g.label for g in groups}
        assert label_map["screen"] == "Screen"
        assert label_map["target"] == "Target"

    def test_grouped_catalog_screen_events(self):
        groups = get_grouped_catalog()
        screen_group = next(g for g in groups if g.source == "screen")
        assert len(screen_group.events) == 2
        assert screen_group.available is True

    def test_grouped_catalog_target_events(self):
        groups = get_grouped_catalog()
        target_group = next(g for g in groups if g.source == "target")
        assert len(target_group.events) == 3
        assert target_group.available is False

    def test_grouped_catalog_event_entries_have_type_and_label(self):
        groups = get_grouped_catalog()
        for group in groups:
            for event in group.events:
                assert event.type, "Event type must be non-empty"
                assert event.label, "Event label must be non-empty"
