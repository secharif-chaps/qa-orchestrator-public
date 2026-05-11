"""Unit tests for save_patents_data / get_patents_data.

Tests exercise the persistence layer with a mocked SQLAlchemy session —
they verify the data-shape contract (field mapping, delete-then-insert,
serialisation) without touching a real database. End-to-end DB behaviour
(cascades, constraints) is covered by the integration layer.
"""

from __future__ import annotations

from datetime import date
from unittest.mock import MagicMock

from app.models.company_patents import CompanyPatentItem, CompanyPatents, LegalStatus
from app.services.company_section_service import (
    _parse_legal_status,
    _parse_patent_publication_date,
    _serialize_patent_item,
    get_patents_data,
    save_patents_data,
)


def _make_db_with_no_existing_section() -> MagicMock:
    """Build a MagicMock session where first() returns None / all() returns []."""
    db = MagicMock()
    query = db.query.return_value
    query.filter.return_value.first.return_value = None
    query.filter.return_value.delete.return_value = 0
    query.filter.return_value.order_by.return_value.all.return_value = []
    return db


# ---------------------------------------------------------------------------
# save_patents_data
# ---------------------------------------------------------------------------


class TestSavePatentsData:
    def test_creates_section_when_missing(self):
        db = _make_db_with_no_existing_section()
        data = {
            "insights": "Some insights",
            "total_patents_count": 0,
            "top_cpc_domains": [],
            "filing_trend": {},
            "geographic_coverage": {"EP": 3, "US": 2},
            "status_breakdown": {"active": 2, "expired": 1},
            "portfolio_strength": "Solid foundation.",
            "patents": [],
        }

        save_patents_data(db, company_id=1, data=data)

        # db.add was called at least once with a CompanyPatents instance.
        added_sections = [call.args[0] for call in db.add.call_args_list if isinstance(call.args[0], CompanyPatents)]
        assert len(added_sections) == 1
        section = added_sections[0]
        assert section.company_id == 1
        assert section.insights == "Some insights"
        assert section.total_patents_count == 0
        assert section.top_cpc_domains == []
        assert section.filing_trend == {}
        assert section.geographic_coverage == {"EP": 3, "US": 2}
        assert section.status_breakdown == {"active": 2, "expired": 1}
        assert section.portfolio_strength == "Solid foundation."

    def test_updates_existing_section_without_re_adding(self):
        db = MagicMock()
        existing = CompanyPatents(
            company_id=1,
            insights="old",
            total_patents_count=3,
            top_cpc_domains=[{"code": "H04L", "label": "old", "count": 1}],
            filing_trend={"2022": 3},
            geographic_coverage={"EP": 1},
            status_breakdown={"active": 1},
            portfolio_strength="old strength",
        )
        query = db.query.return_value
        # First filter().first() → existing section, subsequent chain for delete() is harmless.
        query.filter.return_value.first.return_value = existing

        data = {
            "insights": "new insights",
            "total_patents_count": 5,
            "top_cpc_domains": [{"code": "B64C", "label": "new", "count": 5}],
            "filing_trend": {"2024": 5},
            "geographic_coverage": {"US": 4, "JP": 2},
            "status_breakdown": {"active": 3, "pending": 2},
            "portfolio_strength": "new strength",
            "patents": [],
        }

        save_patents_data(db, company_id=1, data=data)

        assert existing.insights == "new insights"
        assert existing.total_patents_count == 5
        assert existing.top_cpc_domains == [{"code": "B64C", "label": "new", "count": 5}]
        assert existing.filing_trend == {"2024": 5}
        assert existing.geographic_coverage == {"US": 4, "JP": 2}
        assert existing.status_breakdown == {"active": 3, "pending": 2}
        assert existing.portfolio_strength == "new strength"
        # The existing section is not re-added.
        for call in db.add.call_args_list:
            assert not (isinstance(call.args[0], CompanyPatents) and call.args[0] is existing)

    def test_section_defaults_when_families_and_legal_missing(self):
        """Graceful degradation: agent output without families/legal fields persists zeros/None."""
        db = _make_db_with_no_existing_section()
        data = {
            "insights": "Publications-only insights",
            "total_patents_count": 1,
            "top_cpc_domains": [],
            "filing_trend": {"2024": 1},
            # No geographic_coverage / status_breakdown / portfolio_strength keys.
            "patents": [{"patent_number": "EP.1.A1"}],
        }

        save_patents_data(db, company_id=1, data=data)

        added_sections = [call.args[0] for call in db.add.call_args_list if isinstance(call.args[0], CompanyPatents)]
        section = added_sections[0]
        assert section.geographic_coverage == {}
        assert section.status_breakdown == {}
        assert section.portfolio_strength is None

    def test_inserts_patent_items_and_coerces_date(self):
        db = _make_db_with_no_existing_section()
        data = {
            "insights": "",
            "total_patents_count": 2,
            "top_cpc_domains": [],
            "filing_trend": {"2024": 2},
            "patents": [
                {
                    "patent_number": "EP.1.A1",
                    "title": "T1",
                    "abstract": "abs1",
                    "inventors": ["Alice"],
                    "applicants": ["Acme"],
                    "publication_date": "2024-06-15",
                    "cpc_codes": ["H04L 9/00"],
                    "is_key_patent": True,
                    "family_size": 5,
                    "family_countries": ["EP", "US", "JP"],
                    "legal_status": "active",
                },
                {
                    "patent_number": "EP.2.A1",
                    "title": "T2",
                    "abstract": None,
                    "inventors": [],
                    "applicants": [],
                    "publication_date": None,
                    "cpc_codes": [],
                    "is_key_patent": False,
                    # No families/legal → defaults.
                },
            ],
        }

        save_patents_data(db, company_id=1, data=data)

        items = [call.args[0] for call in db.add.call_args_list if isinstance(call.args[0], CompanyPatentItem)]
        assert [i.patent_number for i in items] == ["EP.1.A1", "EP.2.A1"]
        assert items[0].publication_date == date(2024, 6, 15)
        assert items[0].is_key_patent is True
        assert items[0].cpc_codes == ["H04L 9/00"]
        assert items[0].inventors == ["Alice"]
        assert items[0].family_size == 5
        assert items[0].family_countries == ["EP", "US", "JP"]
        assert items[0].legal_status is LegalStatus.active
        assert items[1].publication_date is None
        assert items[1].is_key_patent is False
        assert items[1].family_size == 0
        assert items[1].family_countries == []
        assert items[1].legal_status is None

    def test_skips_items_without_patent_number_and_duplicates(self):
        db = _make_db_with_no_existing_section()
        data = {
            "insights": "",
            "total_patents_count": 0,
            "top_cpc_domains": [],
            "filing_trend": {},
            "patents": [
                {"patent_number": "EP.1.A1", "title": "a"},
                {"patent_number": None, "title": "bad"},
                {"patent_number": "EP.1.A1", "title": "dup"},  # duplicate — skipped in-flight
            ],
        }

        save_patents_data(db, company_id=1, data=data)

        items = [call.args[0] for call in db.add.call_args_list if isinstance(call.args[0], CompanyPatentItem)]
        assert [i.patent_number for i in items] == ["EP.1.A1"]

    def test_calls_delete_on_existing_items_before_insert(self):
        """delete-then-insert replaces the patent_item set for the company."""
        db = _make_db_with_no_existing_section()
        save_patents_data(db, company_id=1, data={"patents": []})
        # Two filter() calls: one for section lookup, one for items delete.
        # The items delete call invokes .delete() on its filter chain.
        delete_calls = []
        for filter_call in db.query.return_value.filter.return_value.mock_calls:
            name = filter_call[0]
            if name.endswith("delete"):
                delete_calls.append(filter_call)
        # delete() was invoked (order-of-calls: section-fetch via first(), items-wipe via delete()).
        assert any(c[0].endswith("delete") for c in db.query.return_value.filter.return_value.mock_calls)


# ---------------------------------------------------------------------------
# get_patents_data
# ---------------------------------------------------------------------------


class TestGetPatentsData:
    def test_returns_empty_dict_when_no_section(self):
        db = _make_db_with_no_existing_section()
        assert get_patents_data(db, 1) == {}

    def test_returns_serialized_section_and_items(self):
        db = MagicMock()
        section = CompanyPatents(
            company_id=1,
            insights="innov summary",
            total_patents_count=2,
            top_cpc_domains=[{"code": "H04L", "label": "Digital info", "count": 2}],
            filing_trend={"2023": 1, "2024": 1},
            geographic_coverage={"EP": 2, "US": 1},
            status_breakdown={"active": 1, "expired": 1},
            portfolio_strength="Balanced European footprint.",
        )
        items = [
            CompanyPatentItem(
                company_id=1,
                patent_number="EP.2.A1",
                title="Newer",
                abstract="abs",
                inventors=["Alice"],
                applicants=["Acme"],
                publication_date=date(2024, 1, 1),
                cpc_codes=["H04L 9/00"],
                is_key_patent=True,
                family_size=3,
                family_countries=["EP", "US", "JP"],
                legal_status=LegalStatus.active,
            ),
            CompanyPatentItem(
                company_id=1,
                patent_number="EP.1.A1",
                title="Older",
                abstract=None,
                inventors=[],
                applicants=[],
                publication_date=date(2023, 5, 1),
                cpc_codes=[],
                is_key_patent=False,
                family_size=0,
                family_countries=[],
                legal_status=None,
            ),
        ]

        # Section query: first() returns section. Items query: .order_by().all() returns items.
        def _query_side_effect(model):
            q = MagicMock()
            if model is CompanyPatents:
                q.filter.return_value.first.return_value = section
            else:  # CompanyPatentItem
                q.filter.return_value.order_by.return_value.all.return_value = items
            return q

        db.query.side_effect = _query_side_effect

        result = get_patents_data(db, company_id=1)

        assert result["insights"] == "innov summary"
        assert result["total_patents_count"] == 2
        assert result["filing_trend"] == {"2023": 1, "2024": 1}
        assert result["geographic_coverage"] == {"EP": 2, "US": 1}
        assert result["status_breakdown"] == {"active": 1, "expired": 1}
        assert result["portfolio_strength"] == "Balanced European footprint."
        assert [p["patent_number"] for p in result["patents"]] == ["EP.2.A1", "EP.1.A1"]
        assert result["patents"][0]["publication_date"] == "2024-01-01"
        assert result["patents"][0]["is_key_patent"] is True
        assert result["patents"][0]["family_size"] == 3
        assert result["patents"][0]["family_countries"] == ["EP", "US", "JP"]
        assert result["patents"][0]["legal_status"] == "active"
        assert result["patents"][1]["publication_date"] == "2023-05-01"
        assert result["patents"][1]["family_size"] == 0
        assert result["patents"][1]["family_countries"] == []
        assert result["patents"][1]["legal_status"] is None


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


class TestParsePatentPublicationDate:
    def test_parses_iso_string(self):
        assert _parse_patent_publication_date("2024-06-15") == date(2024, 6, 15)

    def test_passes_through_date(self):
        d = date(2024, 1, 1)
        assert _parse_patent_publication_date(d) is d

    def test_none_stays_none(self):
        assert _parse_patent_publication_date(None) is None

    def test_malformed_returns_none(self):
        assert _parse_patent_publication_date("not-a-date") is None


class TestSerializePatentItem:
    def test_date_rendered_as_iso(self):
        item = CompanyPatentItem(
            company_id=1,
            patent_number="EP.1.A1",
            title="t",
            abstract="a",
            inventors=["Alice"],
            applicants=["Acme"],
            publication_date=date(2024, 6, 15),
            cpc_codes=["H04L 9/00"],
            is_key_patent=True,
            family_size=2,
            family_countries=["EP", "US"],
            legal_status=LegalStatus.expired,
        )
        out = _serialize_patent_item(item)
        assert out["publication_date"] == "2024-06-15"
        assert out["is_key_patent"] is True
        assert out["family_size"] == 2
        assert out["family_countries"] == ["EP", "US"]
        assert out["legal_status"] == "expired"

    def test_null_date_serialises_to_none(self):
        item = CompanyPatentItem(
            company_id=1,
            patent_number="EP.1.A1",
            title=None,
            abstract=None,
            inventors=[],
            applicants=[],
            publication_date=None,
            cpc_codes=[],
            is_key_patent=False,
            family_size=0,
            family_countries=[],
            legal_status=None,
        )
        out = _serialize_patent_item(item)
        assert out["publication_date"] is None
        assert out["legal_status"] is None
        assert out["family_countries"] == []


class TestParseLegalStatus:
    def test_accepts_enum_value_strings(self):
        assert _parse_legal_status("active") is LegalStatus.active
        assert _parse_legal_status("expired") is LegalStatus.expired
        assert _parse_legal_status("pending") is LegalStatus.pending
        assert _parse_legal_status("unknown") is LegalStatus.unknown

    def test_passes_through_enum_instance(self):
        assert _parse_legal_status(LegalStatus.active) is LegalStatus.active

    def test_returns_none_for_blank_or_none(self):
        assert _parse_legal_status(None) is None
        assert _parse_legal_status("") is None

    def test_returns_none_for_unknown_string(self):
        assert _parse_legal_status("granted") is None
        assert _parse_legal_status("foo") is None
