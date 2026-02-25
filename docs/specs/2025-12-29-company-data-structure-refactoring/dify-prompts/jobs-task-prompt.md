# Jobs Task - Dify Workflow Prompt

## Expected JSON Output Format

```typescript
interface JobsAnalysis {
  insights: string  // AI-generated analysis (source: "Chaps-e")
  offers?: {
    title: {
      value: string
      source: string
    }
    location: {
      value: string
      source: string
    }
    department: {
      value: string
      source: string
    }
    description: {
      value: string
      source: string
    }
    requirements: {
      value: string
      source: string
    }
    posted_date: {
      value: string
      source: string
    }
  }[]
  insights_data?: {
    total_openings: {
      value: number
      source: string
    }
    top_departments: {
      value: string[]
      source: string
    }
    hiring_focus: {
      value: string
      source: string
    }
    growth_indicators: {
      value: string
      source: string
    }
  }
}
```

---

## Full Prompt

You are tasked with analyzing input text to extract comprehensive job market and hiring information about a company. Your goal is to identify and structure the output to match this exact JSON format:

```json
{
  "insights": "A 2-3 sentence analysis of the company's hiring activity and what it indicates about their growth trajectory.",
  "offers": [
    {
      "title": {
        "value": "Senior Software Engineer",
        "source": "https://jobs.example.com/posting/123"
      },
      "location": {
        "value": "Paris, France",
        "source": "https://jobs.example.com/posting/123"
      },
      "department": {
        "value": "Engineering",
        "source": "https://jobs.example.com/posting/123"
      },
      "description": {
        "value": "Build and maintain backend systems...",
        "source": "https://jobs.example.com/posting/123"
      },
      "requirements": {
        "value": "5+ years experience, Python, AWS",
        "source": "https://jobs.example.com/posting/123"
      },
      "posted_date": {
        "value": "2024-12-15",
        "source": "https://jobs.example.com/posting/123"
      }
    }
  ],
  "insights_data": {
    "total_openings": {
      "value": 45,
      "source": "https://careers.example.com"
    },
    "top_departments": {
      "value": ["Engineering", "Sales", "Product"],
      "source": "https://careers.example.com"
    },
    "hiring_focus": {
      "value": "Engineering expansion and international sales growth",
      "source": "Chaps-e"
    },
    "growth_indicators": {
      "value": "Strong hiring in technical roles suggests product development acceleration",
      "source": "Chaps-e"
    }
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
│  Tier 1A: Company Careers Page                         │
│           - Official job postings (/careers, /jobs)    │
│           - Most up-to-date, direct from source        │
│                                                         │
│  Tier 1B: Official Job Boards                          │
│           - LinkedIn Jobs, Welcome to the Jungle       │
│           - Indeed (company verified postings)         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ TIER 2: MEDIUM TRUST (Third-Party Job Aggregators)     │
│         - Glassdoor, Indeed (unverified), ZipRecruiter │
│         - May have duplicates or outdated listings     │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ TIER 3: LOW TRUST (Knowledge Bases)                    │
│         - Mistral KB, Claude KB                         │
│         - Potentially outdated hiring information      │
└─────────────────────────────────────────────────────────┘
```

---

## EXTRACTION REQUIREMENTS

### 1. insights (REQUIRED - AI Generated)
- **Source:** Always set to `"Chaps-e"` (this is AI-generated analysis)
- A 2-3 sentence summary analyzing the company's hiring patterns
- Include: hiring volume, focus areas, growth implications
- Example: "The company is actively expanding its engineering team with 45 open positions, primarily focused on backend development and data engineering. This hiring surge, combined with new sales roles in EMEA, suggests preparation for significant product expansion and international market growth."

### 2. offers (Array of Job Postings)
Extract specific job openings with SourcedValue pattern for each field:

| Field | Description | Source |
|-------|-------------|--------|
| `title` | Job title/position name | URL of the job posting |
| `location` | Work location (city, country, or "Remote") | URL of the job posting |
| `department` | Department or team name | URL of the job posting |
| `description` | Brief job description (1-2 sentences) | URL of the job posting |
| `requirements` | Key qualifications (summarized) | URL of the job posting |
| `posted_date` | Posting date (YYYY-MM-DD format) | URL of the job posting |

**Rules for offers:**
- Extract up to 10 most relevant/recent job postings
- Prioritize diverse departments (don't just list 10 engineering jobs)
- Use the exact URL where the job was found as the source
- If date is not available, use empty string ""

### 3. insights_data (Structured Hiring Insights)

| Field | Type | Source | Description |
|-------|------|--------|-------------|
| `total_openings` | number | URL where count was found | Total number of open positions |
| `top_departments` | string[] | URL or "Chaps-e" | Top 3-5 departments hiring |
| `hiring_focus` | string | "Chaps-e" | AI analysis of hiring focus areas |
| `growth_indicators` | string | "Chaps-e" | AI analysis of what hiring indicates |

**Rules for insights_data:**
- `total_openings`: Use actual count from careers page (numeric value, not string)
- `top_departments`: List departments with most openings
- `hiring_focus`: AI-generated analysis (source: "Chaps-e")
- `growth_indicators`: AI-generated analysis (source: "Chaps-e")

---

## SOURCE ATTRIBUTION RULES

### For Job Postings (offers array)
- Use the **exact URL** of each job posting
- All fields in a single offer should share the same source URL
- Example: `"source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/senior-backend-engineer"`

### For Insights Data
- `total_openings`: URL of careers page or job board with count
- `top_departments`: URL if extracted, or "Chaps-e" if analyzed
- `hiring_focus`: Always "Chaps-e" (AI analysis)
- `growth_indicators`: Always "Chaps-e" (AI analysis)

### For Top-Level Insights
- Always set source to `"Chaps-e"` (this is your AI analysis)

---

## DATA INPUT STRUCTURE

### Tier 1A: Company Careers Page (HIGHEST TRUST)
```
{{#1754928772178.company#}} career page scraping results:
{{#1754928772178.scraped#}}
```

### Tier 1B: Job Board Results (HIGH TRUST)
```
Job board scraping results (Welcome to the Jungle, LinkedIn, etc.):
{{#17568191110340.items#}}
```

### Tier 2: Third-Party Aggregators (MEDIUM TRUST)
```
Additional job aggregator results:
{{#additional_sources#}}
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
  "insights": "ChapsVision is aggressively hiring across technical and commercial roles with 45 open positions. The heavy focus on backend engineering and data science roles suggests significant product development investment, while new sales positions in Germany and UK indicate European expansion plans.",
  "offers": [
    {
      "title": {
        "value": "Senior Backend Engineer",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/senior-backend-engineer"
      },
      "location": {
        "value": "Paris, France",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/senior-backend-engineer"
      },
      "department": {
        "value": "Engineering",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/senior-backend-engineer"
      },
      "description": {
        "value": "Design and implement scalable backend services using Python and FastAPI",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/senior-backend-engineer"
      },
      "requirements": {
        "value": "5+ years Python, experience with microservices, PostgreSQL, Docker",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/senior-backend-engineer"
      },
      "posted_date": {
        "value": "2024-12-10",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/senior-backend-engineer"
      }
    },
    {
      "title": {
        "value": "Data Scientist",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/data-scientist"
      },
      "location": {
        "value": "Remote, France",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/data-scientist"
      },
      "department": {
        "value": "Data & AI",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/data-scientist"
      },
      "description": {
        "value": "Build ML models to power our AI analytics platform",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/data-scientist"
      },
      "requirements": {
        "value": "3+ years ML experience, Python, TensorFlow or PyTorch, NLP experience preferred",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/data-scientist"
      },
      "posted_date": {
        "value": "2024-12-05",
        "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs/data-scientist"
      }
    },
    {
      "title": {
        "value": "Sales Manager - DACH Region",
        "source": "https://careers.chapsvision.com/jobs/sales-manager-dach"
      },
      "location": {
        "value": "Munich, Germany",
        "source": "https://careers.chapsvision.com/jobs/sales-manager-dach"
      },
      "department": {
        "value": "Sales",
        "source": "https://careers.chapsvision.com/jobs/sales-manager-dach"
      },
      "description": {
        "value": "Lead sales efforts in the DACH region, building enterprise client relationships",
        "source": "https://careers.chapsvision.com/jobs/sales-manager-dach"
      },
      "requirements": {
        "value": "7+ years B2B SaaS sales, German fluency, enterprise sales experience",
        "source": "https://careers.chapsvision.com/jobs/sales-manager-dach"
      },
      "posted_date": {
        "value": "2024-12-01",
        "source": "https://careers.chapsvision.com/jobs/sales-manager-dach"
      }
    }
  ],
  "insights_data": {
    "total_openings": {
      "value": 45,
      "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs"
    },
    "top_departments": {
      "value": ["Engineering", "Sales", "Data & AI", "Product", "Customer Success"],
      "source": "https://www.welcometothejungle.com/fr/companies/chapsvision/jobs"
    },
    "hiring_focus": {
      "value": "Technical talent acquisition for product development, with emphasis on backend engineering and AI/ML capabilities. Secondary focus on international sales expansion.",
      "source": "Chaps-e"
    },
    "growth_indicators": {
      "value": "The hiring pattern suggests Series B/C growth stage with strong product-market fit. Heavy engineering investment indicates platform scaling, while international sales roles suggest successful domestic market and readiness for expansion.",
      "source": "Chaps-e"
    }
  }
}
```

---

## FINAL CHECKLIST BEFORE OUTPUT

- [ ] Did I include the `insights` field with source "Chaps-e"?
- [ ] Did I extract job offers with SourcedValue pattern for ALL fields?
- [ ] Does each offer field have `value` and `source`?
- [ ] Is `total_openings.value` a NUMBER (not a string)?
- [ ] Is `top_departments.value` an ARRAY of strings?
- [ ] Are `hiring_focus` and `growth_indicators` sourced as "Chaps-e"?
- [ ] Are all job posting sources exact URLs?
- [ ] Is the output valid JSON only (no markdown code blocks)?

---

## Common Mistakes to Avoid

❌ **WRONG:** Forgetting the top-level `insights` field
✅ **CORRECT:** Always include `insights` with source `"Chaps-e"`

❌ **WRONG:** Using `sources: []` (array) instead of `source: ""` (string)
✅ **CORRECT:** Use singular `source` field with string value

❌ **WRONG:** `"total_openings": { "value": "45", "source": "..." }` (string value)
✅ **CORRECT:** `"total_openings": { "value": 45, "source": "..." }` (number value)

❌ **WRONG:** Returning job offers without SourcedValue pattern
```json
{ "title": "Engineer", "location": "Paris" }
```
✅ **CORRECT:** Each field wrapped in SourcedValue
```json
{
  "title": { "value": "Engineer", "source": "https://..." },
  "location": { "value": "Paris", "source": "https://..." }
}
```

❌ **WRONG:** Wrapping JSON in markdown code blocks
✅ **CORRECT:** Return only raw JSON object

❌ **WRONG:** Missing source for job posting fields
✅ **CORRECT:** Every field in offers array has a source URL
