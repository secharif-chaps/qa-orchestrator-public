"""LangGraph state graph definition for company analysis."""

from collections.abc import Callable

from langgraph.constants import END, Send
from langgraph.graph import StateGraph

from app.agents.nodes.corporate_structure import run_corporate_structure_agent
from app.agents.nodes.csr import run_csr_agent
from app.agents.nodes.data_collector import data_collector_node
from app.agents.nodes.digital import run_digital_agent
from app.agents.nodes.financial import run_financial_agent
from app.agents.nodes.jobs import run_jobs_agent
from app.agents.nodes.patents import run_patents_agent
from app.agents.nodes.planner import planner_node
from app.agents.nodes.press import run_press_agent
from app.agents.nodes.products import run_products_agent
from app.agents.nodes.profile import run_profile_agent
from app.agents.nodes.sanctions import run_sanctions_agent
from app.agents.nodes.synthesizer import synthesizer_node
from app.agents.nodes.team import run_team_agent
from app.agents.nodes.timeline import run_timeline_agent
from app.agents.state import CompanyAnalysisState

# Maps agent names to node functions
AGENT_NODE_MAP: dict[str, Callable] = {
    "profile": run_profile_agent,
    "digital": run_digital_agent,
    "press": run_press_agent,
    "jobs": run_jobs_agent,
    "products": run_products_agent,
    "timeline": run_timeline_agent,
    "csr": run_csr_agent,
    "team": run_team_agent,
    "corporate_structure": run_corporate_structure_agent,
    "sanctions": run_sanctions_agent,
    "financial": run_financial_agent,
    "patents": run_patents_agent,
}


def _route_to_agents(state: CompanyAnalysisState) -> list[Send]:
    """Fan-out: create a Send per agent in agents_to_run."""
    return [Send(f"agent_{agent_name}", state) for agent_name in state["agents_to_run"]]


def _route_after_synthesis(state: CompanyAnalysisState) -> list[Send] | str:
    """Route after synthesizer: retry failed agents or finish."""
    if state.get("agents_to_retry"):
        return [Send(f"agent_{name}", state) for name in state["agents_to_retry"]]
    return END


def _build_graph_definition() -> StateGraph:
    """Build the graph definition (uncompiled).

    Graph structure:
        START → planner → data_collector → [agent_profile, agent_digital, ...] → synthesizer → END
                                                                                      ↓ (retry)
                                                                               [agent_X, ...] → synthesizer → END
    """
    graph = StateGraph(CompanyAnalysisState)

    # Add nodes
    graph.add_node("planner", planner_node)
    graph.add_node("data_collector", data_collector_node)
    graph.add_node("synthesizer", synthesizer_node)

    for agent_name, node_fn in AGENT_NODE_MAP.items():
        graph.add_node(f"agent_{agent_name}", node_fn)

    # Edges
    graph.set_entry_point("planner")
    graph.add_edge("planner", "data_collector")
    graph.add_conditional_edges("data_collector", _route_to_agents)

    for agent_name in AGENT_NODE_MAP:
        graph.add_edge(f"agent_{agent_name}", "synthesizer")

    graph.add_conditional_edges("synthesizer", _route_after_synthesis)

    return graph


# Uncompiled graph definition (shared)
_graph_definition = _build_graph_definition()

# Lazy-initialized compiled graph with checkpointer
_compiled_graph = None


async def get_analysis_graph():
    """Get the compiled analysis graph with checkpoint persistence.

    Lazily initializes the checkpointer and compiles on first call.
    """
    global _compiled_graph
    if _compiled_graph is not None:
        return _compiled_graph

    from app.agents.checkpoint import get_checkpointer

    checkpointer = await get_checkpointer()
    _compiled_graph = _graph_definition.compile(checkpointer=checkpointer)
    return _compiled_graph


# Sync fallback for tests or contexts without checkpointer
analysis_graph = _graph_definition.compile()
