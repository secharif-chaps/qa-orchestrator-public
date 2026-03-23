"""Tests for all 8 agent node wrappers.

Each node follows the identical pattern: call run_agent with the correct
agent_name, company_name, website, company_brief, and country_code, then
return {"agent_results": [result]}.

Uses parametrize to test all 8 nodes with a single test class.
"""

from unittest.mock import AsyncMock, patch

import pytest

from app.agents.nodes.csr import run_csr_agent
from app.agents.nodes.digital import run_digital_agent
from app.agents.nodes.jobs import run_jobs_agent
from app.agents.nodes.press import run_press_agent
from app.agents.nodes.products import run_products_agent
from app.agents.nodes.profile import run_profile_agent
from app.agents.nodes.team import run_team_agent
from app.agents.nodes.timeline import run_timeline_agent
from app.agents.state import AgentResult

# Map agent name → (node function, module path for patching)
AGENT_NODES = [
    ("profile", run_profile_agent, "app.agents.nodes.profile"),
    ("digital", run_digital_agent, "app.agents.nodes.digital"),
    ("press", run_press_agent, "app.agents.nodes.press"),
    ("jobs", run_jobs_agent, "app.agents.nodes.jobs"),
    ("products", run_products_agent, "app.agents.nodes.products"),
    ("timeline", run_timeline_agent, "app.agents.nodes.timeline"),
    ("csr", run_csr_agent, "app.agents.nodes.csr"),
    ("team", run_team_agent, "app.agents.nodes.team"),
]


def _make_state(**overrides) -> dict:
    base = {
        "company_id": 1,
        "company_name": "TestCo",
        "website": "https://testco.com",
        "organization_id": "org-1",
        "owner_id": "user-1",
        "country_code": "FR",
        "company_brief": "A test company",
        "agents_to_run": [],
        "agent_results": [],
        "quality_issues": [],
        "agents_to_retry": [],
        "total_tokens": 0,
        "total_cost": 0.0,
    }
    base.update(overrides)
    return base


def _mock_agent_result(agent_name: str) -> AgentResult:
    return AgentResult(
        agent_name=agent_name,
        status="success",
        data={"key": "value"},
        sources=["https://example.com"],
        error=None,
        input_tokens=100,
        output_tokens=50,
        duration_ms=1000,
    )


class TestAgentNodes:
    """Parametrized tests for all 8 agent node wrappers."""

    @pytest.mark.asyncio
    @pytest.mark.parametrize("agent_name,node_fn,module_path", AGENT_NODES)
    async def test_calls_run_agent_with_correct_params(self, agent_name, node_fn, module_path):
        mock_result = _mock_agent_result(agent_name)

        with patch(f"{module_path}.run_agent", new_callable=AsyncMock) as mock_run:
            mock_run.return_value = mock_result
            state = _make_state()
            await node_fn(state)

            mock_run.assert_called_once_with(
                agent_name=agent_name,
                company_name="TestCo",
                website="https://testco.com",
                company_brief="A test company",
                country_code="FR",
            )

    @pytest.mark.asyncio
    @pytest.mark.parametrize("agent_name,node_fn,module_path", AGENT_NODES)
    async def test_returns_agent_results_list(self, agent_name, node_fn, module_path):
        mock_result = _mock_agent_result(agent_name)

        with patch(f"{module_path}.run_agent", new_callable=AsyncMock) as mock_run:
            mock_run.return_value = mock_result
            output = await node_fn(_make_state())

            assert "agent_results" in output
            assert isinstance(output["agent_results"], list)
            assert len(output["agent_results"]) == 1
            assert output["agent_results"][0] == mock_result

    @pytest.mark.asyncio
    @pytest.mark.parametrize("agent_name,node_fn,module_path", AGENT_NODES)
    async def test_missing_optional_state_keys(self, agent_name, node_fn, module_path):
        """When company_brief and country_code are missing, passes None."""
        mock_result = _mock_agent_result(agent_name)
        state = _make_state(company_brief=None, country_code=None)

        with patch(f"{module_path}.run_agent", new_callable=AsyncMock) as mock_run:
            mock_run.return_value = mock_result
            await node_fn(state)

            mock_run.assert_called_once_with(
                agent_name=agent_name,
                company_name="TestCo",
                website="https://testco.com",
                company_brief=None,
                country_code=None,
            )

    @pytest.mark.asyncio
    @pytest.mark.parametrize("agent_name,node_fn,module_path", AGENT_NODES)
    async def test_state_without_optional_keys(self, agent_name, node_fn, module_path):
        """State dict with no company_brief or country_code keys uses .get() default."""
        mock_result = _mock_agent_result(agent_name)
        state = _make_state()
        state.pop("company_brief", None)
        state.pop("country_code", None)

        with patch(f"{module_path}.run_agent", new_callable=AsyncMock) as mock_run:
            mock_run.return_value = mock_result
            await node_fn(state)

            mock_run.assert_called_once_with(
                agent_name=agent_name,
                company_name="TestCo",
                website="https://testco.com",
                company_brief=None,
                country_code=None,
            )
