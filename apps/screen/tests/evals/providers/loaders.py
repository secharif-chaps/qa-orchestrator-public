"""Per-agent promptfoo provider functions.

Each function is a named promptfoo file provider — the function name appears
as the prompt label in the results table, giving clear per-agent visibility.

Usage in a per-agent config:
    prompts:
      - file://providers/loaders.py:load_profile
"""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent.parent.parent.parent))

from app.agents.config import PROMPTS_REGISTRY


def _user(context: dict) -> str:
    return context.get("vars", {}).get("user_message", "")


def load_profile(context: dict) -> list[dict]:
    return [
        {"role": "system", "content": PROMPTS_REGISTRY["profile"]},
        {"role": "user", "content": _user(context)},
    ]


def load_sanctions(context: dict) -> list[dict]:
    return [
        {"role": "system", "content": PROMPTS_REGISTRY["sanctions"]},
        {"role": "user", "content": _user(context)},
    ]


def load_financial_classify(context: dict) -> list[dict]:
    vars_ = context.get("vars", {})
    prompt = PROMPTS_REGISTRY["financial_classify"].format(company_name=vars_.get("company_name", ""))
    return [
        {"role": "system", "content": prompt},
        {"role": "user", "content": vars_.get("user_message", "")},
    ]


def load_planner(context: dict) -> list[dict]:
    return [
        {"role": "system", "content": PROMPTS_REGISTRY["planner"]},
        {"role": "user", "content": _user(context)},
    ]
