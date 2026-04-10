def get_assert(output, context):
    """Assert output contains exactly 3 queries separated by ' | '."""
    text = output.strip()
    parts = text.split(" | ")

    if len(parts) == 3:
        all_valid = all(len(p.strip()) > 3 for p in parts)
        if all_valid:
            return {
                "pass": True,
                "score": 1.0,
                "reason": "Exactly 3 non-empty queries found.",
            }
        return {
            "pass": False,
            "score": 0.3,
            "reason": f"3 parts found but some are too short or empty: {parts}",
        }

    return {
        "pass": False,
        "score": 0.0,
        "reason": (f"Expected 3 queries separated by ' | ', got {len(parts)} parts: '{text[:200]}'"),
    }
