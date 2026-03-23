"""State definitions for the company analysis graph."""

from typing import Annotated, TypedDict


class AgentResult(TypedDict):
    """Result from a single agent execution."""

    agent_name: str
    status: str  # "success" | "error"
    data: dict
    sources: list[str]
    error: str | None
    input_tokens: int
    output_tokens: int
    duration_ms: int


def _merge_agent_results(
    existing: list[AgentResult],
    new: list[AgentResult],
) -> list[AgentResult]:
    """Append-only reducer for agent results.

    Each agent node returns {"agent_results": [result]}.
    The reducer appends it to the existing list.
    Retried agents accumulate without overwriting original results.
    """
    return existing + new


class CompanyAnalysisState(TypedDict):
    """Full state for the company analysis graph."""

    company_id: int
    company_name: str
    website: str
    organization_id: str
    owner_id: str
    country_code: str | None
    company_brief: str | None
    agents_to_run: list[str]
    agent_results: Annotated[list[AgentResult], _merge_agent_results]
    quality_issues: list[str]
    agents_to_retry: list[str]
    retry_counts: dict[str, int]
    total_tokens: int
    total_cost: float
