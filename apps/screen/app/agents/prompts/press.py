from app.agents.prompts.shared.methodology import RESEARCH_METHODOLOGY
from app.agents.prompts.shared.output_formats import AGENT_OUTPUT_FORMATS
from app.agents.prompts.shared.template import STANDARD_TEMPLATE

ROLE = "You find recent press coverage, news articles, and media mentions about the company."
SEARCH_TARGETS = """Search Google News, major business publications (Reuters, Bloomberg, TechCrunch, Les Echos), the company's press/newsroom page, and industry-specific publications."""

VARS = {
    "role": ROLE,
    "targets": SEARCH_TARGETS,
    "methodology": RESEARCH_METHODOLOGY,
    "output_format": AGENT_OUTPUT_FORMATS["press"],
}

PROMPT = STANDARD_TEMPLATE.format_map(VARS)
