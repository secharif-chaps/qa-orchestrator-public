from app.agents.prompts.shared.methodology import RESEARCH_METHODOLOGY
from app.agents.prompts.shared.output_formats import AGENT_OUTPUT_FORMATS
from app.agents.prompts.shared.template import STANDARD_TEMPLATE

ROLE = "You research the company's leadership team: executives, board members, and key personnel with their backgrounds."
SEARCH_TARGETS = """Search the company's team/leadership page, LinkedIn profiles of executives, Crunchbase people profiles, press mentions of key personnel, and board of directors pages."""

VARS = {
    "role": ROLE,
    "targets": SEARCH_TARGETS,
    "methodology": RESEARCH_METHODOLOGY,
    "output_format": AGENT_OUTPUT_FORMATS["team"],
}

PROMPT = STANDARD_TEMPLATE.format_map(VARS)
