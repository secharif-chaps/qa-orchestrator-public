"""Per-agent output format specifications using SourcedValue pattern."""

# SourcedValue pattern: {"value": "string", "source": "URL"}
# Fields that are null should be omitted or set to null

AGENT_OUTPUT_FORMATS: dict[str, str] = {
    "profile": """{
  "insights": "2-3 paragraph analysis of the company profile",
  "groupName": {"value": "Parent group or company name", "source": "URL"},
  "businessLine": {"value": "Primary business activity", "source": "URL"},
  "catchphrase": {"value": "Company slogan or tagline", "source": "URL"},
  "establishmentYear": {"value": "YYYY", "source": "URL"},
  "employeeCount": {"value": "Number or range", "source": "URL"},
  "revenue": {"value": "Revenue figure with currency", "source": "URL"},
  "ceo": {"value": "CEO full name", "source": "URL"},
  "hq": {"value": "City, Country", "source": "URL"}
}""",
    "digital": """{
  "insights": "Analysis of digital presence and strategy",
  "socialMedia": [
    {"platform": "linkedin", "url": "URL", "followers": "count or null", "source": "URL"},
    {"platform": "twitter", "url": "URL", "followers": "count or null", "source": "URL"}
  ],
  "onlineServices": [
    {"name": "Service name", "url": "URL", "description": "Brief description", "source": "URL"}
  ],
  "technologies": ["Tech 1", "Tech 2"],
  "mobileApps": [
    {"name": "App name", "platform": "ios|android", "url": "URL"}
  ]
}""",
    "press": """{
  "insights": "Summary of recent media coverage and key themes",
  "articles": [
    {
      "title": {"value": "Article title", "source": "URL"},
      "publication": {"value": "Publication name", "source": "URL"},
      "date": {"value": "YYYY-MM-DD", "source": "URL"},
      "summary": {"value": "Brief summary", "source": "URL"},
      "sentiment": "positive|neutral|negative"
    }
  ]
}""",
    "jobs": """{
  "insights": "Analysis of hiring trends and employer reputation",
  "insights_data": {
    "total_openings": {"value": "number", "source": "URL"},
    "top_departments": ["department1", "department2"],
    "glassdoor_rating": {"value": "X.X/5", "source": "URL"},
    "growth_signal": "growing|stable|reducing"
  },
  "offers": [
    {
      "title": {"value": "Job title", "source": "URL"},
      "location": {"value": "City, Country", "source": "URL"},
      "department": {"value": "Department", "source": "URL"},
      "type": {"value": "full-time|part-time|contract|internship", "source": "URL"},
      "seniority": {"value": "junior|mid|senior|lead|executive", "source": "URL"},
      "salary": {"value": "Salary range", "source": "URL"},
      "posted_date": {"value": "YYYY-MM-DD", "source": "URL"}
    }
  ]
}""",
    "products": """{
  "insights": "Analysis of product portfolio and market positioning",
  "categories": [
    {
      "name": {"value": "Category name", "source": "URL"},
      "description": {"value": "Category description", "source": "URL"}
    }
  ],
  "items": [
    {
      "name": {"value": "Product name", "source": "URL"},
      "description": {"value": "Product description", "source": "URL"},
      "category": {"value": "Category name", "source": "URL"},
      "type": "product|service|platform|solution",
      "url": {"value": "Product URL", "source": "URL"},
      "pricing": {"value": "Pricing info", "source": "URL"}
    }
  ]
}""",
    "timeline": """{
  "insights": "Narrative summary of company history and key developments",
  "events": [
    {
      "year": {"value": "YYYY", "source": "URL"},
      "title": {"value": "Event title", "source": "URL"},
      "description": {"value": "Event description", "source": "URL"},
      "type": "founding|acquisition|partnership|product_launch|funding|expansion|restructuring|other"
    }
  ]
}""",
    "csr": """{
  "insights": "Analysis of CSR strategy and sustainability commitments",
  "initiatives": [
    {
      "name": {"value": "Initiative name", "source": "URL"},
      "description": {"value": "Initiative description", "source": "URL"},
      "type": "environmental|social|governance|community|diversity",
      "year": {"value": "YYYY", "source": "URL"}
    }
  ],
  "certifications": [
    {"name": "Certification name", "source": "URL"}
  ],
  "reports": [
    {"title": "Report title", "url": "URL", "year": "YYYY"}
  ]
}""",
    "team": """{
  "insights": "Analysis of leadership structure and key personnel",
  "members": [
    {
      "name": {"value": "Full name", "source": "URL"},
      "title": {"value": "Job title", "source": "URL"},
      "linkedin": {"value": "LinkedIn URL", "source": "URL"},
      "bio": {"value": "Brief bio", "source": "URL"},
      "image_url": {"value": "Photo URL", "source": "URL"},
      "reports_to": "Name of superior or null"
    }
  ]
}""",
}
