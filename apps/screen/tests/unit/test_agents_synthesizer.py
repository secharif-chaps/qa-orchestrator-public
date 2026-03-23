"""Tests for synthesizer node: quality gate and retry logic.

Covers: _count_attempts, _calculate_cost, synthesizer_node.
"""

import pytest

from app.agents.config import (
    INPUT_PRICE_PER_MILLION,
    MAX_AGENT_RETRIES,
    OUTPUT_PRICE_PER_MILLION,
)
from app.agents.nodes.synthesizer import (
    _calculate_cost,
    _count_attempts,
    synthesizer_node,
)
from app.agents.state import AgentResult

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def _make_result(agent_name: str, status: str = "success", **overrides) -> AgentResult:
    base = AgentResult(
        agent_name=agent_name,
        status=status,
        data={"key": "value"} if status == "success" else {},
        sources=["https://example.com"] if status == "success" else [],
        error=None if status == "success" else "some error",
        input_tokens=100,
        output_tokens=50,
        duration_ms=1000,
    )
    base.update(overrides)
    return base


def _make_state(**overrides) -> dict:
    base = {
        "company_id": 1,
        "company_name": "Acme",
        "website": "https://acme.com",
        "organization_id": "org-1",
        "owner_id": "user-1",
        "country_code": None,
        "company_brief": None,
        "agents_to_run": [],
        "agent_results": [],
        "quality_issues": [],
        "agents_to_retry": [],
        "retry_counts": {},
        "total_tokens": 0,
        "total_cost": 0.0,
    }
    base.update(overrides)
    return base


# ---------------------------------------------------------------------------
# _count_attempts
# ---------------------------------------------------------------------------


class TestCountAttempts:
    """Tests for attempt counter."""

    def test_no_matches(self):
        results = [_make_result("press"), _make_result("digital")]
        assert _count_attempts(results, "profile") == 0

    def test_single_match(self):
        results = [_make_result("profile"), _make_result("digital")]
        assert _count_attempts(results, "profile") == 1

    def test_multiple_matches(self):
        results = [
            _make_result("profile"),
            _make_result("digital"),
            _make_result("profile", status="error"),
        ]
        assert _count_attempts(results, "profile") == 2

    def test_empty_list(self):
        assert _count_attempts([], "profile") == 0


# ---------------------------------------------------------------------------
# _calculate_cost
# ---------------------------------------------------------------------------


class TestCalculateCost:
    """Tests for cost calculation."""

    def test_standard_calculation(self):
        cost = _calculate_cost(1_000_000, 1_000_000)
        expected = INPUT_PRICE_PER_MILLION + OUTPUT_PRICE_PER_MILLION
        assert cost == pytest.approx(expected)

    def test_zero_tokens(self):
        assert _calculate_cost(0, 0) == 0.0

    def test_input_only(self):
        cost = _calculate_cost(500_000, 0)
        expected = (500_000 / 1_000_000) * INPUT_PRICE_PER_MILLION
        assert cost == pytest.approx(expected)

    def test_output_only(self):
        cost = _calculate_cost(0, 250_000)
        expected = (250_000 / 1_000_000) * OUTPUT_PRICE_PER_MILLION
        assert cost == pytest.approx(expected)

    def test_small_token_count(self):
        cost = _calculate_cost(100, 50)
        expected = (100 * INPUT_PRICE_PER_MILLION + 50 * OUTPUT_PRICE_PER_MILLION) / 1_000_000
        assert cost == pytest.approx(expected)


# ---------------------------------------------------------------------------
# synthesizer_node
# ---------------------------------------------------------------------------


class TestSynthesizerNode:
    """Tests for the async synthesizer node."""

    @pytest.mark.asyncio
    async def test_all_agents_succeeded(self):
        results = [_make_result("profile"), _make_result("digital")]
        state = _make_state(
            agents_to_run=["profile", "digital"],
            agent_results=results,
        )
        out = await synthesizer_node(state)

        assert out["agents_to_retry"] == []
        assert out["quality_issues"] == []
        assert out["total_tokens"] == 300  # (100+50)*2
        assert out["total_cost"] == pytest.approx(_calculate_cost(200, 100))

    @pytest.mark.asyncio
    async def test_agent_with_error_below_max_retries(self):
        results = [_make_result("profile", status="error")]
        state = _make_state(
            agents_to_run=["profile"],
            agent_results=results,
        )
        out = await synthesizer_node(state)

        assert "profile" in out["agents_to_retry"]
        assert len(out["quality_issues"]) == 1
        assert "profile" in out["quality_issues"][0]

    @pytest.mark.asyncio
    async def test_agent_with_error_at_max_retries(self):
        """Agent that has exhausted retry_counts should NOT be retried."""
        results = [_make_result("profile", status="error")]
        state = _make_state(
            agents_to_run=["profile"],
            agent_results=results,
            retry_counts={"profile": MAX_AGENT_RETRIES},
        )
        out = await synthesizer_node(state)

        assert "profile" not in out["agents_to_retry"]
        assert len(out["quality_issues"]) == 1

    @pytest.mark.asyncio
    async def test_mixed_results(self):
        results = [
            _make_result("profile"),
            _make_result("digital", status="error"),
            _make_result("press"),
        ]
        state = _make_state(
            agents_to_run=["profile", "digital", "press"],
            agent_results=results,
        )
        out = await synthesizer_node(state)

        assert "digital" in out["agents_to_retry"]
        assert "profile" not in out["agents_to_retry"]
        assert "press" not in out["agents_to_retry"]

    @pytest.mark.asyncio
    async def test_token_aggregation(self):
        results = [
            _make_result("profile", input_tokens=200, output_tokens=100),
            _make_result("digital", input_tokens=300, output_tokens=150),
        ]
        state = _make_state(
            agents_to_run=["profile", "digital"],
            agent_results=results,
        )
        out = await synthesizer_node(state)

        assert out["total_tokens"] == 750  # 200+100+300+150

    @pytest.mark.asyncio
    async def test_cost_calculation_matches(self):
        results = [
            _make_result("profile", input_tokens=500, output_tokens=200),
        ]
        state = _make_state(
            agents_to_run=["profile"],
            agent_results=results,
        )
        out = await synthesizer_node(state)

        expected_cost = _calculate_cost(500, 200)
        assert out["total_cost"] == pytest.approx(expected_cost)

    @pytest.mark.asyncio
    async def test_no_result_for_agent(self):
        """Agent in agents_to_run with no matching result should NOT be retried."""
        state = _make_state(
            agents_to_run=["profile"],
            agent_results=[],
        )
        out = await synthesizer_node(state)

        assert "profile" not in out["agents_to_retry"]
        assert any("profile" in qi and "no result" in qi for qi in out["quality_issues"])

    @pytest.mark.asyncio
    async def test_empty_state(self):
        state = _make_state(agents_to_run=[], agent_results=[])
        out = await synthesizer_node(state)

        assert out["agents_to_retry"] == []
        assert out["quality_issues"] == []
        assert out["total_tokens"] == 0
        assert out["total_cost"] == 0.0
