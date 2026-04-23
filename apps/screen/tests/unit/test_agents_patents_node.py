"""Tests for the patents agent node — run_patents_agent and helpers.

The agent runs in two phases:

- Phase 1 (programmatic) extracts patents from ``enrichment_data``,
  dedupes by ``doc_id``, pulls CPC codes from ``epo_families``, and
  builds the yearly filing trend.
- Phase 2 (LLM) receives a condensed portfolio view and returns
  insights + CPC domain labels + key patent numbers via the Chat
  Completions API.

These tests mock ``_analyze_with_llm`` so they stay fast and network-free.
"""

from __future__ import annotations

from collections import Counter
from unittest.mock import AsyncMock, patch

import pytest

from app.agents.nodes.patents import (
    _condense_for_prompt,
    _cpc_prefix,
    _extract_features,
    _index_cpc_by_doc_id,
    _index_family_meta,
    _index_legal,
    run_patents_agent,
)

# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------


def _state(enrichment_data: dict | None = None) -> dict:
    """Minimal CompanyAnalysisState-shaped dict for the node under test."""
    return {
        "company_id": 1,
        "company_name": "Acme Corp",
        "website": "https://acme.com",
        "organization_id": "org-1",
        "owner_id": "user-1",
        "country_code": "FR",
        "company_brief": None,
        "enrichment_data": enrichment_data or {},
        "agents_to_run": ["patents"],
        "agent_results": [],
        "quality_issues": [],
        "agents_to_retry": [],
        "retry_counts": {},
        "total_tokens": 0,
        "total_cost": 0.0,
    }


def _patent(doc_id: str, pub_date: str | None = "2024-06-15", abstract: str | None = "Some abstract.") -> dict:
    return {
        "doc_id": doc_id,
        "title": f"Invention {doc_id}",
        "applicants": ["ACME CORP"],
        "inventors": ["Doe, Jane"],
        "ipc_classes": [],  # deliberately unused by the agent
        "publication_date": pub_date,
        "application_date": pub_date,
        "abstract": abstract,
        "abstract_lang": "en",
    }


def _publications(patents: list[dict]) -> dict:
    return {"applicant_query": "Acme*", "total_results": len(patents), "patents": patents}


def _families(entries: list[dict]) -> dict:
    return {"families": entries}


def _family_entry(
    doc_id: str,
    *,
    cpc: list[str] | None = None,
    countries: list[str] | None = None,
    family_size: int | None = None,
) -> dict:
    """Build a family entry matching the ``epo_families`` JSON shape.

    ``countries`` is expanded into one ``family_members`` dict per entry
    so duplicates are preserved (needed to exercise the geographic
    coverage aggregation logic).
    """
    members = [{"country": c, "doc_id": f"{c}.0.A1", "doc_number": "0"} for c in (countries or [])]
    return {
        "doc_id": doc_id,
        "family_size": family_size if family_size is not None else len(members),
        "family_members": members,
        "cpc_classifications": list(cpc or []),
    }


def _legal(statuses: dict[str, str]) -> dict:
    """Build the ``epo_legal`` payload from a ``{doc_id: simplified_status}`` map."""
    return {
        "legal_statuses": [
            {"doc_id": doc_id, "simplified_status": status, "events": []} for doc_id, status in statuses.items()
        ]
    }


def _llm_result(
    insights: str,
    top_domains: list[dict],
    key_ids: list[str],
    portfolio_strength: str = "",
) -> dict:
    """Shape mirroring what ``_analyze_with_llm`` returns."""
    return {
        "data": {
            "insights": insights,
            "top_cpc_domains": top_domains,
            "key_patent_doc_ids": key_ids,
            "portfolio_strength": portfolio_strength,
        },
        "input_tokens": 42,
        "output_tokens": 17,
    }


# ---------------------------------------------------------------------------
# Phase 1 helpers
# ---------------------------------------------------------------------------


class TestExtractFeatures:
    def test_dedupes_by_doc_id(self):
        raw = [_patent("EP.1.A1"), _patent("EP.2.A1"), _patent("EP.1.A1")]
        patents, _, _, _, _ = _extract_features(raw, {}, {})
        assert [p["patent_number"] for p in patents] == ["EP.1.A1", "EP.2.A1"]

    def test_filing_trend_counts_by_year_and_ignores_missing_dates(self):
        raw = [
            _patent("EP.1.A1", pub_date="2022-02-01"),
            _patent("EP.2.A1", pub_date="2023-05-10"),
            _patent("EP.3.A1", pub_date="2023-12-01"),
            _patent("EP.4.A1", pub_date=None),
        ]
        _, filing_trend, _, _, _ = _extract_features(raw, {}, {})
        assert filing_trend == {"2022": 1, "2023": 2}

    def test_pulls_cpc_codes_from_family_by_doc_id(self):
        raw = [_patent("EP.1.A1"), _patent("EP.2.A1")]
        families = _families(
            [
                {"doc_id": "EP.1.A1", "cpc_classifications": ["H04L 9/00", "H04L 12/00"]},
                {"doc_id": "EP.2.A1", "cpc_classifications": ["B64C 1/00"]},
            ]
        )
        patents, _, code_counts, _, _ = _extract_features(raw, families, {})

        by_id = {p["patent_number"]: p for p in patents}
        assert by_id["EP.1.A1"]["cpc_codes"] == ["H04L 9/00", "H04L 12/00"]
        assert by_id["EP.2.A1"]["cpc_codes"] == ["B64C 1/00"]
        # H04L appears twice (once per patent — both codes share the same prefix on EP.1.A1).
        assert code_counts["H04L"] == 2
        assert code_counts["B64C"] == 1

    def test_cpc_empty_when_family_absent_or_missing_doc_id(self):
        raw = [_patent("EP.1.A1"), _patent("EP.2.A1")]
        families = _families([{"doc_id": "EP.1.A1", "cpc_classifications": ["H04L 9/00"]}])
        patents, _, _, _, _ = _extract_features(raw, families, {})

        by_id = {p["patent_number"]: p for p in patents}
        assert by_id["EP.1.A1"]["cpc_codes"] == ["H04L 9/00"]
        assert by_id["EP.2.A1"]["cpc_codes"] == []

        # Now with no families at all.
        patents_no_fam, _, code_counts, _, _ = _extract_features(raw, {}, {})
        assert all(p["cpc_codes"] == [] for p in patents_no_fam)
        assert code_counts == Counter()

    def test_aggregates_geographic_and_legal_counts(self):
        raw = [_patent("EP.1.A1"), _patent("EP.2.A1"), _patent("EP.3.A1")]
        families = _families(
            [
                _family_entry("EP.1.A1", countries=["EP", "US", "JP"], family_size=3),
                _family_entry("EP.2.A1", countries=["EP", "US", "US"], family_size=3),
                _family_entry("EP.3.A1", countries=[], family_size=0),
            ]
        )
        legal = _legal({"EP.1.A1": "active", "EP.2.A1": "expired", "EP.3.A1": "active"})

        patents, _, _, geographic_coverage, status_breakdown = _extract_features(raw, families, legal)

        # EP appears on EP.1.A1 and EP.2.A1 (one member each) → 2.
        # US appears on EP.1.A1 (one) + EP.2.A1 (two duplicates intentionally kept) → 3.
        # JP only on EP.1.A1 → 1.
        assert geographic_coverage == {"EP": 2, "US": 3, "JP": 1}
        assert status_breakdown == {"active": 2, "expired": 1}

        # Per-item annotations reflect per-patent metadata.
        by_id = {p["patent_number"]: p for p in patents}
        assert by_id["EP.1.A1"]["family_size"] == 3
        assert by_id["EP.1.A1"]["family_countries"] == ["EP", "JP", "US"]  # sorted unique
        assert by_id["EP.1.A1"]["legal_status"] == "active"
        assert by_id["EP.2.A1"]["family_countries"] == ["EP", "US"]
        assert by_id["EP.2.A1"]["legal_status"] == "expired"
        assert by_id["EP.3.A1"]["family_size"] == 0
        assert by_id["EP.3.A1"]["family_countries"] == []
        assert by_id["EP.3.A1"]["legal_status"] == "active"

    def test_missing_families_and_legal_degrade_gracefully(self):
        raw = [_patent("EP.1.A1"), _patent("EP.2.A1")]
        patents, _, _, geographic_coverage, status_breakdown = _extract_features(raw, {}, {})

        assert geographic_coverage == {}
        assert status_breakdown == {}
        for p in patents:
            assert p["family_size"] == 0
            assert p["family_countries"] == []
            assert p["legal_status"] is None

    def test_missing_legal_only_keeps_geographic_coverage(self):
        raw = [_patent("EP.1.A1")]
        families = _families([_family_entry("EP.1.A1", countries=["EP", "US"], family_size=2)])

        patents, _, _, geographic_coverage, status_breakdown = _extract_features(raw, families, {})
        assert geographic_coverage == {"EP": 1, "US": 1}
        assert status_breakdown == {}
        assert patents[0]["family_size"] == 2
        assert patents[0]["legal_status"] is None


class TestIndexCpcByDocId:
    def test_returns_empty_dict_when_families_missing(self):
        assert _index_cpc_by_doc_id({}) == {}
        assert _index_cpc_by_doc_id({"families": []}) == {}

    def test_skips_family_without_doc_id(self):
        idx = _index_cpc_by_doc_id(_families([{"cpc_classifications": ["H04L"]}]))
        assert idx == {}


class TestIndexFamilyMeta:
    def test_returns_empty_when_families_missing(self):
        assert _index_family_meta({}) == {}
        assert _index_family_meta({"families": []}) == {}

    def test_builds_per_doc_metadata(self):
        families = _families([_family_entry("EP.1.A1", countries=["US", "EP", "US"], family_size=3)])
        idx = _index_family_meta(families)
        assert idx["EP.1.A1"][0] == 3  # family_size
        assert idx["EP.1.A1"][1] == ["EP", "US"]  # sorted unique countries
        assert idx["EP.1.A1"][2] == ["US", "EP", "US"]  # raw member countries (duplicates kept)

    def test_falls_back_to_member_count_when_family_size_absent(self):
        families = {"families": [{"doc_id": "EP.1.A1", "family_members": [{"country": "EP"}]}]}
        idx = _index_family_meta(families)
        assert idx["EP.1.A1"][0] == 1

    def test_skips_entries_with_blank_country(self):
        families = {"families": [{"doc_id": "EP.1.A1", "family_members": [{"country": ""}, {"country": "US"}]}]}
        idx = _index_family_meta(families)
        assert idx["EP.1.A1"][1] == ["US"]


class TestIndexLegal:
    def test_returns_empty_when_payload_missing(self):
        assert _index_legal({}) == {}
        assert _index_legal({"legal_statuses": []}) == {}

    def test_indexes_by_doc_id_and_skips_blank_status(self):
        payload = {
            "legal_statuses": [
                {"doc_id": "EP.1.A1", "simplified_status": "active"},
                {"doc_id": "EP.2.A1", "simplified_status": ""},  # skipped
                {"doc_id": None, "simplified_status": "expired"},  # skipped
                {"doc_id": "EP.3.A1", "simplified_status": "pending"},
            ]
        }
        idx = _index_legal(payload)
        assert idx == {"EP.1.A1": "active", "EP.3.A1": "pending"}


class TestCpcPrefix:
    def test_extracts_four_char_prefix(self):
        assert _cpc_prefix("H04L 9/00") == "H04L"
        assert _cpc_prefix("B64C 1/00") == "B64C"

    def test_handles_short_codes(self):
        assert _cpc_prefix("B64") == "B64"
        assert _cpc_prefix("") is None
        assert _cpc_prefix(None) is None  # type: ignore[arg-type]


class TestCondenseForPrompt:
    def test_keeps_most_recent_and_caps_count(self):
        raw_patents = [
            {
                "patent_number": f"EP.{i}.A1",
                "title": f"t{i}",
                "abstract": "a",
                "publication_date": f"20{20 + (i % 5):02d}-01-01",
                "cpc_codes": [],
                "inventors": [],
                "applicants": [],
                "family_size": 0,
                "family_countries": [],
                "legal_status": None,
                "is_key_patent": False,
            }
            for i in range(50)
        ]
        condensed = _condense_for_prompt(raw_patents)
        assert len(condensed) == 30  # MAX_PATENTS_IN_PROMPT default
        # First entry should be the most recent.
        assert condensed[0]["publication_date"] >= condensed[-1]["publication_date"]

    def test_truncates_abstracts(self):
        long_abstract = "x" * 2000
        raw = [
            {
                "patent_number": "EP.1.A1",
                "title": "t",
                "abstract": long_abstract,
                "publication_date": "2024-01-01",
                "cpc_codes": [],
                "inventors": [],
                "applicants": [],
                "family_size": 0,
                "family_countries": [],
                "legal_status": None,
                "is_key_patent": False,
            }
        ]
        [out] = _condense_for_prompt(raw)
        assert len(out["abstract"]) < len(long_abstract)
        assert out["abstract"].endswith("…")

    def test_passes_families_and_legal_fields_through(self):
        raw = [
            {
                "patent_number": "EP.1.A1",
                "title": "t",
                "abstract": "a",
                "publication_date": "2024-01-01",
                "cpc_codes": ["H04L"],
                "inventors": [],
                "applicants": [],
                "family_size": 4,
                "family_countries": ["EP", "US"],
                "legal_status": "active",
                "is_key_patent": False,
            }
        ]
        [out] = _condense_for_prompt(raw)
        assert out["family_size"] == 4
        assert out["family_countries"] == ["EP", "US"]
        assert out["legal_status"] == "active"


# ---------------------------------------------------------------------------
# run_patents_agent — end-to-end
# ---------------------------------------------------------------------------


class TestRunPatentsAgent:
    @pytest.mark.asyncio
    async def test_no_publications_returns_success_with_empty_payload(self):
        result = await run_patents_agent(_state(enrichment_data={}))

        [agent_result] = result["agent_results"]
        assert agent_result["agent_name"] == "patents"
        assert agent_result["status"] == "success"
        assert agent_result["data"]["patents"] == []
        assert agent_result["data"]["total_patents_count"] == 0
        assert agent_result["data"]["top_cpc_domains"] == []
        assert agent_result["data"]["filing_trend"] == {}
        assert agent_result["data"]["geographic_coverage"] == {}
        assert agent_result["data"]["status_breakdown"] == {}
        assert agent_result["data"]["portfolio_strength"] == ""

    @pytest.mark.asyncio
    async def test_empty_patent_list_returns_success_with_empty_payload(self):
        state = _state(enrichment_data={"epo_publications": _publications([])})
        result = await run_patents_agent(state)
        assert result["agent_results"][0]["status"] == "success"
        assert result["agent_results"][0]["data"]["patents"] == []

    @pytest.mark.asyncio
    async def test_merges_phase1_counts_with_phase2_insights(self):
        raw = [
            _patent("EP.1.A1", pub_date="2023-04-01", abstract="Abstract one."),
            _patent("EP.2.A1", pub_date="2024-07-01", abstract="Abstract two."),
        ]
        state = _state(
            enrichment_data={
                "epo_publications": _publications(raw),
                "epo_families": _families(
                    [
                        _family_entry("EP.1.A1", cpc=["H04L 9/00"], countries=["EP", "US"], family_size=2),
                        _family_entry("EP.2.A1", cpc=[], countries=["EP"], family_size=1),
                    ]
                ),
                "epo_legal": _legal({"EP.1.A1": "active", "EP.2.A1": "expired"}),
            }
        )

        llm_return = _llm_result(
            insights="Narrative about Acme.",
            top_domains=[{"code": "H04L", "label": "Digital info", "count": 1}],
            key_ids=["EP.2.A1"],
            portfolio_strength="Solid European footprint with one expired publication.",
        )

        with patch(
            "app.agents.nodes.patents._analyze_with_llm",
            new=AsyncMock(return_value=llm_return),
        ) as mock_llm:
            result = await run_patents_agent(state)

        mock_llm.assert_awaited_once()
        call_kwargs = mock_llm.await_args.kwargs
        assert call_kwargs["company_name"] == "Acme Corp"
        # Both optional aggregates should be threaded through to the LLM helper.
        assert call_kwargs["geographic_coverage"]
        assert call_kwargs["status_breakdown"]

        agent_result = result["agent_results"][0]
        assert agent_result["status"] == "success"
        assert agent_result["data"]["insights"] == "Narrative about Acme."
        assert agent_result["data"]["total_patents_count"] == 2
        assert agent_result["data"]["filing_trend"] == {"2023": 1, "2024": 1}
        assert agent_result["data"]["top_cpc_domains"] == [{"code": "H04L", "label": "Digital info", "count": 1}]
        assert agent_result["data"]["geographic_coverage"] == {"EP": 2, "US": 1}
        assert agent_result["data"]["status_breakdown"] == {"active": 1, "expired": 1}
        assert agent_result["data"]["portfolio_strength"].startswith("Solid European")

        # Only EP.2.A1 should be flagged as key.
        by_id = {p["patent_number"]: p for p in agent_result["data"]["patents"]}
        assert by_id["EP.1.A1"]["is_key_patent"] is False
        assert by_id["EP.2.A1"]["is_key_patent"] is True

        # CPC codes threaded through from families.
        assert by_id["EP.1.A1"]["cpc_codes"] == ["H04L 9/00"]
        assert by_id["EP.2.A1"]["cpc_codes"] == []

        # Per-item families + legal.
        assert by_id["EP.1.A1"]["family_size"] == 2
        assert by_id["EP.1.A1"]["family_countries"] == ["EP", "US"]
        assert by_id["EP.1.A1"]["legal_status"] == "active"
        assert by_id["EP.2.A1"]["legal_status"] == "expired"

        assert agent_result["input_tokens"] == 42
        assert agent_result["output_tokens"] == 17

    @pytest.mark.asyncio
    async def test_publications_only_omits_families_and_legal_aggregates(self):
        """Graceful degradation: no families and no legal → empty aggregates threaded to the LLM."""
        raw = [_patent("EP.1.A1", pub_date="2024-01-01")]
        state = _state(enrichment_data={"epo_publications": _publications(raw)})

        llm_return = _llm_result(
            insights="Insights only from publications.",
            top_domains=[],
            key_ids=[],
            portfolio_strength="",  # LLM returns empty per prompt instructions
        )

        with patch(
            "app.agents.nodes.patents._analyze_with_llm",
            new=AsyncMock(return_value=llm_return),
        ) as mock_llm:
            result = await run_patents_agent(state)

        call_kwargs = mock_llm.await_args.kwargs
        assert call_kwargs["geographic_coverage"] == {}
        assert call_kwargs["status_breakdown"] == {}

        data = result["agent_results"][0]["data"]
        assert data["geographic_coverage"] == {}
        assert data["status_breakdown"] == {}
        assert data["portfolio_strength"] == ""
        # Publications-only synthesis still succeeds.
        assert result["agent_results"][0]["status"] == "success"
        assert data["insights"] == "Insights only from publications."

    @pytest.mark.asyncio
    async def test_llm_error_returns_status_error_with_phase1_artifacts(self):
        raw = [_patent("EP.1.A1", pub_date="2023-04-01")]
        state = _state(enrichment_data={"epo_publications": _publications(raw)})

        with patch(
            "app.agents.nodes.patents._analyze_with_llm",
            new=AsyncMock(side_effect=RuntimeError("LLM down")),
        ):
            result = await run_patents_agent(state)

        agent_result = result["agent_results"][0]
        assert agent_result["status"] == "error"
        assert agent_result["error"] == "LLM down"
        # Phase 1 counts are preserved so the UI still has something to show.
        assert agent_result["data"]["total_patents_count"] == 1
        assert agent_result["data"]["filing_trend"] == {"2023": 1}
        assert [p["patent_number"] for p in agent_result["data"]["patents"]] == ["EP.1.A1"]

    @pytest.mark.asyncio
    async def test_llm_timeout_returns_status_error(self):
        raw = [_patent("EP.1.A1", pub_date="2023-04-01")]
        state = _state(enrichment_data={"epo_publications": _publications(raw)})

        async def _never_returns(*_args, **_kwargs):
            import asyncio

            await asyncio.sleep(10_000)

        with (
            patch("app.agents.nodes.patents.AGENT_TIMEOUT_SECONDS", 0),
            patch("app.agents.nodes.patents._analyze_with_llm", new=AsyncMock(side_effect=_never_returns)),
        ):
            result = await run_patents_agent(state)

        agent_result = result["agent_results"][0]
        assert agent_result["status"] == "error"
        assert agent_result["error"] is not None and "timed out" in agent_result["error"].lower()
