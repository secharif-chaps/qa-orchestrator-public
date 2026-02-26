# Products Task - Dify Workflow Prompt

## Expected JSON Output Format

```typescript
interface ProductAnalysis {
  insights: string  // AI-generated analysis (source: "Chaps-e")
  customer_type: SourcedValue<string>  // AI-analyzed (source: "Chaps-e")
  marketing_positioning: SourcedValue<string>  // AI-analyzed (source: "Chaps-e")
  range: ProductItem[]
  partner_brands: BrandItem[]
  private_labels: BrandItem[]
  categories: {
    [key: string]: string[]  // Simple strings, NOT SourcedValue
  }
}

interface SourcedValue<T> {
  value: T
  source: string  // Single source URL or "Chaps-e" for AI-generated
}

interface ProductItem {
  name: SourcedValue<string>
  description?: SourcedValue<string>
}

interface BrandItem {
  name: SourcedValue<string>
  description?: SourcedValue<string>
}
```

---

## Full Prompt

You are tasked with analyzing input text to extract comprehensive product and service information about a company. Your goal is to identify and structure the output to match this exact JSON format:

```json
{
  "insights": "A 2-3 sentence analysis of the company's product portfolio and market positioning.",
  "customer_type": {
    "value": "B2B enterprise customers in financial services and healthcare",
    "source": "Chaps-e"
  },
  "marketing_positioning": {
    "value": "Premium enterprise solution emphasizing security and compliance",
    "source": "Chaps-e"
  },
  "range": [
    {
      "name": {
        "value": "Enterprise Platform",
        "source": "https://company.com/products/enterprise/"
      },
      "description": {
        "value": "Full-featured CRM with advanced analytics and automation",
        "source": "https://company.com/products/enterprise/"
      }
    }
  ],
  "partner_brands": [
    {
      "name": {
        "value": "Microsoft",
        "source": "https://company.com/partners/microsoft/"
      },
      "description": {
        "value": "Gold partner reselling Microsoft 365 and Azure",
        "source": "https://company.com/partners/microsoft/"
      }
    }
  ],
  "private_labels": [
    {
      "name": {
        "value": "CompanyBrand Analytics",
        "source": "https://company.com/products/analytics/"
      },
      "description": {
        "value": "Proprietary AI-powered analytics tool",
        "source": "https://company.com/products/analytics/"
      }
    }
  ],
  "categories": {
    "CRM Solutions": ["Sales Cloud", "Service Cloud", "Marketing Cloud"],
    "Analytics": ["Business Intelligence", "Custom Reporting"],
    "Services": ["Implementation", "Training"]
  }
}
```

---

## CRITICAL: SOURCE PRIORITIZATION HIERARCHY

You MUST follow this strict 3-tier priority system when extracting data. **ALWAYS use the highest-tier source available for each field.**

### Tier System Overview

```
┌─────────────────────────────────────────────────────────┐
│ TIER 1: HIGHEST TRUST (Official & Verified Sources)    │
│                                                         │
│  Tier 1A: Company Website Scraping                     │
│           - Official pages (/products, /services)      │
│           - Most up-to-date, direct from source        │
│                                                         │
│  Tier 1B: Wikipedia (IF RELEVANT)                      │
│           - Pre-filter: MUST be about this company     │
│           - If relevant: HIGH TRUST (same as 1A)       │
│           - If NOT relevant: IGNORE completely         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ TIER 2: MEDIUM TRUST (Google Search Results)           │
│         - Third-party sources (reviews, reports)       │
│         - May be outdated, verify when possible        │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ TIER 3: LOW TRUST (Knowledge Bases)                    │
│         - Mistral KB, Claude KB                         │
│         - Potentially outdated, use as last resort     │
└─────────────────────────────────────────────────────────┘
```

---

## EXTRACTION REQUIREMENTS

### 1. insights (REQUIRED - AI Generated)
- **Source:** Always `"Chaps-e"` (this is AI-generated analysis)
- A 2-3 sentence summary of company's product portfolio
- Include: product focus, market positioning, target segments
- Example: "Company offers comprehensive enterprise CRM platform with three main tiers targeting SMB to large enterprise customers. Product portfolio includes core CRM functionality, advanced analytics, and extensive integration capabilities. Strong focus on B2B market with vertical-specific solutions."

### 2. customer_type (REQUIRED - AI Analyzed)
- **Source:** Always `"Chaps-e"` (this is AI-generated analysis)
- Target customer segments based on YOUR analysis
- Analyze: product features, pricing, case studies, industries mentioned
- **Examples**:
  - "B2B enterprise customers in financial services and healthcare"
  - "B2C consumers, primarily millennials and Gen Z"
  - "SMB to mid-market companies in technology sector"

### 3. marketing_positioning (REQUIRED - AI Analyzed)
- **Source:** Always `"Chaps-e"` (this is AI-generated analysis)
- How company positions itself based on YOUR analysis
- Analyze: taglines, value propositions, competitive claims, pricing
- **Examples**:
  - "Premium enterprise solution emphasizing security and compliance"
  - "Affordable, user-friendly platform for small businesses"
  - "Innovation leader in AI-powered analytics"

### 4. range (Array of ProductItem)
Extract product/service offerings with SourcedValue pattern:

| Field | Description | Source |
|-------|-------------|--------|
| `name` | Product/service name | URL where found |
| `description` | Brief description of the product | URL where found |

**Rules for range:**
- Extract all major products/services
- Each field has its own source URL
- Include pricing tiers if they're distinct products

### 5. partner_brands (Array of BrandItem)
Third-party brands the company distributes or partners with:

| Field | Description | Source |
|-------|-------------|--------|
| `name` | Partner brand name | URL where found |
| `description` | Partnership description (reseller level, etc.) | URL where found |

**Rules for partner_brands:**
- Only include brands company actively sells/resells
- Don't include technology/integration partners (unless they also resell)
- Include partnership level if mentioned

### 6. private_labels (Array of BrandItem)
Company's own branded or white-label products:

| Field | Description | Source |
|-------|-------------|--------|
| `name` | Private label brand name | URL where found |
| `description` | Description of proprietary offering | URL where found |

**Rules for private_labels:**
- Include own-brand products
- Include white-label offerings
- Don't include standard products

### 7. categories (Object - NOT SourcedValue)
**CRITICAL**: This is a simple object with string arrays, NOT SourcedValue objects.

Structure: `{ "Category Name": ["Product 1", "Product 2"], ... }`

**Rules for categories:**
- Create 3-8 logical categories
- Product names are simple strings (not SourcedValue)
- Group products by type, segment, or use case

---

## SOURCE ATTRIBUTION RULES

### For AI-Generated Content
- `insights`: Always implicit source `"Chaps-e"`
- `customer_type`: Always source `"Chaps-e"`
- `marketing_positioning`: Always source `"Chaps-e"`

### For Extracted Product Data
- Use the **exact URL** where each product was found
- Each field has its OWN source

### For Knowledge Bases (fallback)
- Mistral KB: `"Mistral Knowledge Base"`
- Claude KB: `"Claude Knowledge Base"`

---

## DATA INPUT STRUCTURE

### Tier 1A: Company Website Scraping (HIGHEST TRUST)
```
{{#1754928772178.company#}} complete website scraping results:
{{#1754928772178.scraped#}}
```

### Tier 1B: Wikipedia (HIGH TRUST IF RELEVANT)
```
Wikipedia search (HIGH TRUST if about company, IGNORE if not):
{{#1754928772178.wikipedia#}}
```

### Tier 2: Google Search Results (MEDIUM TRUST)
```
Specific Scraping results (medium trust):
{{#1754999165157.items#}}
```

### Tier 3: Knowledge Bases (LOW TRUST)
```
Mistral knowledge base:
{{#1754928772178.mistral#}}

Claude knowledge base:
{{#1754928772178.claude#}}
```

---

## OUTPUT FORMAT

Return ONLY valid JSON matching the structure below. No markdown, no code blocks, no explanations - just the JSON object.

**Complete example output:**
```json
{
  "insights": "Company offers comprehensive enterprise CRM platform with three main tiers targeting SMB to large enterprise customers. Product portfolio includes core CRM functionality, advanced analytics, and extensive integration capabilities. Strong focus on B2B market with vertical-specific solutions for financial services and healthcare.",
  "customer_type": {
    "value": "B2B companies from SMB to enterprise, with primary focus on financial services, healthcare, and technology sectors",
    "source": "Chaps-e"
  },
  "marketing_positioning": {
    "value": "Premium enterprise CRM solution emphasizing security, compliance, and scalability for regulated industries. Positioned as trusted alternative to Salesforce with better customization and pricing.",
    "source": "Chaps-e"
  },
  "range": [
    {
      "name": {
        "value": "Enterprise Platform",
        "source": "https://www.company.com/products/enterprise/"
      },
      "description": {
        "value": "Full-featured CRM with advanced analytics, workflow automation, custom development tools, and enterprise-grade security. Includes dedicated account management and SLA guarantees.",
        "source": "https://www.company.com/products/enterprise/"
      }
    },
    {
      "name": {
        "value": "Professional Edition",
        "source": "https://www.company.com/products/professional/"
      },
      "description": {
        "value": "Mid-market CRM with core sales, marketing, and service features. Includes standard integrations and email support.",
        "source": "https://www.company.com/products/professional/"
      }
    },
    {
      "name": {
        "value": "Starter Plan",
        "source": "https://www.company.com/products/starter/"
      },
      "description": {
        "value": "Essential CRM for small businesses with contact management, pipeline tracking, and basic reporting.",
        "source": "https://www.company.com/products/starter/"
      }
    },
    {
      "name": {
        "value": "Implementation Services",
        "source": "https://www.company.com/services/implementation/"
      },
      "description": {
        "value": "Professional services including data migration, custom configuration, integration development, and team training.",
        "source": "https://www.company.com/services/implementation/"
      }
    }
  ],
  "partner_brands": [
    {
      "name": {
        "value": "Microsoft",
        "source": "https://www.company.com/partners/microsoft/"
      },
      "description": {
        "value": "Gold partner reselling Microsoft 365, Azure, and Dynamics 365 with integrated CRM solutions",
        "source": "https://www.company.com/partners/microsoft/"
      }
    },
    {
      "name": {
        "value": "DocuSign",
        "source": "https://www.company.com/integrations/docusign/"
      },
      "description": {
        "value": "Authorized reseller offering e-signature integration with CRM platform",
        "source": "https://www.company.com/integrations/docusign/"
      }
    }
  ],
  "private_labels": [
    {
      "name": {
        "value": "CompanyBrand Analytics Engine",
        "source": "https://www.company.com/products/analytics/"
      },
      "description": {
        "value": "Proprietary AI-powered analytics and forecasting tool exclusive to the platform",
        "source": "https://www.company.com/products/analytics/"
      }
    },
    {
      "name": {
        "value": "White-label CRM Platform",
        "source": "https://www.company.com/partners/white-label/"
      },
      "description": {
        "value": "Available for system integrators and resellers to rebrand and sell to their customers",
        "source": "https://www.company.com/partners/white-label/"
      }
    }
  ],
  "categories": {
    "CRM Core Products": [
      "Enterprise Platform",
      "Professional Edition",
      "Starter Plan"
    ],
    "Analytics & Intelligence": [
      "CompanyBrand Analytics Engine",
      "Sales Forecasting Module",
      "Custom Reporting Builder"
    ],
    "Integration & API": [
      "REST API Platform",
      "Webhook Framework",
      "Pre-built Connectors Pack"
    ],
    "Professional Services": [
      "Implementation Services",
      "Custom Development",
      "Training & Certification",
      "Managed Services"
    ],
    "Partner Solutions": [
      "Microsoft 365 Integration",
      "DocuSign E-signature",
      "Slack Collaboration Tools"
    ]
  }
}
```

---

## FINAL CHECKLIST BEFORE OUTPUT

- [ ] Did I include the `insights` field (AI-generated analysis)?
- [ ] Does `customer_type` have `value` and `source: "Chaps-e"`?
- [ ] Does `marketing_positioning` have `value` and `source: "Chaps-e"`?
- [ ] Does each item in `range` have `name` and `description` with SourcedValue pattern?
- [ ] Does each item in `partner_brands` have SourcedValue fields?
- [ ] Does each item in `private_labels` have SourcedValue fields?
- [ ] Is `categories` a simple object with string arrays (NOT SourcedValue)?
- [ ] Is `source` a STRING (not an array)?
- [ ] Did I create 3-8 logical categories?
- [ ] Is the output valid JSON only (no markdown code blocks)?

---

## Common Mistakes to Avoid

❌ **WRONG:** Using `sources` array instead of singular `source`
```json
{
  "name": {
    "value": "Enterprise Platform",
    "sources": ["url1", "url2"]
  }
}
```
✅ **CORRECT:** Using singular `source` string
```json
{
  "name": {
    "value": "Enterprise Platform",
    "source": "https://company.com/products/"
  }
}
```

❌ **WRONG:** Categories using SourcedValue pattern
```json
{
  "categories": {
    "Software": [
      { "value": "Product A", "source": "url" }
    ]
  }
}
```
✅ **CORRECT:** Categories with simple string arrays
```json
{
  "categories": {
    "Software": ["Product A", "Product B"]
  }
}
```

❌ **WRONG:** customer_type without SourcedValue wrapper
```json
{
  "customer_type": "B2B enterprise"
}
```
✅ **CORRECT:** customer_type with SourcedValue and "Chaps-e" source
```json
{
  "customer_type": {
    "value": "B2B enterprise customers in financial services",
    "source": "Chaps-e"
  }
}
```

❌ **WRONG:** Missing top-level `insights` field
✅ **CORRECT:** Always include `insights` (AI-generated analysis)

❌ **WRONG:** Generic customer_type or marketing_positioning
```json
{
  "customer_type": { "value": "businesses", "source": "Chaps-e" }
}
```
✅ **CORRECT:** Specific, analyzed content
```json
{
  "customer_type": {
    "value": "B2B mid-market companies (100-1000 employees) in financial services and healthcare",
    "source": "Chaps-e"
  }
}
```

❌ **WRONG:** Product items without SourcedValue structure
```json
{
  "range": [
    { "name": "Product A", "description": "Description" }
  ]
}
```
✅ **CORRECT:** Each field uses SourcedValue pattern
```json
{
  "range": [
    {
      "name": { "value": "Product A", "source": "https://..." },
      "description": { "value": "Description", "source": "https://..." }
    }
  ]
}
```

❌ **WRONG:** Wrapping JSON in markdown code blocks
✅ **CORRECT:** Return only raw JSON object
