from app.agents.prompts.shared.methodology import RESEARCH_METHODOLOGY
from app.agents.prompts.shared.output_formats import AGENT_OUTPUT_FORMATS
from app.agents.prompts.shared.template import STANDARD_TEMPLATE

ROLE = "You research the company's job market: open positions, hiring trends, employer reputation, and workplace culture."
SEARCH_TARGETS = """Search the company's careers page, LinkedIn jobs, Glassdoor, Indeed, Welcome to the Jungle, and other job boards. Look for hiring trends and employer reviews."""

VARS = {
    "role": ROLE,
    "targets": SEARCH_TARGETS,
    "methodology": RESEARCH_METHODOLOGY,
    "output_format": AGENT_OUTPUT_FORMATS["jobs"],
}

PROMPT = STANDARD_TEMPLATE.format_map(VARS)
