import json


def _strip_markdown_fences(text):
    """Strip markdown code fences if present."""
    if text.startswith("```"):
        lines = text.split("\n")
        if lines[0].startswith("```"):
            lines = lines[1:]
        if lines and lines[-1].strip() == "```":
            lines = lines[:-1]
        text = "\n".join(lines).strip()
    return text


def get_assert(output, context):
    """Assert output is valid JSON."""
    text = _strip_markdown_fences(output.strip())

    try:
        parsed = json.loads(text)
        if not isinstance(parsed, dict):
            return {
                "pass": False,
                "score": 0.2,
                "reason": (f"JSON parsed but is {type(parsed).__name__}, expected object/dict"),
            }
        return {
            "pass": True,
            "score": 1.0,
            "reason": "Valid JSON object parsed successfully.",
        }
    except json.JSONDecodeError as e:
        return {
            "pass": False,
            "score": 0.0,
            "reason": f"Invalid JSON: {e}. First 200 chars: '{text[:200]}'",
        }
