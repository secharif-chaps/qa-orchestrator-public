import re


def get_assert(output, context):
    """Assert output contains only queries with pipe separators, no extra text."""
    text = output.strip()

    # Check for numbering patterns like "1.", "1)", "Query 1:"
    if re.search(r"^\d+[\.\):]", text, re.MULTILINE):
        return {
            "pass": False,
            "score": 0.0,
            "reason": "Output contains numbering (e.g., '1.', '2)')",
        }

    # Should be single line
    lines = [line for line in text.split("\n") if line.strip()]
    if len(lines) > 1:
        return {
            "pass": False,
            "score": 0.0,
            "reason": (f"Output has {len(lines)} lines, expected single line with pipe separators"),
        }

    # Check for common preamble phrases
    preamble_patterns = [
        r"(?i)^(here are|the queries|i suggest|query \d|search query)",
        r"(?i)(note:|description:|explanation:)",
    ]
    for pattern in preamble_patterns:
        if re.search(pattern, text):
            return {
                "pass": False,
                "score": 0.0,
                "reason": f"Output contains extra text matching pattern: {pattern}",
            }

    return {
        "pass": True,
        "score": 1.0,
        "reason": "No extra text detected. Output is clean queries only.",
    }
