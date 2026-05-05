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


def _llm_result(insights: str, top_domains: list[dict], key_ids: list[str]) -> dict:
    """Shape mirroring what ``_analyze_with_llm`` returns."""
    return {
        "data": {
            "insights": insights,
            "top_cpc_domains": top_domains,
            "key_patent_doc_ids": key_ids,
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
        patents, _, _ = _extract_features(raw, {})
        assert [p["patent_number"] for p in patents] == ["EP.1.A1", "EP.2.A1"]

    def test_filing_trend_counts_by_year_and_ignores_missing_dates(self):
        raw = [
            _patent("EP.1.A1", pub_date="2022-02-01"),
            _patent("EP.2.A1", pub_date="2023-05-10"),
            _patent("EP.3.A1", pub_date="2023-12-01"),
            _patent("EP.4.A1", pub_date=None),
        ]
        _, filing_trend, _ = _extract_features(raw, {})
        assert filing_trend == {"2022": 1, "2023": 2}

    def test_pulls_cpc_codes_from_family_by_doc_id(self):
        raw = [_patent("EP.1.A1"), _patent("EP.2.A1")]
        families = _families(
            [
                {"doc_id": "EP.1.A1", "cpc_classifications": ["H04L 9/00", "H04L 12/00"]},
                {"doc_id": "EP.2.A1", "cpc_classifications": ["B64C 1/00"]},
            ]
        )
        patents, _, code_counts = _extract_features(raw, families)

        by_id = {p["patent_number"]: p for p in patents}
        assert by_id["EP.1.A1"]["cpc_codes"] == ["H04L 9/00", "H04L 12/00"]
        assert by_id["EP.2.A1"]["cpc_codes"] == ["B64C 1/00"]
        # H04L appears twice (once per patent — both codes share the same prefix on EP.1.A1).
        assert code_counts["H04L"] == 2
        assert code_counts["B64C"] == 1

    def test_cpc_empty_when_family_absent_or_missing_doc_id(self):
        raw = [_patent("EP.1.A1"), _patent("EP.2.A1")]
        families = _families([{"doc_id": "EP.1.A1", "cpc_classifications": ["H04L 9/00"]}])
        patents, _, _ = _extract_features(raw, families)

        by_id = {p["patent_number"]: p for p in patents}
        assert by_id["EP.1.A1"]["cpc_codes"] == ["H04L 9/00"]
        assert by_id["EP.2.A1"]["cpc_codes"] == []

        # Now with no families at all.
        patents_no_fam, _, code_counts = _extract_features(raw, {})
        assert all(p["cpc_codes"] == [] for p in patents_no_fam)
        assert code_counts == Counter()


class TestIndexCpcByDocId:
    def test_returns_empty_dict_when_families_missing(self):
        assert _index_cpc_by_doc_id({}) == {}
        assert _index_cpc_by_doc_id({"families": []}) == {}

    def test_skips_family_without_doc_id(self):
        idx = _index_cpc_by_doc_id(_families([{"cpc_classifications": ["H04L"]}]))
        assert idx == {}


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
                "is_key_patent": False,
            }
        ]
        [out] = _condense_for_prompt(raw)
        assert len(out["abstract"]) < len(long_abstract)
        assert out["abstract"].endswith("…")


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
                "epo_families": _families([{"doc_id": "EP.1.A1", "cpc_classifications": ["H04L 9/00"]}]),
            }
        )

        llm_return = _llm_result(
            insights="Narrative about Acme.",
            top_domains=[{"code": "H04L", "label": "Digital info", "count": 1}],
            key_ids=["EP.2.A1"],
        )

        with patch(
            "app.agents.nodes.patents._analyze_with_llm",
            new=AsyncMock(return_value=llm_return),
        ) as mock_llm:
            result = await run_patents_agent(state)

        mock_llm.assert_awaited_once()
        assert mock_llm.await_args.kwargs["company_name"] == "Acme Corp"

        agent_result = result["agent_results"][0]
        assert agent_result["status"] == "success"
        assert agent_result["data"]["insights"] == "Narrative about Acme."
        assert agent_result["data"]["total_patents_count"] == 2
        assert agent_result["data"]["filing_trend"] == {"2023": 1, "2024": 1}
        assert agent_result["data"]["top_cpc_domains"] == [{"code": "H04L", "label": "Digital info", "count": 1}]

        # Only EP.2.A1 should be flagged as key.
        by_id = {p["patent_number"]: p for p in agent_result["data"]["patents"]}
        assert by_id["EP.1.A1"]["is_key_patent"] is False
        assert by_id["EP.2.A1"]["is_key_patent"] is True

        # CPC codes threaded through from families.
        assert by_id["EP.1.A1"]["cpc_codes"] == ["H04L 9/00"]
        assert by_id["EP.2.A1"]["cpc_codes"] == []

        assert agent_result["input_tokens"] == 42
        assert agent_result["output_tokens"] == 17

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
