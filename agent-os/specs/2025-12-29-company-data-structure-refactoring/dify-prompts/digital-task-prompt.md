# Digital Task - Dify Workflow Prompt

## Expected JSON Output Format

```typescript
interface DigitalAnalysis {
  insights: string  // AI-generated analysis (source: "Chaps-e")
  digital_strategy?: {
    overall_strategy?: SourcedValue<string>
    digital_transformation?: SourcedValue<string>
    e_commerce_capabilities?: SourcedValue<string>
    mobile_strategy?: SourcedValue<string>
    digital_marketing_approach?: SourcedValue<string>
  }
  loyalty_programs?: LoyaltyProgram[]
  online_services?: OnlineService[]
  social_media_accounts?: SocialMediaAccount[]
}

interface SourcedValue<T> {
  value: T
  source: string  // Single source URL or "Chaps-e" for AI-generated
}

interface LoyaltyProgram {
  name: SourcedValue<string>
  description?: SourcedValue<string>
  benefits?: SourcedValue<string[]>
}

interface OnlineService {
  name: SourcedValue<string>
  description?: SourcedValue<string>
  url?: SourcedValue<string>
}

interface SocialMediaAccount {
  platform: SourcedValue<string>
  url: SourcedValue<string>
}
```

---

## Full Prompt

You are tasked with analyzing input text to extract comprehensive digital strategy information about a company. Your goal is to identify and structure the output to match this exact JSON format:

```json
{
  "insights": "A 2-3 sentence analysis of the company's digital strategy and online presence.",
  "digital_strategy": {
    "overall_strategy": {
      "value": "Mobile-first, omnichannel strategy",
      "source": "https://company.com/digital-strategy/"
    },
    "digital_transformation": {
      "value": "Cloud migration initiative 2023-2025",
      "source": "https://company.com/transformation/"
    },
    "e_commerce_capabilities": {
      "value": "Full e-commerce platform with 50k SKUs",
      "source": "https://company.com/shop/"
    },
    "mobile_strategy": {
      "value": "Native iOS and Android apps with 2M downloads",
      "source": "https://company.com/mobile/"
    },
    "digital_marketing_approach": {
      "value": "Performance marketing via Google/Facebook Ads, SEO, email automation",
      "source": "https://company.com/marketing/"
    }
  },
  "loyalty_programs": [
    {
      "name": {
        "value": "Gold Elite Rewards",
        "source": "https://company.com/loyalty/"
      },
      "description": {
        "value": "Premium tier loyalty program for top customers",
        "source": "https://company.com/loyalty/"
      },
      "benefits": {
        "value": ["15% discount", "Free shipping", "Early access"],
        "source": "https://company.com/loyalty/"
      }
    }
  ],
  "online_services": [
    {
      "name": {
        "value": "Customer Portal",
        "source": "https://company.com/services/"
      },
      "description": {
        "value": "Self-service account management portal",
        "source": "https://company.com/services/"
      },
      "url": {
        "value": "https://portal.company.com",
        "source": "https://company.com/services/"
      }
    }
  ],
  "social_media_accounts": [
    {
      "platform": {
        "value": "Facebook",
        "source": "https://company.com/"
      },
      "url": {
        "value": "https://facebook.com/companyname",
        "source": "https://company.com/"
      }
    }
  ]
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
│           - Official pages (/digital, /services)       │
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

**Key Principle:** Tier 1A and 1B are EQUAL priority when both available. Tier 1 beats Tier 2. Tier 2 beats Tier 3.

---

## EXTRACTION REQUIREMENTS

### 1. insights (REQUIRED - AI Generated)
- **Source:** Always set to `"Chaps-e"` (this is AI-generated analysis)
- A 2-3 sentence summary of the company's digital strategy and presence
- Include: digital maturity, key initiatives, online presence strength
- Example: "Company has a comprehensive digital transformation strategy focused on mobile-first customer experience and AI-powered automation. Operates a premium loyalty program with over 2M members. Strong social media presence across 6 platforms."

### 2. digital_strategy (Object with SourcedValue fields)
Each field within digital_strategy uses the SourcedValue pattern independently:

| Field | Description | Source |
|-------|-------------|--------|
| `overall_strategy` | High-level digital strategy and vision | URL where found |
| `digital_transformation` | Ongoing/completed transformation initiatives | URL where found |
| `e_commerce_capabilities` | E-commerce platforms and online sales | URL where found |
| `mobile_strategy` | Mobile apps and mobile-first approach | URL where found |
| `digital_marketing_approach` | Digital marketing strategy and channels | URL where found |

**Rules for digital_strategy:**
- Each field has its OWN source (not shared)
- Use the exact URL where each piece of information was found
- Fields can come from different pages

### 3. loyalty_programs (Array of LoyaltyProgram)
Each loyalty program has SourcedValue fields:

| Field | Type | Description |
|-------|------|-------------|
| `name` | SourcedValue<string> | Name of the loyalty program |
| `description` | SourcedValue<string> | Brief description of the program |
| `benefits` | SourcedValue<string[]> | Array of benefits/perks |

**Rules for loyalty_programs:**
- Extract all loyalty programs, rewards programs, membership programs
- Each field has its own source URL
- Benefits is an array of strings within a SourcedValue

### 4. online_services (Array of OnlineService)
Each online service has SourcedValue fields:

| Field | Type | Description |
|-------|------|-------------|
| `name` | SourcedValue<string> | Name of the online service |
| `description` | SourcedValue<string> | Brief description |
| `url` | SourcedValue<string> | Direct URL to access the service |

**What counts as an online service:**
- Customer portals, self-service platforms
- Mobile apps (iOS, Android, web apps)
- Online tools, calculators, configurators
- Digital platforms, SaaS offerings
- Chatbots, virtual assistants
- Online booking, scheduling systems

### 5. social_media_accounts (Array of SocialMediaAccount)
Each social media account has SourcedValue fields:

| Field | Type | Description |
|-------|------|-------------|
| `platform` | SourcedValue<string> | Standardized platform name |
| `url` | SourcedValue<string> | Direct URL to company's profile |

**Standardized platform names:**
- "Facebook" (not "fb" or "facebook.com")
- "Twitter" (not "X" or "twitter.com")
- "LinkedIn" (not "linkedIn" or "LI")
- "Instagram" (not "IG" or "insta")
- "YouTube" (not "YT")
- "TikTok" (not "tiktok")

**How to extract:**
- Look for social media icons/links in website footer
- Check contact page, about page
- Look for "Follow us", "Connect with us" sections
- Source is the page where the link was found (not the social media URL itself)

---

## SOURCE ATTRIBUTION RULES

### For Each Field
- Use the **exact URL** where the information was found
- Each field has its OWN source, not shared

### For AI-Generated Content
- `insights`: Always source as `"Chaps-e"`

### For Knowledge Bases (when used as fallback)
- Mistral KB: Set source to `"Mistral Knowledge Base"`
- Claude KB: Set source to `"Claude Knowledge Base"`

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
  "insights": "Company has a comprehensive digital transformation strategy focused on mobile-first customer experience and AI-powered automation. Operates premium loyalty program 'Gold Elite' with over 2M members. Strong social media presence across 6 platforms with combined 500k followers.",
  "digital_strategy": {
    "overall_strategy": {
      "value": "Mobile-first, omnichannel strategy connecting digital and physical experiences with AI-powered personalization",
      "source": "https://www.company.com/digital-strategy/"
    },
    "digital_transformation": {
      "value": "Multi-year transformation initiative (2022-2025) migrating legacy systems to cloud-native microservices architecture",
      "source": "https://www.company.com/transformation/"
    },
    "e_commerce_capabilities": {
      "value": "Full-stack e-commerce platform processing $500M annually with 50k SKUs, personalized recommendations, and same-day delivery",
      "source": "https://www.company.com/shop/"
    },
    "mobile_strategy": {
      "value": "Native iOS and Android apps with 2M+ downloads, mobile wallet integration, and augmented reality features",
      "source": "https://www.company.com/mobile/"
    },
    "digital_marketing_approach": {
      "value": "Data-driven performance marketing via programmatic advertising, SEO, email automation with 30% open rates",
      "source": "https://www.company.com/marketing/"
    }
  },
  "loyalty_programs": [
    {
      "name": {
        "value": "Gold Elite Rewards",
        "source": "https://www.company.com/loyalty/"
      },
      "description": {
        "value": "Premium tier loyalty program for top customers with exclusive benefits and personalized service",
        "source": "https://www.company.com/loyalty/"
      },
      "benefits": {
        "value": [
          "15% discount on all purchases",
          "Free premium shipping",
          "Early access to new products",
          "Dedicated concierge service"
        ],
        "source": "https://www.company.com/loyalty/"
      }
    },
    {
      "name": {
        "value": "Silver Members Club",
        "source": "https://www.company.com/rewards/"
      },
      "description": {
        "value": "Standard loyalty tier for regular customers",
        "source": "https://www.company.com/rewards/"
      },
      "benefits": {
        "value": [
          "5% discount on purchases",
          "Free standard shipping over $50",
          "Birthday reward"
        ],
        "source": "https://www.company.com/rewards/"
      }
    }
  ],
  "online_services": [
    {
      "name": {
        "value": "Customer Portal",
        "source": "https://www.company.com/services/"
      },
      "description": {
        "value": "Self-service account management portal with order tracking, returns, and payment management",
        "source": "https://www.company.com/services/"
      },
      "url": {
        "value": "https://portal.company.com",
        "source": "https://www.company.com/services/"
      }
    },
    {
      "name": {
        "value": "Mobile Banking App",
        "source": "https://www.company.com/mobile-app/"
      },
      "description": {
        "value": "Full-featured mobile banking for iOS and Android with biometric authentication",
        "source": "https://www.company.com/mobile-app/"
      },
      "url": {
        "value": "https://www.company.com/mobile-app",
        "source": "https://www.company.com/mobile-app/"
      }
    },
    {
      "name": {
        "value": "Virtual Assistant Chatbot",
        "source": "https://www.company.com/support/"
      },
      "description": {
        "value": "AI-powered customer service chatbot available 24/7",
        "source": "https://www.company.com/support/"
      },
      "url": {
        "value": "https://www.company.com/support/chat",
        "source": "https://www.company.com/support/"
      }
    }
  ],
  "social_media_accounts": [
    {
      "platform": {
        "value": "Facebook",
        "source": "https://www.company.com/"
      },
      "url": {
        "value": "https://facebook.com/companyname",
        "source": "https://www.company.com/"
      }
    },
    {
      "platform": {
        "value": "Twitter",
        "source": "https://www.company.com/"
      },
      "url": {
        "value": "https://twitter.com/companyname",
        "source": "https://www.company.com/"
      }
    },
    {
      "platform": {
        "value": "LinkedIn",
        "source": "https://www.company.com/contact/"
      },
      "url": {
        "value": "https://linkedin.com/company/company-name",
        "source": "https://www.company.com/contact/"
      }
    },
    {
      "platform": {
        "value": "Instagram",
        "source": "https://www.company.com/"
      },
      "url": {
        "value": "https://instagram.com/companyname",
        "source": "https://www.company.com/"
      }
    },
    {
      "platform": {
        "value": "YouTube",
        "source": "https://www.company.com/"
      },
      "url": {
        "value": "https://youtube.com/@companyname",
        "source": "https://www.company.com/"
      }
    },
    {
      "platform": {
        "value": "TikTok",
        "source": "https://www.company.com/"
      },
      "url": {
        "value": "https://tiktok.com/@companyname",
        "source": "https://www.company.com/"
      }
    }
  ]
}
```

---

## FINAL CHECKLIST BEFORE OUTPUT

- [ ] Did I include the `insights` field with source "Chaps-e"?
- [ ] Does each field in digital_strategy have its own `value` and `source`?
- [ ] Does each loyalty program field have `value` and `source`?
- [ ] Does each online service field have `value` and `source`?
- [ ] Does each social media account field have `value` and `source`?
- [ ] Are platform names standardized (Facebook, Twitter, LinkedIn, etc.)?
- [ ] Is `source` a STRING (not an array)?
- [ ] Is the output valid JSON only (no markdown code blocks)?

---

## Common Mistakes to Avoid

❌ **WRONG:** Using `sources` array instead of singular `source`
```json
{
  "overall_strategy": {
    "value": "Mobile-first strategy",
    "sources": ["url1", "url2"]
  }
}
```
✅ **CORRECT:** Using singular `source` string
```json
{
  "overall_strategy": {
    "value": "Mobile-first strategy",
    "source": "https://company.com/digital/"
  }
}
```

❌ **WRONG:** Shared sources for entire section
```json
{
  "digital_strategy": {
    "value": {
      "overall_strategy": "...",
      "mobile_strategy": "..."
    },
    "sources": ["url1"]
  }
}
```
✅ **CORRECT:** Each field has its own source
```json
{
  "digital_strategy": {
    "overall_strategy": {
      "value": "...",
      "source": "url1"
    },
    "mobile_strategy": {
      "value": "...",
      "source": "url2"
    }
  }
}
```

❌ **WRONG:** Missing top-level `insights` field
✅ **CORRECT:** Always include `insights` with source `"Chaps-e"`

❌ **WRONG:** Non-standardized social media platform names
```json
{ "platform": { "value": "fb", "source": "..." } }
```
✅ **CORRECT:** Standardized platform names
```json
{ "platform": { "value": "Facebook", "source": "..." } }
```

❌ **WRONG:** Wrapping JSON in markdown code blocks
✅ **CORRECT:** Return only raw JSON object

❌ **WRONG:** Plain string fields without SourcedValue wrapper
```json
{
  "loyalty_programs": [
    { "name": "Gold Rewards", "description": "Premium program" }
  ]
}
```
✅ **CORRECT:** All fields use SourcedValue pattern
```json
{
  "loyalty_programs": [
    {
      "name": { "value": "Gold Rewards", "source": "https://..." },
      "description": { "value": "Premium program", "source": "https://..." }
    }
  ]
}
```
