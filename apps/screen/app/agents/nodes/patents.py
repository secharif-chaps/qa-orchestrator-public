"""Patents agent node — two-phase analysis of EPO publications.

Unlike the standard web-search agents, the patents agent works from
pre-fetched enrichment data only:

  Phase 1 (programmatic): extract patents from ``enrichment_data``
          (``epo_publications``), dedupe by ``doc_id``, pull CPC codes
          from ``epo_families`` when available, compute the yearly
          filing trend and per-code counts.
  Phase 2 (LLM): call the LLM with a condensed view of the portfolio to
          produce narrative insights, map top classification codes to
          human-readable labels, and flag key patents.

No web search is issued: the LLM call goes through the Chat Completions
API (same path as the sanctions agent) so the ``web_search`` tool is
never even attached to the request.

Returns the standard ``{"agent_results": [AgentResult]}`` so the
downstream synthesizer / runner / persistence path is unchanged.
"""

from __future__ import annotations

import asyncio
import json
import time
from collections import Counter
from typing import Any

from app.agents.config import AGENT_TIMEOUT_SECONDS, LLM_MODEL, PROMPTS_REGISTRY
from app.agents.schemas import AGENT_OUTPUT_SCHEMAS
from app.agents.state import AgentResult, CompanyAnalysisState
from app.core.llm import get_chat_client
from app.core.logging_config import get_logger

logger = get_logger(__name__)

AGENT_NAME = "patents"

# Cap the number of patents we send to the LLM to bound token cost;
# the Phase 1 programmatic counts are computed over the full list.
MAX_PATENTS_IN_PROMPT = 30
# Truncate each abstract; avoids blowing up the prompt on very long ones.
ABSTRACT_MAX_CHARS = 600
# Cap the number of top classification codes surfaced to the LLM.
MAX_TOP_CODES = 20

# Cap the number of countries surfaced in the prompt's geographic coverage block.
MAX_GEO_COUNTRIES_IN_PROMPT = 15

async def run_patents_agent(state: CompanyAnalysisState) -> dict:
    """Execute the two-phase patents analysis.

    Reads ``enrichment_data`` from state, produces a full ``AgentResult``
    whose ``data`` payload matches the contract expected by
    ``save_patents_data`` (insights, total_patents_count, top_cpc_domains,
    filing_trend, patents list).

    No EPO data → ``status="success"`` with empty payload, so the task
    terminates cleanly (acceptance criterion: "Given no EPO data, the
    agent terminates without error").
    """
    start_time = time.monotonic()
    company_name = state["company_name"]

    enrichment = state.get("enrichment_data") or {}
    publications = enrichment.get("epo_publications") or {}
    families_payload = enrichment.get("epo_families") or {}
    legal_payload = enrichment.get("epo_legal") or {}

    raw_patents = publications.get("patents") or []
    if not raw_patents:
        logger.info(
            "Patents agent: no EPO publications data, returning empty success",
            extra={"company_name": company_name},
        )
        return _empty_success(company_name, start_time)

    # Phase 1: programmatic extraction.
    (
        patents,
        filing_trend,
        code_counts,
        geographic_coverage,
        status_breakdown,
    ) = _extract_features(raw_patents, families_payload, legal_payload)

    # Phase 2: LLM analysis (timeout-bounded, errors swallowed into status="error").
    try:
        llm_out = await asyncio.wait_for(
            _analyze_with_llm(
                company_name=company_name,
                patents=patents,
                code_counts=code_counts,
                geographic_coverage=geographic_coverage,
                status_breakdown=status_breakdown,
            ),
            timeout=AGENT_TIMEOUT_SECONDS,
        )
    except TimeoutError:
        duration_ms = int((time.monotonic() - start_time) * 1000)
        logger.error(
            "Patents agent timed out",
            extra={"company_name": company_name, "timeout_s": AGENT_TIMEOUT_SECONDS},
        )
        return _error_result(
            company_name=company_name,
            patents=patents,
            filing_trend=filing_trend,
            geographic_coverage=geographic_coverage,
            status_breakdown=status_breakdown,
            error=f"Agent timed out after {AGENT_TIMEOUT_SECONDS} seconds",
            duration_ms=duration_ms,
        )
    except Exception as exc:
        duration_ms = int((time.monotonic() - start_time) * 1000)
        logger.error(
            "Patents agent LLM call failed",
            exc_info=True,
            extra={"company_name": company_name, "error": str(exc)},
        )
        return _error_result(
            company_name=company_name,
            patents=patents,
            filing_trend=filing_trend,
            geographic_coverage=geographic_coverage,
            status_breakdown=status_breakdown,
            error=str(exc),
            duration_ms=duration_ms,
        )

    # Merge Phase 1 + Phase 2: flag key patents from the LLM's pick list.
    key_ids = set(llm_out["data"].get("key_patent_doc_ids") or [])
    for patent in patents:
        patent["is_key_patent"] = patent["patent_number"] in key_ids

    duration_ms = int((time.monotonic() - start_time) * 1000)
    return {
        "agent_results": [
            AgentResult(
                agent_name=AGENT_NAME,
                status="success",
                data={
                    "insights": llm_out["data"].get("insights") or "",
                    "total_patents_count": len(patents),
                    "top_cpc_domains": llm_out["data"].get("top_cpc_domains") or [],
                    "filing_trend": filing_trend,
                    "geographic_coverage": geographic_coverage,
                    "status_breakdown": status_breakdown,
                    "portfolio_strength": llm_out["data"].get("portfolio_strength") or "",
                    "patents": patents,
                },
                sources=[],
                error=None,
                input_tokens=llm_out.get("input_tokens", 0),
                output_tokens=llm_out.get("output_tokens", 0),
                duration_ms=duration_ms,
            )
        ]
    }


# ---------------------------------------------------------------------------
# Phase 1 — programmatic extraction
# ---------------------------------------------------------------------------


def _extract_features(
    raw_patents: list[dict[str, Any]],
    families_payload: dict[str, Any],
    legal_payload: dict[str, Any],
) -> tuple[list[dict[str, Any]], dict[str, int], Counter, dict[str, int], dict[str, int]]:
    """Dedupe, compute yearly trend, CPC codes, and families / legal aggregates.

    Args:
        raw_patents: The list under ``epo_publications.patents``.
        families_payload: Full ``epo_families`` enrichment (``{"families": [...]}``)
            or ``{}`` when families were not collected.
        legal_payload: Full ``epo_legal`` enrichment (``{"legal_statuses": [...]}``)
            or ``{}`` when legal data was not collected.

    Returns:
        Tuple ``(patents, filing_trend, code_counts, geographic_coverage, status_breakdown)``:
            - ``patents``: normalised list of patent dicts ready for the
              persistence layer. Each dict carries ``patent_number``,
              bibliographic fields, ``cpc_codes``, ``family_size``,
              ``family_countries``, ``legal_status`` and
              ``is_key_patent`` (initially False).
            - ``filing_trend``: ``{year_str: count}`` — patents without a
              publication date are skipped.
            - ``code_counts``: :class:`~collections.Counter` of CPC codes
              across the deduped portfolio; empty when no family data.
            - ``geographic_coverage``: ``{country_code: count}`` summing
              family members across the portfolio; empty when no family
              data is available.
            - ``status_breakdown``: ``{legal_status: count}`` for items
              with a known simplified status; empty when no legal data.
    """
    cpc_by_doc_id = _index_cpc_by_doc_id(families_payload)
    family_meta_by_doc_id = _index_family_meta(families_payload)
    legal_by_doc_id = _index_legal(legal_payload)

    seen: set[str] = set()
    patents: list[dict[str, Any]] = []
    filing_trend: Counter[str] = Counter()
    code_counts: Counter[str] = Counter()
    geographic_coverage: Counter[str] = Counter()
    status_breakdown: Counter[str] = Counter()

    for raw in raw_patents:
        doc_id = raw.get("doc_id")
        if not doc_id or doc_id in seen:
            continue
        seen.add(doc_id)

        pub_date = raw.get("publication_date")
        year = pub_date[:4] if isinstance(pub_date, str) and len(pub_date) >= 4 and pub_date[:4].isdigit() else None
        if year is not None:
            filing_trend[year] += 1

        cpc_codes = list(cpc_by_doc_id.get(doc_id) or [])
        for code in cpc_codes:
            prefix = _cpc_prefix(code)
            if prefix:
                code_counts[prefix] += 1

        family_size, family_countries, member_countries = family_meta_by_doc_id.get(doc_id, (0, [], []))
        # geographic_coverage aggregates *every* family member: a patent
        # with two EP members and one US member contributes +2 to EP
        # and +1 to US (reflecting the portfolio's real footprint).
        for country in member_countries:
            geographic_coverage[country] += 1

        legal_status = legal_by_doc_id.get(doc_id)
        if legal_status:
            status_breakdown[legal_status] += 1

        patents.append(
            {
                "patent_number": doc_id,
                "title": raw.get("title"),
                "abstract": raw.get("abstract"),
                "inventors": list(raw.get("inventors") or []),
                "applicants": list(raw.get("applicants") or []),
                "publication_date": pub_date,
                "cpc_codes": cpc_codes,
                "family_size": family_size,
                "family_countries": family_countries,
                "legal_status": legal_status,
                "is_key_patent": False,
            }
        )

    # JSONB columns are dicts — convert Counters to plain dicts.
    return (
        patents,
        dict(filing_trend),
        code_counts,
        dict(geographic_coverage),
        dict(status_breakdown),
    )


def _index_cpc_by_doc_id(families_payload: dict[str, Any]) -> dict[str, list[str]]:
    """Build a ``{doc_id: cpc_classifications}`` index from ``epo_families``."""
    families = families_payload.get("families") if isinstance(families_payload, dict) else None
    if not families:
        return {}
    index: dict[str, list[str]] = {}
    for family in families:
        doc_id = family.get("doc_id")
        if not doc_id:
            continue
        index[doc_id] = list(family.get("cpc_classifications") or [])
    return index


def _index_family_meta(
    families_payload: dict[str, Any],
) -> dict[str, tuple[int, list[str], list[str]]]:
    """Build a ``{doc_id: (family_size, unique_countries, member_countries)}`` index.

    ``unique_countries`` is the sorted deduplicated list of country codes
    (used to populate ``CompanyPatentItem.family_countries``), while
    ``member_countries`` keeps every member's country so the caller can
    sum per-country coverage across the full portfolio.
    """
    families = families_payload.get("families") if isinstance(families_payload, dict) else None
    if not families:
        return {}

    index: dict[str, tuple[int, list[str], list[str]]] = {}
    for family in families:
        doc_id = family.get("doc_id")
        if not doc_id:
            continue
        members = family.get("family_members") or []
        member_countries: list[str] = []
        unique_countries: set[str] = set()
        for member in members:
            country = (member.get("country") or "").strip()
            if not country:
                continue
            member_countries.append(country)
            unique_countries.add(country)
        # family_size falls back to the number of parsed members when the
        # EPO payload omits the explicit integer (the pydantic model
        # defaults to len(members) anyway).
        raw_size = family.get("family_size")
        family_size = int(raw_size) if isinstance(raw_size, int) and raw_size > 0 else len(member_countries)
        index[doc_id] = (family_size, sorted(unique_countries), member_countries)
    return index


def _index_legal(legal_payload: dict[str, Any]) -> dict[str, str]:
    """Build a ``{doc_id: simplified_status}`` index from ``epo_legal``.

    Skips entries without a ``doc_id`` or without a simplified status —
    the caller treats missing entries as "legal data unavailable" rather
    than defaulting them to any specific bucket.
    """
    legal_statuses = legal_payload.get("legal_statuses") if isinstance(legal_payload, dict) else None
    if not legal_statuses:
        return {}
    index: dict[str, str] = {}
    for entry in legal_statuses:
        doc_id = entry.get("doc_id")
        status = entry.get("simplified_status")
        if doc_id and status:
            index[doc_id] = status
    return index


def _cpc_prefix(code: str) -> str | None:
    """Return the section+class+subclass prefix of a CPC/IPC symbol.

    CPC codes look like ``H04L 9/00`` or ``B64C``. We aggregate at the
    four-character subclass level (``H04L``, ``B64C``) which is the
    level the LLM can reliably label.
    """
    if not code:
        return None
    head = code.strip().split(" ", 1)[0]
    return head[:4] if len(head) >= 4 else (head or None)


# ---------------------------------------------------------------------------
# Phase 2 — LLM analysis
# ---------------------------------------------------------------------------


def _condense_for_prompt(patents: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Trim the patents list for the LLM prompt.

    Keeps the most recent ``MAX_PATENTS_IN_PROMPT`` entries (by
    publication_date desc, undated last) and truncates each abstract.
    Threads ``family_size``, ``family_countries`` and ``legal_status``
    so the LLM can reason about geographic reach and legal solidity of
    the representative sample.
    """
    ordered = sorted(
        patents,
        key=lambda p: (p.get("publication_date") is not None, p.get("publication_date") or ""),
        reverse=True,
    )
    condensed: list[dict[str, Any]] = []
    for p in ordered[:MAX_PATENTS_IN_PROMPT]:
        abstract = p.get("abstract")
        if isinstance(abstract, str) and len(abstract) > ABSTRACT_MAX_CHARS:
            abstract = abstract[:ABSTRACT_MAX_CHARS].rstrip() + "…"
        condensed.append(
            {
                "patent_number": p["patent_number"],
                "title": p.get("title"),
                "abstract": abstract,
                "publication_date": p.get("publication_date"),
                "cpc_codes": p.get("cpc_codes") or [],
                "family_size": int(p.get("family_size") or 0),
                "family_countries": list(p.get("family_countries") or []),
                "legal_status": p.get("legal_status"),
            }
        )
    return condensed


async def _analyze_with_llm(
    company_name: str,
    patents: list[dict[str, Any]],
    code_counts: Counter,
    geographic_coverage: dict[str, int],
    status_breakdown: dict[str, int],
) -> dict[str, Any]:
    """Issue the Phase 2 LLM call via Chat Completions and return parsed output.

    Uses the Chat Completions API directly (same pattern as the sanctions
    agent) so the ``web_search`` tool is never attached — the patents
    agent works exclusively from the structured EPO data passed in the
    user message. The response is constrained to JSON object mode and
    validated against :class:`PatentsAgentOutput`.

    Geographic coverage and legal status breakdown blocks are injected
    only when the corresponding aggregates carry data, matching the
    system prompt's graceful degradation clause for
    ``portfolio_strength``.
    """
    output_schema = AGENT_OUTPUT_SCHEMAS["patents"]

    condensed_patents = _condense_for_prompt(patents)
    top_codes = [{"code": code, "count": count} for code, count in code_counts.most_common(MAX_TOP_CODES)]

    sections = [
        f"Analyze the patent portfolio of {company_name}.",
        "## Top classification codes (across all patents)",
        json.dumps(top_codes, indent=2, ensure_ascii=False),
    ]

    if geographic_coverage:
        top_geo = dict(Counter(geographic_coverage).most_common(MAX_GEO_COUNTRIES_IN_PROMPT))
        sections.append("## Geographic coverage (family members per country, top 15)")
        sections.append(json.dumps(top_geo, indent=2, ensure_ascii=False))

    if status_breakdown:
        sections.append("## Legal status breakdown")
        sections.append(json.dumps(status_breakdown, indent=2, ensure_ascii=False))

    sections.append("## Patents to analyse (most recent first, truncated for brevity)")
    sections.append(json.dumps(condensed_patents, indent=2, ensure_ascii=False))
    sections.append("Produce the JSON object now.")

    user_query = "\n\n".join(sections)

    client = get_chat_client()
    response = await client.chat.completions.create(
        model=LLM_MODEL,
        messages=[
            {"role": "system", "content": PROMPTS_REGISTRY["patents"]},
            {"role": "user", "content": user_query},
        ],
        response_format={"type": "json_object"},
        temperature=0.1,
    )

    content = response.choices[0].message.content or "{}"
    parsed = output_schema.model_validate_json(content)

    return {
        "data": parsed.model_dump(exclude_none=True),
        "input_tokens": response.usage.prompt_tokens if response.usage else 0,
        "output_tokens": response.usage.completion_tokens if response.usage else 0,
    }


# ---------------------------------------------------------------------------
# Result builders
# ---------------------------------------------------------------------------


def _empty_success(company_name: str, start_time: float) -> dict:
    """AgentResult for the no-EPO-data path.

    The task still succeeds so downstream writers persist an empty
    section (insights stays empty, counts stay zero). This matches the
    acceptance criterion that the agent terminates cleanly without EPO
    data.
    """
    duration_ms = int((time.monotonic() - start_time) * 1000)
    return {
        "agent_results": [
            AgentResult(
                agent_name=AGENT_NAME,
                status="success",
                data={
                    "insights": "",
                    "total_patents_count": 0,
                    "top_cpc_domains": [],
                    "filing_trend": {},
                    "geographic_coverage": {},
                    "status_breakdown": {},
                    "portfolio_strength": "",
                    "patents": [],
                },
                sources=[],
                error=None,
                input_tokens=0,
                output_tokens=0,
                duration_ms=duration_ms,
            )
        ]
    }


def _error_result(
    company_name: str,
    patents: list[dict[str, Any]],
    filing_trend: dict[str, int],
    geographic_coverage: dict[str, int],
    status_breakdown: dict[str, int],
    error: str,
    duration_ms: int,
) -> dict:
    """AgentResult for an LLM failure: keep Phase 1 artefacts so the UI
    can still show counts / trend / coverage while flagging the error.
    """
    return {
        "agent_results": [
            AgentResult(
                agent_name=AGENT_NAME,
                status="error",
                data={
                    "insights": "",
                    "total_patents_count": len(patents),
                    "top_cpc_domains": [],
                    "filing_trend": filing_trend,
                    "geographic_coverage": geographic_coverage,
                    "status_breakdown": status_breakdown,
                    "portfolio_strength": "",
                    "patents": patents,
                },
                sources=[],
                error=error,
                input_tokens=0,
                output_tokens=0,
                duration_ms=duration_ms,
            )
        ]
    }
