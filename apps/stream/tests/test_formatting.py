"""Unit tests for shared formatting helpers."""

import re
from datetime import UTC, datetime
from unittest.mock import patch

from app.adapters.formatting import (
    build_entity_url,
    extract_payload_details,
    format_timestamp,
    get_event_label,
    get_source_label,
)
from app.models.event import StreamEvent


def _make_event(**overrides) -> StreamEvent:
    defaults = {
        "id": 1,
        "event_type": "screen.company.created",
        "source": "screen",
        "payload": {"company_name": "ChapsVision"},
        "organization_id": "org-1",
        "folder_id": "folder-abc",
        "entity_type": "company",
        "entity_id": "42",
        "summary": "Fiche entreprise ChapsVision créée",
    }
    defaults.update(overrides)
    return StreamEvent(**defaults)


class TestGetEventLabel:
    def test_known_type(self):
        assert get_event_label("screen.company.created") == "Fiche entreprise créée"

    def test_unknown_type(self):
        assert get_event_label("unknown.event.type") == "unknown.event.type"


class TestGetSourceLabel:
    def test_known_source(self):
        assert get_source_label("screen") == "Screen"
        assert get_source_label("target") == "Target"

    def test_unknown_source(self):
        assert get_source_label("foo") == "Foo"


class TestFormatTimestamp:
    def test_specific_datetime(self):
        dt = datetime(2026, 4, 1, 23, 17, tzinfo=UTC)
        assert format_timestamp(dt) == "01 avr. 2026 à 23:17"

    def test_january(self):
        dt = datetime(2026, 1, 5, 9, 3, tzinfo=UTC)
        assert format_timestamp(dt) == "05 janv. 2026 à 09:03"

    def test_default_now(self):
        result = format_timestamp()
        assert re.fullmatch(r"\d{2} \w+\.? \d{4} à \d{2}:\d{2}", result)


class TestBuildEntityUrl:
    @patch("app.adapters.formatting.settings")
    def test_company_url(self, mock_settings):
        mock_settings.APP_BASE_URL = "https://app.chapsmind.com"
        event = _make_event()
        url = build_entity_url(event)
        assert url == "https://app.chapsmind.com/folders/folder-abc/companies/42"

    @patch("app.adapters.formatting.settings")
    def test_trailing_slash(self, mock_settings):
        mock_settings.APP_BASE_URL = "https://app.chapsmind.com/"
        event = _make_event()
        url = build_entity_url(event)
        assert url == "https://app.chapsmind.com/folders/folder-abc/companies/42"

    def test_no_entity_type(self):
        event = _make_event(entity_type=None)
        assert build_entity_url(event) is None

    def test_no_entity_id(self):
        event = _make_event(entity_id=None)
        assert build_entity_url(event) is None

    def test_no_folder_id(self):
        event = _make_event(folder_id=None)
        assert build_entity_url(event) is None

    def test_non_company_entity(self):
        event = _make_event(entity_type="task")
        assert build_entity_url(event) is None


class TestExtractPayloadDetails:
    def test_company_name(self):
        event = _make_event(payload={"company_name": "ChapsVision"})
        details = extract_payload_details(event)
        assert ("Entreprise", "ChapsVision") in details

    def test_company_created_with_stats(self):
        event = _make_event(
            event_type="screen.company.created",
            payload={"company_name": "Acme", "success_count": 3, "error_count": 1},
        )
        details = extract_payload_details(event)
        assert ("Entreprise", "Acme") in details
        labels = dict(details)
        assert "3 collectes réussies" in labels["Résultat"]
        assert "1 erreur" in labels["Résultat"]

    def test_company_created_no_errors(self):
        event = _make_event(
            event_type="screen.company.created",
            payload={"company_name": "Acme", "success_count": 5, "error_count": 0},
        )
        details = extract_payload_details(event)
        labels = dict(details)
        assert "5 collectes réussies" in labels["Résultat"]
        assert "erreur" not in labels["Résultat"]

    def test_company_created_single_success(self):
        event = _make_event(
            event_type="screen.company.created",
            payload={"company_name": "Acme", "success_count": 1, "error_count": 0},
        )
        details = extract_payload_details(event)
        labels = dict(details)
        assert "1 collecte réussie" in labels["Résultat"]

    def test_company_created_zero_success(self):
        event = _make_event(
            event_type="screen.company.created",
            payload={"company_name": "Acme", "success_count": 0, "error_count": 0},
        )
        details = extract_payload_details(event)
        labels = dict(details)
        assert "0 collectes réussies" in labels["Résultat"]

    def test_empty_payload(self):
        event = _make_event(payload={})
        details = extract_payload_details(event)
        assert details == []

    def test_ignores_internal_fields(self):
        event = _make_event(payload={"company_id": 42, "action": "update", "website": "https://example.com"})
        details = extract_payload_details(event)
        assert details == []
