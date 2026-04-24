"""Agent system configuration and constants."""

from app.agents.prompts import (
    corporate_structure,
    csr,
    digital,
    financial,
    jobs,
    planner,
    press,
    products,
    profile,
    sanctions,
    team,
    timeline,
)
from app.agents.prompts.domains import AGENT_ALLOWED_DOMAINS  # noqa: F401
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

PROMPTS_REGISTRY: dict[str, str] = {
    # ── Standard agents (single-step, via run_agent() in base.py) ─────────
    "profile":              profile.PROMPT,
    "digital":              digital.PROMPT,
    "press":                press.PROMPT,
    "jobs":                 jobs.PROMPT,
    "products":             products.PROMPT,
    "timeline":             timeline.PROMPT,
    "csr":                  csr.PROMPT,
    "team":                 team.PROMPT,
    "corporate_structure":  corporate_structure.PROMPT,
    "sanctions":            sanctions.PROMPT,
    # ── Financial agent (multi-step: classify → gather → synthesize) ──────
    "financial_classify":   financial.CLASSIFY_PROMPT,
    "financial_synthesize": financial.SYNTHESIZE_PROMPT,
    # ── Pipeline nodes (not agents, run before the agent graph) ───────────
    "planner":              planner.PLANNER_SYSTEM_PROMPT,
}

ALL_AGENT_TYPES: list[str] = [
    "profile", "digital", "press", "jobs", "products",
    "timeline", "csr", "team", "corporate_structure", "sanctions",
    "financial",
]
