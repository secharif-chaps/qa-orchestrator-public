"""Per-agent domain whitelists for web search filtering.

The {company_domain} placeholder is replaced at runtime with the actual domain.
Agents with empty lists have no domain restrictions.
"""

AGENT_ALLOWED_DOMAINS: dict[str, list[str]] = {
    "profile": [
        "{company_domain}",
        "wikipedia.org",
        "linkedin.com",
        "crunchbase.com",
        "societe.com",
        "pappers.fr",
        "infogreffe.fr",
        "companieshouse.gov.uk",
        "sec.gov",
    ],
    "digital": [
        "{company_domain}",
        "linkedin.com",
        "twitter.com",
        "x.com",
        "facebook.com",
        "instagram.com",
        "youtube.com",
        "tiktok.com",
        "builtwith.com",
        "apps.apple.com",
        "play.google.com",
    ],
    "press": [],  # No domain restriction for press coverage
    "jobs": [
        "{company_domain}",
        "linkedin.com",
        "glassdoor.com",
        "glassdoor.fr",
        "indeed.com",
        "indeed.fr",
        "welcometothejungle.com",
    ],
    "products": [
        "{company_domain}",
        "g2.com",
        "capterra.com",
        "trustpilot.com",
        "producthunt.com",
        "apps.apple.com",
        "play.google.com",
    ],
    "timeline": [],  # No domain restriction for historical events
    "csr": [],  # No domain restriction for CSR/sustainability
    "team": [
        "{company_domain}",
        "linkedin.com",
        "crunchbase.com",
        "bloomberg.com",
    ],
}
