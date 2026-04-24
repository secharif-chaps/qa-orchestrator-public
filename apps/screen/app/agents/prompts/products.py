from app.agents.prompts.shared.methodology import RESEARCH_METHODOLOGY
from app.agents.prompts.shared.output_formats import AGENT_OUTPUT_FORMATS
from app.agents.prompts.shared.template import STANDARD_TEMPLATE

ROLE = "You identify the company's products, services, and key offerings including pricing and market positioning."
SEARCH_TARGETS = """Search the company's products/services pages, product review sites, comparison platforms, app stores, and industry analyst reports."""

VARS = {
    "role": ROLE,
    "targets": SEARCH_TARGETS,
    "methodology": RESEARCH_METHODOLOGY,
    "output_format": AGENT_OUTPUT_FORMATS["products"],
}

PROMPT = STANDARD_TEMPLATE.format_map(VARS)
