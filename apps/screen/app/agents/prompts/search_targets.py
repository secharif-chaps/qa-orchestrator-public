"""Per-agent search target descriptions."""

AGENT_SEARCH_TARGETS: dict[str, str] = {
    "profile": """Search the company's official website (About page, Corporate page), LinkedIn company page, Crunchbase, Wikipedia, and business registries (Pappers for French companies, Companies House for UK, SEC for US).""",
    "digital": """Search the company's website source code and headers, BuiltWith or Wappalyzer profiles, social media accounts (LinkedIn, Twitter/X, Facebook, Instagram, YouTube, TikTok), app stores (iOS/Android), and any SaaS/platform products.""",
    "press": """Search Google News, major business publications (Reuters, Bloomberg, TechCrunch, Les Echos), the company's press/newsroom page, and industry-specific publications.""",
    "jobs": """Search the company's careers page, LinkedIn jobs, Glassdoor, Indeed, Welcome to the Jungle, and other job boards. Look for hiring trends and employer reviews.""",
    "products": """Search the company's products/services pages, product review sites, comparison platforms, app stores, and industry analyst reports.""",
    "timeline": """Search Wikipedia, Crunchbase, the company's history/about page, press archives, and business databases for chronological milestones.""",
    "csr": """Search the company's CSR/sustainability page, ESG reports, sustainability databases (CDP, B Corp), press releases about environmental/social initiatives.""",
    "team": """Search the company's team/leadership page, LinkedIn profiles of executives, Crunchbase people profiles, press mentions of key personnel, and board of directors pages.""",
    "corporate_structure": """Search the company's corporate website (About, Legal, Group pages), Wikipedia, business registries (Pappers for French companies, Companies House for UK, SEC EDGAR for US, OpenCorporates), Crunchbase, Bloomberg, and annual reports. Look for corporate group structure, subsidiary lists, parent company, and organizational charts.""",
    "sanctions": """Search WorldCheck screening results for sanctions list entries (ONU, EU, OFAC, SDN), regulatory enforcement actions (competition authorities, data protection agencies), adverse media related to compliance issues, and PEP connections. Cross-reference with official sanctions databases and regulatory body publications.""",
}
