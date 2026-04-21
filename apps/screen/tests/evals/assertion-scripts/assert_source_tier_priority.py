import json
import re
from urllib.parse import urlparse

# Fields expected to have {"value": ..., "source": ...} structure
OBJECT_FIELDS = [
    "businessLine",
    "catchphrase",
    "establishmentYear",
    "employeeCount",
    "revenue",
    "ceo",
    "hq",
    "groupName",
]

# Known Tier 3 source labels (lowercased) — what the prompt instructs the LLM to write
TIER3_LABELS = [
    "mistral knowledge base",
    "gpt knowledge base",
    "gptknowledge base",
    "mistral kb",
    "gptkb",
]

# Wikipedia domain patterns
WIKIPEDIA_PATTERNS = ["wikipedia.org", "en.m.wikipedia.org"]


def _classify_source(source, company_domain):
    """Classify a source string into its tier.

    Returns: "1a", "1b", "2", "3", or "skip" (for AI-generated like Chaps-e).
    """
    s = source.strip().lower()

    # Skip AI-generated insights source
    if s in ("chaps-e", "chapsmind", "ai-generated"):
        return "skip"

    # Tier 3: known KB labels (non-URL sources)
    if any(label in s for label in TIER3_LABELS):
        return "3"

    # If it looks like a URL, classify by domain
    if s.startswith("http://") or s.startswith("https://"):
        try:
            domain = urlparse(s).netloc.lower().replace("www.", "")
        except Exception:
            return "3"

        # Tier 1A: company domain
        if company_domain and company_domain in domain:
            return "1a"

        # Tier 1B: Wikipedia
        if any(wp in domain for wp in WIKIPEDIA_PATTERNS):
            return "1b"

        # Tier 2: any other real URL
        return "2"

    # Non-URL, non-KB label — treat as Tier 3 (unrecognized source)
    return "3"


def _extract_company_domain(vars_):
    """Extract company domain from the scraped data or website var."""
    # Try explicit website var first
    website = vars_.get("website", "").strip()
    if website:
        try:
            return urlparse(website).netloc.lower().replace("www.", "")
        except Exception:
            pass

    # Try to extract domain from scraped data (URLs in the text)
    scraped = vars_.get("scraped", "")
    if scraped:
        urls = re.findall(r"https?://(?:www\.)?([a-zA-Z0-9.-]+)", scraped)
        if urls:
            # Most frequent domain in scraped data is likely the company domain
            from collections import Counter

            domain_counts = Counter(urls)
            return domain_counts.most_common(1)[0][0].lower()

    return None


def _tier_rank(tier):
    """Numeric rank for tier comparison. Lower = more trusted."""
    return {"1a": 1, "1b": 1, "2": 2, "3": 3, "skip": 0}.get(tier, 99)


def _tier_label(tier):
    """Human-readable tier label."""
    return {
        "1a": "Tier 1A (company website)",
        "1b": "Tier 1B (Wikipedia)",
        "2": "Tier 2 (search results)",
        "3": "Tier 3 (knowledge base)",
    }.get(tier, f"Unknown ({tier})")


def get_assert(output, context):
    """Assert source tier priority: higher-tier sources preferred over lower ones."""
    try:
        text = output.strip()
        if text.startswith("```"):
            lines = text.split("\n")
            if lines[0].startswith("```"):
                lines = lines[1:]
            if lines and lines[-1].strip() == "```":
                lines = lines[:-1]
            text = "\n".join(lines).strip()
        data = json.loads(text)
    except (json.JSONDecodeError, ValueError) as e:
        return {"pass": False, "score": 0.0, "reason": f"Cannot parse JSON: {e}"}

    vars_ = context.get("vars", {})
    company_domain = _extract_company_domain(vars_)

    # Determine which tiers have input data available
    has_tier1a = bool(vars_.get("scraped", "").strip())
    has_tier1b = bool(vars_.get("wikipedia", "").strip())
    has_tier2 = bool(vars_.get("items", "").strip())
    has_tier3 = bool(vars_.get("mistral", "").strip()) or bool(vars_.get("gpt", "").strip())

    # Build set of available tiers
    available_tiers = set()
    if has_tier1a:
        available_tiers.add("1a")
    if has_tier1b:
        available_tiers.add("1b")
    if has_tier2:
        available_tiers.add("2")
    if has_tier3:
        available_tiers.add("3")

    if not available_tiers:
        return {
            "pass": True,
            "score": 1.0,
            "reason": "No input data available — nothing to check.",
        }

    # Best available tier rank (lowest number = most trusted)
    best_available_rank = min(_tier_rank(t) for t in available_tiers)

    violations = []
    fields_checked = 0

    for field in OBJECT_FIELDS:
        if field not in data or not isinstance(data[field], dict):
            continue

        source = data[field].get("source", "")
        if not source:
            continue

        fields_checked += 1
        tier = _classify_source(source, company_domain)

        if tier == "skip":
            continue

        field_rank = _tier_rank(tier)

        # Flag if this field uses a tier lower than the best available
        if field_rank > best_available_rank:
            violations.append(
                f"'{field}' uses {_tier_label(tier)} (source: '{source}') "
                f"but {_tier_label(min(available_tiers, key=_tier_rank))} data was available"
            )

    if not fields_checked:
        return {
            "pass": True,
            "score": 1.0,
            "reason": "No sourced fields to check.",
        }

    if violations:
        score = max(0.0, 1.0 - len(violations) * 0.2)
        return {
            "pass": len(violations) == 0,
            "score": score,
            "reason": f"{len(violations)} tier priority violation(s): " + "; ".join(violations),
        }

    return {
        "pass": True,
        "score": 1.0,
        "reason": f"Source tier priority respected across {fields_checked} fields.",
    }
