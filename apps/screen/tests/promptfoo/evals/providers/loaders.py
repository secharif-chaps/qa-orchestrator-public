"""Per-agent promptfoo provider functions.

Each function is a named promptfoo file provider — the function name appears
as the prompt label in the results table, giving clear per-agent visibility.

Usage in a per-agent config:
    prompts:
      - file://providers/loaders.py:load_profile
"""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent.parent.parent.parent.parent))

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


def load_digital(context: dict) -> list[dict]:
    return [
        {"role": "system", "content": PROMPTS_REGISTRY["digital"]},
        {"role": "user", "content": _user(context)},
    ]


def load_press(context: dict) -> list[dict]:
    return [
        {"role": "system", "content": PROMPTS_REGISTRY["press"]},
        {"role": "user", "content": _user(context)},
    ]


def load_jobs(context: dict) -> list[dict]:
    return [
        {"role": "system", "content": PROMPTS_REGISTRY["jobs"]},
        {"role": "user", "content": _user(context)},
    ]


def load_products(context: dict) -> list[dict]:
    return [
        {"role": "system", "content": PROMPTS_REGISTRY["products"]},
        {"role": "user", "content": _user(context)},
    ]


def load_timeline(context: dict) -> list[dict]:
    return [
        {"role": "system", "content": PROMPTS_REGISTRY["timeline"]},
        {"role": "user", "content": _user(context)},
    ]


def load_csr(context: dict) -> list[dict]:
    return [
        {"role": "system", "content": PROMPTS_REGISTRY["csr"]},
        {"role": "user", "content": _user(context)},
    ]


def load_team(context: dict) -> list[dict]:
    return [
        {"role": "system", "content": PROMPTS_REGISTRY["team"]},
        {"role": "user", "content": _user(context)},
    ]


def load_corporate_structure(context: dict) -> list[dict]:
    return [
        {"role": "system", "content": PROMPTS_REGISTRY["corporate_structure"]},
        {"role": "user", "content": _user(context)},
    ]
