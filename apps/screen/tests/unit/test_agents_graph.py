"""Tests for LangGraph state graph definition.

Covers: AGENT_NODE_MAP, _route_to_agents, _route_after_synthesis, analysis_graph.
"""

from langgraph.constants import END, Send

from app.agents.config import ALL_AGENT_TYPES
from app.agents.graph import (
    AGENT_NODE_MAP,
    _route_after_synthesis,
    _route_to_agents,
    analysis_graph,
)

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def _make_state(**overrides) -> dict:
    """Build a minimal CompanyAnalysisState dict with sensible defaults."""
    base = {
        "company_id": 1,
        "company_name": "Acme Corp",
        "website": "https://acme.com",
        "organization_id": "org-1",
        "owner_id": "user-1",
        "country_code": None,
        "company_brief": None,
        "agents_to_run": [],
        "agent_results": [],
        "quality_issues": [],
        "agents_to_retry": [],
        "total_tokens": 0,
        "total_cost": 0.0,
    }
    base.update(overrides)
    return base


# ---------------------------------------------------------------------------
# AGENT_NODE_MAP
# ---------------------------------------------------------------------------


class TestAgentNodeMap:
    """Tests for the AGENT_NODE_MAP constant."""

    def test_all_agent_types_present(self):
        """All 9 agent types have entries."""
        for agent_type in ALL_AGENT_TYPES:
            assert agent_type in AGENT_NODE_MAP

    def test_entry_count_matches_config(self):
        assert len(AGENT_NODE_MAP) == len(ALL_AGENT_TYPES)

    def test_values_are_callable(self):
        for name, fn in AGENT_NODE_MAP.items():
            assert callable(fn), f"{name} node function is not callable"

    def test_expected_agent_names(self):
        expected = {
            "profile",
            "digital",
            "press",
            "jobs",
            "products",
            "timeline",
            "csr",
            "team",
            "corporate_structure",
            "sanctions",
            "financial",
            "patents",
        }
        assert set(AGENT_NODE_MAP.keys()) == expected


# ---------------------------------------------------------------------------
# _route_to_agents
# ---------------------------------------------------------------------------


class TestRouteToAgents:
    """Tests for the fan-out routing function."""

    def test_returns_send_per_agent(self):
        state = _make_state(agents_to_run=["profile", "digital", "press"])
        result = _route_to_agents(state)

        assert len(result) == 3
        assert all(isinstance(s, Send) for s in result)

    def test_send_targets_correct_node_names(self):
        state = _make_state(agents_to_run=["profile", "jobs"])
        result = _route_to_agents(state)

        node_names = [s.node for s in result]
        assert node_names == ["agent_profile", "agent_jobs"]

    def test_send_carries_full_state(self):
        state = _make_state(agents_to_run=["csr"])
        result = _route_to_agents(state)

        assert result[0].arg == state

    def test_empty_agents_to_run_returns_empty_list(self):
        state = _make_state(agents_to_run=[])
        result = _route_to_agents(state)

        assert result == []

    def test_all_agents(self):
        state = _make_state(agents_to_run=list(ALL_AGENT_TYPES))
        result = _route_to_agents(state)

        assert len(result) == len(ALL_AGENT_TYPES)
        expected_nodes = [f"agent_{name}" for name in ALL_AGENT_TYPES]
        assert [s.node for s in result] == expected_nodes


# ---------------------------------------------------------------------------
# _route_after_synthesis
# ---------------------------------------------------------------------------


class TestRouteAfterSynthesis:
    """Tests for the post-synthesizer routing function."""

    def test_retry_non_empty_returns_sends(self):
        state = _make_state(agents_to_retry=["press", "jobs"])
        result = _route_after_synthesis(state)

        assert isinstance(result, list)
        assert len(result) == 2
        assert all(isinstance(s, Send) for s in result)
        assert [s.node for s in result] == ["agent_press", "agent_jobs"]

    def test_retry_empty_returns_end(self):
        state = _make_state(agents_to_retry=[])
        result = _route_after_synthesis(state)

        assert result == END

    def test_no_agents_to_retry_key_returns_end(self):
        """When agents_to_retry is missing (falsy), returns END."""
        state = _make_state()
        # Explicitly remove the key to test .get() fallback
        state.pop("agents_to_retry", None)
        result = _route_after_synthesis(state)

        assert result == END

    def test_single_retry_agent(self):
        state = _make_state(agents_to_retry=["team"])
        result = _route_after_synthesis(state)

        assert len(result) == 1
        assert result[0].node == "agent_team"


# ---------------------------------------------------------------------------
# analysis_graph (sync compiled fallback)
# ---------------------------------------------------------------------------


class TestAnalysisGraph:
    """Tests for the sync compiled graph."""

    def test_returns_compiled_graph(self):
        assert hasattr(analysis_graph, "invoke")

    def test_graph_has_expected_nodes(self):
        node_names = set(analysis_graph.nodes.keys())

        # Should have planner, synthesizer, and all 9 agent nodes
        expected = {"planner", "synthesizer", "__start__"}
        expected |= {f"agent_{name}" for name in ALL_AGENT_TYPES}

        assert expected.issubset(node_names)

    def test_graph_has_all_agent_nodes(self):
        node_names = set(analysis_graph.nodes.keys())

        for agent_name in ALL_AGENT_TYPES:
            assert f"agent_{agent_name}" in node_names

    def test_planner_is_entry_point(self):
        """Planner should be reachable from __start__."""
        assert "__start__" in analysis_graph.nodes
