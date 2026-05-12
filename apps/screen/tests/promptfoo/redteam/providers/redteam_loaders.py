"""Full providers for promptfoo red team.

Unlike the eval loaders (prompt functions that return list[dict] and let
promptfoo make the API call), these are full providers that own the HTTP call
themselves — required by `promptfoo redteam` which expects a target that
takes a plain prompt string and returns {"output": "..."}.

Usage in a redteam config:
    targets:
      - id: file://../providers/redteam_loaders.py:planner
        label: planner-agent

Adding a new agent:
    1. Add a named function below following the same pattern.
    2. Create a matching config in configs/<agent>.yaml.
"""

import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent.parent.parent.parent.parent))

from app.agents.config import PROMPTS_REGISTRY


def _call_llm(messages: list[dict]) -> dict:
    """Call the LLM gateway and return a promptfoo-compatible response dict."""
    base_url = os.environ["PROMPTFOO_LLM_BASE_URL"].rstrip("/")
    api_key = os.environ["PROMPTFOO_LLM_API_KEY"]
    model = os.environ.get("LLM_MODEL", "gpt-5.1-sweden")

    # No response_format constraint: we want to capture what the model actually
    # outputs under adversarial conditions, including non-JSON responses.
    payload = json.dumps({
        "model": model,
        "messages": messages,
        "temperature": 0,
    }).encode()

    req = urllib.request.Request(
        f"{base_url}/chat/completions",
        data=payload,
        headers={
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            body = json.loads(resp.read())
        return {"output": body["choices"][0]["message"]["content"]}
    except urllib.error.HTTPError as e:
        if e.code == 400:
            # Azure content safety filter blocked the probe before it reached the
            # model. Return a neutral marker so the grader scores it as a pass
            # (the attack was stopped at the gateway level).
            return {"output": "[CONTENT_FILTERED_BY_GATEWAY]"}
        raise


def planner(prompt: str, options: dict, context: dict) -> dict:
    """Red team full provider for the Planner pipeline node.

    Wraps the adversarial probe with the Planner system prompt and calls the
    LLM gateway — keeping the system prompt in sync with PROMPTS_REGISTRY.
    """
    return _call_llm([
        {"role": "system", "content": PROMPTS_REGISTRY["planner"]},
        {"role": "user", "content": prompt},
    ])
