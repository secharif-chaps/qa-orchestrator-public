# Timeline Task - Dify Workflow Prompt

## Expected JSON Output Format

```typescript
interface TimelineAnalysis {
  insights: string  // AI-generated analysis (source: "Chaps-e")
  events?: TimelineEvent[]
}

interface TimelineEvent {
  date: SourcedValue<string>  // Format: YYYY-MM-DD
  title: SourcedValue<string>
  description: SourcedValue<string>
  category: SourcedValue<string>
  location: SourcedValue<string>
  impact: SourcedValue<string>  // AI analysis (source: "Chaps-e")
}

interface SourcedValue<T> {
  value: T
  source: string  // Single source URL or "Chaps-e" for AI-generated
}
```

---

## Full Prompt

You are tasked with analyzing input text to extract comprehensive timeline and historical event information about a company. Your goal is to identify and structure the output to match this exact JSON format:

```json
{
  "insights": "A 2-3 sentence analysis of the company's timeline and key milestones.",
  "events": [
    {
      "date": {
        "value": "2012-05-15",
        "source": "https://company.com/about/history/"
      },
      "title": {
        "value": "Company Founded",
        "source": "https://company.com/about/history/"
      },
      "description": {
        "value": "Company founded by Jane Smith and John Doe in San Francisco with initial seed funding of $2M.",
        "source": "https://company.com/about/history/"
      },
      "category": {
        "value": "founding",
        "source": "https://company.com/about/history/"
      },
      "location": {
        "value": "San Francisco, USA",
        "source": "https://company.com/about/history/"
      },
      "impact": {
        "value": "Established the foundation for a new approach to enterprise SaaS solutions.",
        "source": "Chaps-e"
      }
    }
  ]
}
```

---

## CRITICAL: SOURCE PRIORITIZATION HIERARCHY

You MUST follow this strict 3-tier priority system when extracting data. **ALWAYS use the highest-tier source available for each event.**

### Tier System Overview

```
┌─────────────────────────────────────────────────────────┐
│ TIER 1: HIGHEST TRUST (Official & Verified Sources)    │
│                                                         │
│  Tier 1A: Company Website Scraping                     │
│           - Official pages (/about, /history, /timeline)│
│           - Most up-to-date, direct from source        │
│                                                         │
│  Tier 1B: Wikipedia (IF RELEVANT)                      │
│           - Pre-filter: MUST be about this company     │
│           - If relevant: HIGH TRUST (same as 1A)       │
│           - ESPECIALLY VALUABLE for historical events  │
│           - If NOT relevant: IGNORE completely         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ TIER 2: MEDIUM TRUST (Google Search Results)           │
│         - Third-party sources (news archives, etc.)    │
│         - May be outdated, verify dates carefully      │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ TIER 3: LOW TRUST (Knowledge Bases)                    │
│         - Mistral KB, Claude KB                         │
│         - Potentially outdated, use as last resort     │
└─────────────────────────────────────────────────────────┘
```

**IMPORTANT FOR TIMELINE:** Wikipedia (Tier 1B) is especially valuable for historical events. If Wikipedia is relevant, it's often the BEST source for accurate dates.

---

## EXTRACTION REQUIREMENTS

### 1. insights (REQUIRED - AI Generated)
- **Source:** Always `"Chaps-e"` (this is AI-generated analysis)
- A 2-3 sentence summary of company timeline
- Include: founding, major milestones, growth trajectory
- Example: "Company founded in 2012, achieving rapid growth through strategic acquisitions and multiple funding rounds. Major milestones include Series B funding in 2023 ($50M) and European expansion in 2024."

### 2. events (Array of TimelineEvent)
Each event has SourcedValue fields:

| Field | Description | Source |
|-------|-------------|--------|
| `date` | Event date (YYYY-MM-DD format) | URL where found |
| `title` | Brief event title (3-10 words) | URL where found |
| `description` | Detailed description (1-3 sentences) | URL where found |
| `category` | Event category (see list below) | URL where found |
| `location` | Geographic location | URL where found |
| `impact` | AI analysis of event significance | Always `"Chaps-e"` |

### Event Categories (Allowed Values)
- **founding** - Company inception, incorporation
- **funding** - Investment rounds, financing
- **acquisition** - Company M&A activity
- **product_launch** - New product/service releases
- **milestone** - Significant achievements
- **award** - Recognition, certifications
- **expansion** - Geographic expansion, new offices
- **partnership** - Strategic partnerships
- **leadership** - Executive changes
- **controversy** - Legal issues, crises
- **restructuring** - Layoffs, pivots
- **exit** - IPO, acquisition of the company

### Date Format Rules
- **Full date known**: "2023-05-15"
- **Only month/year**: "2023-05-01" (use first day)
- **Only year**: "2023-01-01" (use January 1st)
- **NEVER** use formats like "May 2023" or "Q1 2023"

---

## SOURCE ATTRIBUTION RULES

### For AI-Generated Content
- `insights`: Always implicit source `"Chaps-e"`
- `impact` field: Always source `"Chaps-e"`

### For Extracted Event Data
- Use the **exact URL** where each piece of information was found
- Each field has its OWN source
- All fields for same event typically share same source (except impact)

### For Wikipedia (when relevant)
- Set source to `"https://en.wikipedia.org"`

### For Knowledge Bases (fallback)
- Mistral KB: `"Mistral Knowledge Base"`
- Claude KB: `"Claude Knowledge Base"`

---

## CHRONOLOGICAL ORDERING

**CRITICAL**: Events MUST be ordered chronologically from oldest to newest.

```json
// ✅ CORRECT: Oldest to newest
[
  { "date": { "value": "2012-01-01", "source": "..." } },
  { "date": { "value": "2015-03-15", "source": "..." } },
  { "date": { "value": "2023-05-20", "source": "..." } }
]
```

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
  "insights": "Company founded in 2012, achieving rapid growth through strategic acquisitions and multiple funding rounds. Major milestones include Series B funding in 2023 ($50M), European expansion, and recognition as Forbes Cloud 100 company.",
  "events": [
    {
      "date": {
        "value": "2012-05-15",
        "source": "https://www.company.com/about/history/"
      },
      "title": {
        "value": "Company Founded",
        "source": "https://www.company.com/about/history/"
      },
      "description": {
        "value": "Company founded by Jane Smith and John Doe in San Francisco with initial seed funding of $2M from Y Combinator.",
        "source": "https://www.company.com/about/history/"
      },
      "category": {
        "value": "founding",
        "source": "https://www.company.com/about/history/"
      },
      "location": {
        "value": "San Francisco, USA",
        "source": "https://www.company.com/about/history/"
      },
      "impact": {
        "value": "Established the foundation for a new approach to enterprise SaaS solutions, attracting early adopter customers in the first year.",
        "source": "Chaps-e"
      }
    },
    {
      "date": {
        "value": "2015-03-10",
        "source": "https://techcrunch.com/2015/03/10/company-series-a/"
      },
      "title": {
        "value": "Series A Funding Round",
        "source": "https://techcrunch.com/2015/03/10/company-series-a/"
      },
      "description": {
        "value": "Completed Series A funding of $15M led by Accel Partners to expand engineering team and accelerate product development.",
        "source": "https://techcrunch.com/2015/03/10/company-series-a/"
      },
      "category": {
        "value": "funding",
        "source": "https://techcrunch.com/2015/03/10/company-series-a/"
      },
      "location": {
        "value": "San Francisco, USA",
        "source": "https://techcrunch.com/2015/03/10/company-series-a/"
      },
      "impact": {
        "value": "This funding enabled the company to grow the team from 10 to 50 employees and launch the enterprise version of the product.",
        "source": "Chaps-e"
      }
    },
    {
      "date": {
        "value": "2017-09-01",
        "source": "https://www.company.com/press/european-expansion/"
      },
      "title": {
        "value": "Expansion to European Markets",
        "source": "https://www.company.com/press/european-expansion/"
      },
      "description": {
        "value": "Opened first European office in London with team of 15 to serve UK and European customers.",
        "source": "https://www.company.com/press/european-expansion/"
      },
      "category": {
        "value": "expansion",
        "source": "https://www.company.com/press/european-expansion/"
      },
      "location": {
        "value": "London, UK",
        "source": "https://www.company.com/press/european-expansion/"
      },
      "impact": {
        "value": "The European expansion opened access to a $2B market and established the company as a global player.",
        "source": "Chaps-e"
      }
    },
    {
      "date": {
        "value": "2019-06-20",
        "source": "https://en.wikipedia.org"
      },
      "title": {
        "value": "Acquisition of DataTech Analytics",
        "source": "https://en.wikipedia.org"
      },
      "description": {
        "value": "Acquired DataTech Analytics for $45M to add advanced analytics capabilities and expand product portfolio.",
        "source": "https://en.wikipedia.org"
      },
      "category": {
        "value": "acquisition",
        "source": "https://en.wikipedia.org"
      },
      "location": {
        "value": "San Francisco, USA",
        "source": "https://en.wikipedia.org"
      },
      "impact": {
        "value": "This acquisition eliminated a competitor and provided critical AI/ML capabilities that became core product differentiators.",
        "source": "Chaps-e"
      }
    },
    {
      "date": {
        "value": "2020-11-05",
        "source": "https://www.company.com/blog/1-million-users/"
      },
      "title": {
        "value": "Reached 1 Million Users",
        "source": "https://www.company.com/blog/1-million-users/"
      },
      "description": {
        "value": "Platform reached 1 million active users milestone, demonstrating strong product-market fit.",
        "source": "https://www.company.com/blog/1-million-users/"
      },
      "category": {
        "value": "milestone",
        "source": "https://www.company.com/blog/1-million-users/"
      },
      "location": {
        "value": "Global",
        "source": "https://www.company.com/blog/1-million-users/"
      },
      "impact": {
        "value": "This milestone validated the business model and attracted attention from larger investors, leading to Series B discussions.",
        "source": "Chaps-e"
      }
    },
    {
      "date": {
        "value": "2023-02-15",
        "source": "https://www.company.com/press/series-b-funding/"
      },
      "title": {
        "value": "Series B Funding $50M",
        "source": "https://www.company.com/press/series-b-funding/"
      },
      "description": {
        "value": "Completed Series B funding round of $50M led by Sequoia Capital with participation from existing investors.",
        "source": "https://www.company.com/press/series-b-funding/"
      },
      "category": {
        "value": "funding",
        "source": "https://www.company.com/press/series-b-funding/"
      },
      "location": {
        "value": "San Francisco, USA",
        "source": "https://www.company.com/press/series-b-funding/"
      },
      "impact": {
        "value": "This funding positioned the company for aggressive expansion and potential IPO within 2-3 years.",
        "source": "Chaps-e"
      }
    },
    {
      "date": {
        "value": "2023-08-10",
        "source": "https://www.forbes.com/cloud100/2023/"
      },
      "title": {
        "value": "Forbes Cloud 100 Recognition",
        "source": "https://www.forbes.com/cloud100/2023/"
      },
      "description": {
        "value": "Named to Forbes Cloud 100 list as one of the top private cloud companies worldwide.",
        "source": "https://www.forbes.com/cloud100/2023/"
      },
      "category": {
        "value": "award",
        "source": "https://www.forbes.com/cloud100/2023/"
      },
      "location": {
        "value": "Global",
        "source": "https://www.forbes.com/cloud100/2023/"
      },
      "impact": {
        "value": "This recognition enhanced brand credibility and helped attract enterprise customers and top talent.",
        "source": "Chaps-e"
      }
    },
    {
      "date": {
        "value": "2024-01-05",
        "source": "https://www.company.com/press/leadership-transition/"
      },
      "title": {
        "value": "CEO Transition",
        "source": "https://www.company.com/press/leadership-transition/"
      },
      "description": {
        "value": "Co-founder Jane Doe transitioned from CEO to Executive Chairman. Former VP of Sales John Smith appointed as new CEO.",
        "source": "https://www.company.com/press/leadership-transition/"
      },
      "category": {
        "value": "leadership",
        "source": "https://www.company.com/press/leadership-transition/"
      },
      "location": {
        "value": "San Francisco, USA",
        "source": "https://www.company.com/press/leadership-transition/"
      },
      "impact": {
        "value": "This leadership transition brought experienced SaaS go-to-market expertise to accelerate revenue growth.",
        "source": "Chaps-e"
      }
    }
  ]
}
```

---

## FINAL CHECKLIST BEFORE OUTPUT

- [ ] Did I include the `insights` field (AI-generated analysis)?
- [ ] Does each event field have `value` and `source`?
- [ ] Is `impact.source` always `"Chaps-e"`?
- [ ] Is `source` a STRING (not an array)?
- [ ] Are all dates in YYYY-MM-DD format?
- [ ] Are events ordered chronologically (oldest to newest)?
- [ ] Did I assign correct category to each event?
- [ ] Is the output valid JSON only (no markdown code blocks)?

---

## Common Mistakes to Avoid

❌ **WRONG:** Using `sources` array instead of singular `source`
```json
{
  "date": {
    "value": "2023-05-15",
    "sources": ["url1", "url2"]
  }
}
```
✅ **CORRECT:** Using singular `source` string
```json
{
  "date": {
    "value": "2023-05-15",
    "source": "https://company.com/history/"
  }
}
```

❌ **WRONG:** Plain string fields without SourcedValue wrapper
```json
{
  "date": "2023-05-15",
  "title": "Company Founded",
  "source": "https://..."
}
```
✅ **CORRECT:** All fields use SourcedValue pattern
```json
{
  "date": { "value": "2023-05-15", "source": "https://..." },
  "title": { "value": "Company Founded", "source": "https://..." }
}
```

❌ **WRONG:** Using incorrect date format
```json
{
  "date": { "value": "May 2023", "source": "..." }
}
```
✅ **CORRECT:** Always use YYYY-MM-DD
```json
{
  "date": { "value": "2023-05-01", "source": "..." }
}
```

❌ **WRONG:** Events in random order
✅ **CORRECT:** Always chronological order (oldest to newest)

❌ **WRONG:** Impact field with extracted source
```json
{
  "impact": {
    "value": "Great achievement",
    "source": "https://company.com/"
  }
}
```
✅ **CORRECT:** Impact always sourced as "Chaps-e"
```json
{
  "impact": {
    "value": "This milestone validated the business model and attracted investor interest.",
    "source": "Chaps-e"
  }
}
```

❌ **WRONG:** Missing top-level `insights` field
✅ **CORRECT:** Always include `insights` (AI-generated analysis)

❌ **WRONG:** Vague category not in allowed list
```json
{
  "category": { "value": "news", "source": "..." }
}
```
✅ **CORRECT:** Use standardized category
```json
{
  "category": { "value": "funding", "source": "..." }
}
```

❌ **WRONG:** Wrapping JSON in markdown code blocks
✅ **CORRECT:** Return only raw JSON object
