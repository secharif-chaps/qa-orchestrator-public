"""Profile agent node."""

from app.agents.nodes.base import run_agent
from app.agents.state import CompanyAnalysisState


async def run_profile_agent(state: CompanyAnalysisState) -> dict:
    """Research company identity and profile."""
    # Pass enrichment sources so the agent can use the get_enrichment_data tool
    enrichment = state.get("enrichment_data", {})
    sources = [s for s in enrichment if s in ("pappers", "worldcheck", "epo_publications", "epo_families", "epo_legal")]

    result = await run_agent(
        agent_name="profile",
        company_name=state["company_name"],
        website=state["website"],
        company_brief=state.get("company_brief"),
        country_code=state.get("country_code"),
        enrichment_sources=sources if sources else None,
        company_id=state["company_id"] if sources else None,
    )
    return {"agent_results": [result]}
