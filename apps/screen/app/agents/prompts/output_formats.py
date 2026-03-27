"""Per-agent output format specifications — auto-generated from Pydantic schemas.

This module generates AGENT_OUTPUT_FORMATS from the Pydantic schemas defined
in app.agents.schemas. This ensures prompts always match the schemas enforced
by OpenAI structured outputs, preventing format drift.
"""

import json
from typing import Any

from app.agents.schemas import AGENT_OUTPUT_SCHEMAS


def _clean_schema_for_prompt(schema: dict[str, Any]) -> dict[str, Any]:
    """Strip title and description keys recursively to save prompt tokens.

    Keeps $defs (needed for $ref pointers) but cleans metadata inside them.
    """
    cleaned: dict[str, Any] = {}
    for key, value in schema.items():
        if key in ("title", "description"):
            continue
        if isinstance(value, dict):
            cleaned[key] = _clean_schema_for_prompt(value)
        elif isinstance(value, list):
            cleaned[key] = [_clean_schema_for_prompt(item) if isinstance(item, dict) else item for item in value]
        else:
            cleaned[key] = value
    return cleaned


def _generate_output_format(agent_name: str) -> str:
    """Generate a human-readable JSON schema description for an agent's prompt."""
    schema_class = AGENT_OUTPUT_SCHEMAS.get(agent_name)
    if schema_class is None:
        return "{}"

    json_schema = schema_class.model_json_schema()
    cleaned = _clean_schema_for_prompt(json_schema)
    return json.dumps(cleaned, indent=2)


# Auto-generated at import time from Pydantic schemas
AGENT_OUTPUT_FORMATS: dict[str, str] = {
    agent_name: _generate_output_format(agent_name) for agent_name in AGENT_OUTPUT_SCHEMAS
}
