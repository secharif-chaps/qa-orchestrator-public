"""Synthesizer node: quality gate and retry routing."""

from app.agents.config import (
    INPUT_PRICE_PER_MILLION,
    MAX_AGENT_RETRIES,
    OUTPUT_PRICE_PER_MILLION,
)
from app.agents.state import AgentResult, CompanyAnalysisState
from app.core.logging_config import get_logger

logger = get_logger(__name__)


def _count_attempts(agent_results: list[AgentResult], agent_name: str) -> int:
    """Count how many times an agent has been executed."""
    return sum(1 for r in agent_results if r["agent_name"] == agent_name)


def _calculate_cost(input_tokens: int, output_tokens: int) -> float:
    """Calculate cost from token counts."""
    return (input_tokens / 1_000_000) * INPUT_PRICE_PER_MILLION + (output_tokens / 1_000_000) * OUTPUT_PRICE_PER_MILLION


async def synthesizer_node(state: CompanyAnalysisState) -> dict:
    """Evaluate agent results, calculate totals, route retries.

    Checks each agent's latest result. Failed agents with fewer than
    MAX_AGENT_RETRIES attempts are queued for retry.
    """
    agent_results = state.get("agent_results", [])
    agents_to_run = state.get("agents_to_run", [])

    # Calculate totals
    total_input = sum(r["input_tokens"] for r in agent_results)
    total_output = sum(r["output_tokens"] for r in agent_results)
    total_cost = _calculate_cost(total_input, total_output)
    total_tokens = total_input + total_output

    # Check for failed agents eligible for retry
    quality_issues = []
    agents_to_retry = []
    retry_counts = dict(state.get("retry_counts", {}))

    for agent_name in agents_to_run:
        # Get the latest result for this agent
        agent_runs = [r for r in agent_results if r["agent_name"] == agent_name]
        if not agent_runs:
            quality_issues.append(f"{agent_name}: no result received")
            continue

        latest = agent_runs[-1]
        if latest["status"] == "error":
            quality_issues.append(f"{agent_name}: {latest.get('error', 'unknown error')}")
            retries_so_far = retry_counts.get(agent_name, 0)
            if retries_so_far < MAX_AGENT_RETRIES:
                agents_to_retry.append(agent_name)
                retry_counts[agent_name] = retries_so_far + 1

    # Counts
    succeeded = sum(
        1 for name in agents_to_run if any(r["agent_name"] == name and r["status"] == "success" for r in agent_results)
    )
    failed = len(agents_to_run) - succeeded

    logger.info(
        "Synthesizer completed",
        extra={
            "company_id": state.get("company_id"),
            "succeeded": succeeded,
            "failed": failed,
            "total_tokens": total_tokens,
            "total_cost": round(total_cost, 4),
            "retrying": agents_to_retry,
        },
    )

    return {
        "quality_issues": quality_issues,
        "agents_to_retry": agents_to_retry,
        "retry_counts": retry_counts,
        "total_tokens": total_tokens,
        "total_cost": total_cost,
    }
