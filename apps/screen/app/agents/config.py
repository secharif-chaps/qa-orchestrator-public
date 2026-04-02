"""Agent system configuration and constants."""

from app.agents.prompts.domains import AGENT_ALLOWED_DOMAINS  # noqa: F401
from app.agents.prompts.methodology import RESEARCH_METHODOLOGY
from app.agents.prompts.output_formats import AGENT_OUTPUT_FORMATS
from app.agents.prompts.roles import AGENT_ROLES
from app.agents.prompts.search_targets import AGENT_SEARCH_TARGETS
from app.core.config import settings

# LLM model name (used by agent nodes)
LLM_MODEL = settings.LLM_MODEL

# Retry and timeout constants
MAX_AGENT_RETRIES = 1
AGENT_TIMEOUT_SECONDS = 120
GLOBAL_ANALYSIS_TIMEOUT_SECONDS = 900
MAX_CONCURRENT_API_CALLS = 5

# Pricing (gpt-5.1 per 1M tokens)
INPUT_PRICE_PER_MILLION = 1.25
OUTPUT_PRICE_PER_MILLION = 10.00

# All agent types (order determines execution)
ALL_AGENT_TYPES = [
    "profile",
    "digital",
    "press",
    "jobs",
    "products",
    "timeline",
    "csr",
    "team",
    "corporate_structure",
    "sanctions",
]


def _build_agent_prompt(agent_name: str) -> str:
    """Assemble a complete agent prompt from prompt components.

    Combines methodology, role, search targets, and output format
    into a single system prompt for the agent.
    """
    role = AGENT_ROLES.get(agent_name, "")
    targets = AGENT_SEARCH_TARGETS.get(agent_name, "")
    output_format = AGENT_OUTPUT_FORMATS.get(agent_name, "")

    return f"""{RESEARCH_METHODOLOGY}

## Your Role
{role}

## Where to Search
{targets}

## Output Format
{output_format}

IMPORTANT: Return ONLY valid JSON. No markdown, no explanations, just the JSON object."""


# Pre-compiled prompts dict (built at import time)
AGENT_PROMPTS: dict[str, str] = {agent_name: _build_agent_prompt(agent_name) for agent_name in ALL_AGENT_TYPES}
