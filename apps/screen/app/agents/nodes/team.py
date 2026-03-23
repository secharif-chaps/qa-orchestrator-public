"""Team agent node."""

from app.agents.nodes.base import run_agent
from app.agents.state import CompanyAnalysisState


async def run_team_agent(state: CompanyAnalysisState) -> dict:
    """Research leadership team and key personnel."""
    result = await run_agent(
        agent_name="team",
        company_name=state["company_name"],
        website=state["website"],
        company_brief=state.get("company_brief"),
        country_code=state.get("country_code"),
    )
    return {"agent_results": [result]}
