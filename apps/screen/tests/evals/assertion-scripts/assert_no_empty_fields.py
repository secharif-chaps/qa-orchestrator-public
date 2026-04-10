import json

REQUIRED_VALUE_FIELDS = [
    "businessLine",
    "catchphrase",
    "establishmentYear",
    "employeeCount",
    "revenue",
    "ceo",
    "hq",
]


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
    """Assert no required fields are empty when data was available in some tier."""
    try:
        text = _strip_markdown_fences(output.strip())
        data = json.loads(text)
    except (json.JSONDecodeError, ValueError) as e:
        return {"pass": False, "score": 0.0, "reason": f"Cannot parse JSON: {e}"}

    empty_fields = []
    for field in REQUIRED_VALUE_FIELDS:
        if field not in data:
            empty_fields.append(f"'{field}' is missing entirely")
        elif isinstance(data[field], dict):
            val = data[field].get("value", "").strip()
            if not val:
                empty_fields.append(f"'{field}' has empty value")
        elif isinstance(data[field], str) and not data[field].strip():
            empty_fields.append(f"'{field}' is empty string")

    # Also check insights
    if "insights" not in data or not data.get("insights", "").strip():
        empty_fields.append("'insights' is missing or empty")

    if empty_fields:
        score = max(0, 1.0 - len(empty_fields) * 0.15)
        return {
            "pass": False,
            "score": score,
            "reason": "Empty fields found: " + "; ".join(empty_fields),
        }

    return {
        "pass": True,
        "score": 1.0,
        "reason": "All required fields have non-empty values.",
    }
