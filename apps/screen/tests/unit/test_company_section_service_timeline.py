"""Unit tests for save_timeline_data / get_timeline_data filtering of empty events.

TAR-1625: the LangGraph timeline agent occasionally emits events with only a
date filled in. These render as empty cards in the UI, so they must be
filtered out — both on write (next task run cleans the DB) and on read
(legacy rows already persisted disappear immediately).
"""

from __future__ import annotations

from unittest.mock import MagicMock

from app.models.company_children import CompanyTimelineEvent
from app.models.company_sections import CompanyTimeline
from app.services.company_section_service import (
    get_timeline_data,
    save_timeline_data,
)


def _make_db_with_no_existing_section() -> MagicMock:
    """Build a MagicMock session where first() returns None and all() returns []."""
    db = MagicMock()
    query = db.query.return_value
    query.filter.return_value.first.return_value = None
    query.filter.return_value.delete.return_value = 0
    query.filter.return_value.all.return_value = []
    return db


def _sourced(value: str, source: str = "https://example.com") -> dict:
    return {"value": value, "source": source}


# ---------------------------------------------------------------------------
# save_timeline_data — write-side filter
# ---------------------------------------------------------------------------


class TestSaveTimelineDataFiltersEmptyEvents:
    def test_drops_event_with_only_date(self):
        """Event with date but no title/description/impact must not be persisted."""
        db = _make_db_with_no_existing_section()
        data = {
            "insights": "Some insights",
            "events": [
                {"date": _sourced("2012-01-01")},
                {"date": _sourced("2020-03-11")},
            ],
        }

        save_timeline_data(db, company_id=8, data=data)

        added_events = [c.args[0] for c in db.add.call_args_list if isinstance(c.args[0], CompanyTimelineEvent)]
        assert added_events == []

    def test_keeps_event_with_only_title(self):
        db = _make_db_with_no_existing_section()
        data = {
            "events": [
                {"date": _sourced("1959"), "title": _sourced("Founded")},
            ],
        }

        save_timeline_data(db, company_id=1, data=data)

        added_events = [c.args[0] for c in db.add.call_args_list if isinstance(c.args[0], CompanyTimelineEvent)]
        assert len(added_events) == 1
        assert added_events[0].title == "Founded"

    def test_keeps_event_with_only_description(self):
        db = _make_db_with_no_existing_section()
        data = {"events": [{"description": _sourced("Acquired by Acme")}]}

        save_timeline_data(db, company_id=1, data=data)

        added_events = [c.args[0] for c in db.add.call_args_list if isinstance(c.args[0], CompanyTimelineEvent)]
        assert len(added_events) == 1
        assert added_events[0].description == "Acquired by Acme"

    def test_keeps_event_with_only_impact(self):
        """Impact alone is enough — the UI renders an alert from it."""
        db = _make_db_with_no_existing_section()
        data = {"events": [{"impact": _sourced("Major market shift", source="Chaps-e")}]}

        save_timeline_data(db, company_id=1, data=data)

        added_events = [c.args[0] for c in db.add.call_args_list if isinstance(c.args[0], CompanyTimelineEvent)]
        assert len(added_events) == 1
        assert added_events[0].impact == "Major market shift"

    def test_mixed_list_partially_filtered(self):
        db = _make_db_with_no_existing_section()
        data = {
            "events": [
                {"date": _sourced("2012-01-01"), "title": _sourced("IPO")},
                {"date": _sourced("2020-03-11")},  # empty — dropped
                {"date": _sourced("1959"), "description": _sourced("Founded")},
                {"date": _sourced("2008")},  # empty — dropped
            ],
        }

        save_timeline_data(db, company_id=1, data=data)

        added_events = [c.args[0] for c in db.add.call_args_list if isinstance(c.args[0], CompanyTimelineEvent)]
        assert [e.title or e.description for e in added_events] == ["IPO", "Founded"]

    def test_treats_empty_string_fields_as_missing(self):
        """SourcedValue with value="" should be treated the same as missing."""
        db = _make_db_with_no_existing_section()
        data = {
            "events": [
                {
                    "date": _sourced("2020-03-11"),
                    "title": _sourced(""),
                    "description": _sourced(""),
                    "impact": _sourced(""),
                },
            ],
        }

        save_timeline_data(db, company_id=1, data=data)

        added_events = [c.args[0] for c in db.add.call_args_list if isinstance(c.args[0], CompanyTimelineEvent)]
        assert added_events == []


# ---------------------------------------------------------------------------
# get_timeline_data — read-side filter (defensive for legacy rows)
# ---------------------------------------------------------------------------


def _persisted_event(**fields: str | None) -> CompanyTimelineEvent:
    """Build a CompanyTimelineEvent the way SQLAlchemy would hand it back."""
    defaults = {
        "company_id": 1,
        "date": None,
        "date_source": None,
        "title": None,
        "title_source": None,
        "description": None,
        "description_source": None,
        "category": None,
        "category_source": None,
        "location": None,
        "location_source": None,
        "impact": None,
        "impact_source": None,
    }
    defaults.update(fields)
    return CompanyTimelineEvent(**defaults)


class TestGetTimelineDataFiltersEmptyEvents:
    def test_drops_legacy_event_with_only_date(self):
        db = MagicMock()
        timeline = CompanyTimeline(company_id=8, insights="Insights", insights_source="Chaps-e")
        # First filter().first() → CompanyTimeline; second filter().all() → events.
        db.query.return_value.filter.return_value.first.return_value = timeline
        db.query.return_value.filter.return_value.all.return_value = [
            _persisted_event(date="2012-01-01", date_source="https://x"),
            _persisted_event(date="1959", date_source="https://x", title="Founded", title_source="https://x"),
            _persisted_event(date="2020-03-11", date_source="https://x"),
        ]

        result = get_timeline_data(db, company_id=8)

        assert "events" in result
        assert len(result["events"]) == 1
        assert result["events"][0]["title"] == {"value": "Founded", "source": "https://x"}

    def test_omits_events_key_when_all_legacy_events_are_empty(self):
        db = MagicMock()
        timeline = CompanyTimeline(company_id=8, insights="Insights", insights_source="Chaps-e")
        db.query.return_value.filter.return_value.first.return_value = timeline
        db.query.return_value.filter.return_value.all.return_value = [
            _persisted_event(date="2012-01-01", date_source="https://x"),
            _persisted_event(date="2020-03-11", date_source="https://x"),
        ]

        result = get_timeline_data(db, company_id=8)

        assert "events" not in result
        assert result.get("insights") == "Insights"
