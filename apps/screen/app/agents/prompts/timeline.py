from app.agents.prompts.shared.methodology import RESEARCH_METHODOLOGY
from app.agents.prompts.shared.output_formats import AGENT_OUTPUT_FORMATS
from app.agents.prompts.shared.template import STANDARD_TEMPLATE

ROLE = "You research the company's history: key milestones, founding events, acquisitions, partnerships, and major developments."
SEARCH_TARGETS = """Search Wikipedia, Crunchbase, the company's history/about page, press archives, and business databases for chronological milestones."""

VARS = {
    "role": ROLE,
    "targets": SEARCH_TARGETS,
    "methodology": RESEARCH_METHODOLOGY,
    "output_format": AGENT_OUTPUT_FORMATS["timeline"],
}

PROMPT = STANDARD_TEMPLATE.format_map(VARS)
