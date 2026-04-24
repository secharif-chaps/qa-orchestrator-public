from app.agents.prompts.shared.methodology import RESEARCH_METHODOLOGY
from app.agents.prompts.shared.output_formats import AGENT_OUTPUT_FORMATS
from app.agents.prompts.shared.template import STANDARD_TEMPLATE

ROLE = "You research the company's CSR and sustainability initiatives: environmental commitments, social programs, and ESG reporting."
SEARCH_TARGETS = """Search the company's CSR/sustainability page, ESG reports, sustainability databases (CDP, B Corp), press releases about environmental/social initiatives."""

VARS = {
    "role": ROLE,
    "targets": SEARCH_TARGETS,
    "methodology": RESEARCH_METHODOLOGY,
    "output_format": AGENT_OUTPUT_FORMATS["csr"],
}

PROMPT = STANDARD_TEMPLATE.format_map(VARS)
