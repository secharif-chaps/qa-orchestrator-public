from app.agents.prompts.shared.methodology import RESEARCH_METHODOLOGY
from app.agents.prompts.shared.output_formats import AGENT_OUTPUT_FORMATS
from app.agents.prompts.shared.template import STANDARD_TEMPLATE

ROLE = "You research the company's digital presence: website technologies, social media accounts, online services, and digital strategy."
SEARCH_TARGETS = """Search the company's website source code and headers, BuiltWith or Wappalyzer profiles, social media accounts (LinkedIn, Twitter/X, Facebook, Instagram, YouTube, TikTok), app stores (iOS/Android), and any SaaS/platform products."""

VARS = {
    "role": ROLE,
    "targets": SEARCH_TARGETS,
    "methodology": RESEARCH_METHODOLOGY,
    "output_format": AGENT_OUTPUT_FORMATS["digital"],
}

PROMPT = STANDARD_TEMPLATE.format_map(VARS)
