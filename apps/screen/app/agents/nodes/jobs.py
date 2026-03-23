"""Jobs agent node."""

from app.agents.nodes.base import run_agent
from app.agents.state import CompanyAnalysisState


async def run_jobs_agent(state: CompanyAnalysisState) -> dict:
    """Research job market and hiring trends."""
    result = await run_agent(
        agent_name="jobs",
        company_name=state["company_name"],
        website=state["website"],
        company_brief=state.get("company_brief"),
        country_code=state.get("country_code"),
    )
    return {"agent_results": [result]}
