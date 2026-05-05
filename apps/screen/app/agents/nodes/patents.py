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

    raw_patents = publications.get("patents") or []
    if not raw_patents:
        logger.info(
            "Patents agent: no EPO publications data, returning empty success",
            extra={"company_name": company_name},
        )
        return _empty_success(company_name, start_time)

    # Phase 1: programmatic extraction.
    patents, filing_trend, code_counts = _extract_features(raw_patents, families_payload)

    # Phase 2: LLM analysis (timeout-bounded, errors swallowed into status="error").
    try:
        llm_out = await asyncio.wait_for(
            _analyze_with_llm(
                company_name=company_name,
                patents=patents,
                code_counts=code_counts,
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
) -> tuple[list[dict[str, Any]], dict[str, int], Counter]:
    """Dedupe, compute yearly trend, and index CPC codes from families.

    Args:
        raw_patents: The list under ``epo_publications.patents``.
        families_payload: Full ``epo_families`` enrichment (``{"families": [...]}``)
            or ``{}`` when families were not collected.

    Returns:
        Tuple ``(patents, filing_trend, code_counts)``:
            - ``patents``: normalised list of patent dicts ready for the
              persistence layer (doc_id under ``patent_number`` + all
              supporting fields + empty ``is_key_patent``).
            - ``filing_trend``: ``{year_str: count}`` — patents without a
              publication date are skipped.
            - ``code_counts``: :class:`~collections.Counter` of CPC codes
              across the deduped portfolio; empty when no family data.
    """
    cpc_by_doc_id = _index_cpc_by_doc_id(families_payload)

    seen: set[str] = set()
    patents: list[dict[str, Any]] = []
    filing_trend: Counter[str] = Counter()
    code_counts: Counter[str] = Counter()

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

        patents.append(
            {
                "patent_number": doc_id,
                "title": raw.get("title"),
                "abstract": raw.get("abstract"),
                "inventors": list(raw.get("inventors") or []),
                "applicants": list(raw.get("applicants") or []),
                "publication_date": pub_date,
                "cpc_codes": cpc_codes,
                "is_key_patent": False,
            }
        )

    # JSONB column is a dict — convert Counter to plain dict.
    return patents, dict(filing_trend), code_counts


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
            }
        )
    return condensed


async def _analyze_with_llm(
    company_name: str,
    patents: list[dict[str, Any]],
    code_counts: Counter,
) -> dict[str, Any]:
    """Issue the Phase 2 LLM call via Chat Completions and return parsed output.

    Uses the Chat Completions API directly (same pattern as the sanctions
    agent) so the ``web_search`` tool is never attached — the patents
    agent works exclusively from the structured EPO data passed in the
    user message. The response is constrained to JSON object mode and
    validated against :class:`PatentsAgentOutput`.
    """
    output_schema = AGENT_OUTPUT_SCHEMAS["patents"]

    condensed_patents = _condense_for_prompt(patents)
    top_codes = [{"code": code, "count": count} for code, count in code_counts.most_common(MAX_TOP_CODES)]

    user_query = (
        f"Analyze the patent portfolio of {company_name}.\n\n"
        "## Top classification codes (across all patents)\n"
        f"{json.dumps(top_codes, indent=2, ensure_ascii=False)}\n\n"
        "## Patents to analyse (most recent first, truncated for brevity)\n"
        f"{json.dumps(condensed_patents, indent=2, ensure_ascii=False)}\n\n"
        "Produce the JSON object now."
    )

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
    error: str,
    duration_ms: int,
) -> dict:
    """AgentResult for an LLM failure: keep Phase 1 artefacts so the UI
    can still show counts / trend while flagging the error.
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
