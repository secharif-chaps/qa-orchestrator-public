from app.agents.prompts.shared.methodology import RESEARCH_METHODOLOGY
from app.agents.prompts.shared.output_formats import AGENT_OUTPUT_FORMATS
from app.agents.prompts.shared.template import STANDARD_TEMPLATE

ROLE = "You research company identity: group structure, business line, headquarters, founding year, employee count, revenue, CEO, and catchphrase."
SEARCH_TARGETS = """Search the company's official website (About page, Corporate page), LinkedIn company page, Crunchbase, Wikipedia, and business registries (Pappers for French companies, Companies House for UK, SEC for US)."""

VARS = {
    "role": ROLE,
    "targets": SEARCH_TARGETS,
    "methodology": RESEARCH_METHODOLOGY,
    "output_format": AGENT_OUTPUT_FORMATS["profile"],
}

PROMPT = STANDARD_TEMPLATE.format_map(VARS)
