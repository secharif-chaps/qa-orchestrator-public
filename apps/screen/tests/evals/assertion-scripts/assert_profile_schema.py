import json

REQUIRED_FIELDS = [
    "insights",
    "businessLine",
    "catchphrase",
    "establishmentYear",
    "employeeCount",
    "revenue",
    "ceo",
    "hq",
]
OPTIONAL_FIELDS = ["groupName"]
OBJECT_FIELDS = [
    "groupName",
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
    """Assert output matches ProfileAnalysis JSON schema."""
    try:
        text = _strip_markdown_fences(output.strip())
        data = json.loads(text)
    except (json.JSONDecodeError, ValueError) as e:
        return {"pass": False, "score": 0.0, "reason": f"Cannot parse JSON: {e}"}

    errors = []

    # Check required fields exist
    for field in REQUIRED_FIELDS:
        if field not in data:
            errors.append(f"Missing required field: '{field}'")

    # Check insights is a string
    if "insights" in data and not isinstance(data["insights"], str):
        errors.append(f"'insights' should be a string, got {type(data['insights']).__name__}")

    # Check object fields have {value, source} structure
    for field in OBJECT_FIELDS:
        if field in data:
            obj = data[field]
            if not isinstance(obj, dict):
                errors.append(f"'{field}' should be an object, got {type(obj).__name__}")
            else:
                if "value" not in obj:
                    errors.append(f"'{field}' missing 'value' key")
                if "source" not in obj:
                    errors.append(f"'{field}' missing 'source' key")

    if errors:
        score = max(0, 1.0 - len(errors) * 0.15)
        return {
            "pass": False,
            "score": score,
            "reason": "Schema violations: " + "; ".join(errors),
        }

    return {
        "pass": True,
        "score": 1.0,
        "reason": "Output matches ProfileAnalysis schema.",
    }
