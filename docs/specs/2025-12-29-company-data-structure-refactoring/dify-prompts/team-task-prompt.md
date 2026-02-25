# Team Task - Dify Workflow Prompt

## Expected JSON Output Format

```typescript
interface TeamAnalysis {
  insights: string  // AI-generated analysis (source: "Chaps-e")
  team?: TeamMember[]
}

interface TeamMember {
  position: SourcedValue<string>
  first_name: SourcedValue<string>
  last_name: SourcedValue<string>
  subordinates?: TeamMember[]
}

interface SourcedValue<T> {
  value: T
  source: string  // Single source URL or "Chaps-e" for AI-generated
}
```

---

## Full Prompt

You are tasked with analyzing input text to extract comprehensive team and management information about a company. Your goal is to identify and structure the output to match this exact JSON format:

```json
{
  "insights": "A 2-3 sentence analysis of the company's leadership team and organizational structure.",
  "team": [
    {
      "position": {
        "value": "CEO & Co-Founder",
        "source": "https://company.com/team/"
      },
      "first_name": {
        "value": "Jane",
        "source": "https://company.com/team/"
      },
      "last_name": {
        "value": "Smith",
        "source": "https://company.com/team/"
      },
      "subordinates": [
        {
          "position": {
            "value": "CTO",
            "source": "https://company.com/team/"
          },
          "first_name": {
            "value": "John",
            "source": "https://company.com/team/"
          },
          "last_name": {
            "value": "Doe",
            "source": "https://company.com/team/"
          },
          "subordinates": []
        }
      ]
    }
  ]
}
```

---

## CRITICAL: SOURCE PRIORITIZATION HIERARCHY

You MUST follow this strict 3-tier priority system when extracting data. **ALWAYS use the highest-tier source available for each team member.**

### Tier System Overview

```
┌─────────────────────────────────────────────────────────┐
│ TIER 1: HIGHEST TRUST (Official & Verified Sources)    │
│                                                         │
│  Tier 1A: Company Website Scraping                     │
│           - Official pages (/team, /leadership, /about)│
│           - Most up-to-date, direct from source        │
│                                                         │
│  Tier 1B: Wikipedia (IF RELEVANT)                      │
│           - Pre-filter: MUST be about this company     │
│           - If relevant: HIGH TRUST (same as 1A)       │
│           - Especially good for founders/CEO           │
│           - If NOT relevant: IGNORE completely         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ TIER 2: MEDIUM TRUST (Google Search Results)           │
│         - Third-party sources (LinkedIn, news, etc.)   │
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
- A 2-3 sentence summary of team structure and leadership
- Include: team size, key leaders, organizational structure
- Example: "Company founded by Jane Smith (CEO) and John Doe (CTO) in 2015. Leadership team includes 5 C-level executives and 12 VPs across functions. Engineering team led by CTO with 3 VP-level direct reports."

### 2. team (Array of TeamMember)
**CRITICAL**: This is an array representing the TOP LEVEL of the organizational hierarchy.

**Who goes in the top-level array:**
- CEO/Founder(s) at the top
- If no CEO identified, C-level executives
- If no C-level, whoever is at the top of hierarchy

### TeamMember Structure

Each TeamMember has SourcedValue fields:

| Field | Description | Source |
|-------|-------------|--------|
| `position` | Job title or role | URL where found |
| `first_name` | Person's first name | URL where found |
| `last_name` | Person's last name | URL where found |
| `subordinates` | Array of direct reports (recursive TeamMember[]) | - |

**Rules for team members:**
- Each field has its own source URL
- Use official title from source
- Split names correctly (first/last)
- subordinates is optional - only include when relationship is CLEAR

---

## SOURCE ATTRIBUTION RULES

### For AI-Generated Content
- `insights`: Always implicit source `"Chaps-e"`

### For Extracted Team Data
- Use the **exact URL** where each person was found
- Each field has its OWN source
- All fields for same person typically share same source

### For Wikipedia (when relevant)
- Set source to `"https://en.wikipedia.org"`

### For Knowledge Bases (fallback)
- Mistral KB: `"Mistral Knowledge Base"`
- Claude KB: `"Claude Knowledge Base"`

---

## ORGANIZATIONAL HIERARCHY RULES

### Rule 1: Standard Corporate Hierarchy

Typical reporting structure (use as guideline, not assumption):

```
CEO / Founder
├── COO (Chief Operating Officer)
├── CFO (Chief Financial Officer)
├── CTO (Chief Technology Officer)
├── CMO (Chief Marketing Officer)
└── CHRO (Chief Human Resources Officer)

CTO
├── VP Engineering
├── VP Product
└── VP Infrastructure
```

### Rule 2: Be Conservative with Subordinates

**DO include subordinates when:**
- ✅ Explicit org chart shows reporting
- ✅ Text says "reports to"
- ✅ Clear title hierarchy (VP reports to CTO)
- ✅ Team section shows "Team led by [name]:"

**DO NOT include subordinates when:**
- ❌ Uncertain about reporting relationship
- ❌ Just guessing based on departments
- ❌ No clear evidence of hierarchy

### Rule 3: Flat Structure Is Okay

If you cannot determine clear reporting relationships, it's better to have a flat structure (everyone in top-level array with no subordinates) than to invent relationships.

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
{{#17558778344686.items#}}
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

**Example 1: Clear Hierarchy (with subordinates)**
```json
{
  "insights": "Company founded by Jane Smith (CEO) and John Doe (CTO) in 2015. Executive team includes 4 C-level leaders. Engineering organization has 3 VP-level leaders reporting to CTO.",
  "team": [
    {
      "position": {
        "value": "CEO & Co-Founder",
        "source": "https://www.company.com/team/"
      },
      "first_name": {
        "value": "Jane",
        "source": "https://www.company.com/team/"
      },
      "last_name": {
        "value": "Smith",
        "source": "https://www.company.com/team/"
      },
      "subordinates": [
        {
          "position": {
            "value": "Chief Technology Officer & Co-Founder",
            "source": "https://www.company.com/team/"
          },
          "first_name": {
            "value": "John",
            "source": "https://www.company.com/team/"
          },
          "last_name": {
            "value": "Doe",
            "source": "https://www.company.com/team/"
          },
          "subordinates": [
            {
              "position": {
                "value": "VP of Engineering",
                "source": "https://www.company.com/team/"
              },
              "first_name": {
                "value": "Alice",
                "source": "https://www.company.com/team/"
              },
              "last_name": {
                "value": "Johnson",
                "source": "https://www.company.com/team/"
              },
              "subordinates": []
            },
            {
              "position": {
                "value": "VP of Product",
                "source": "https://www.company.com/team/"
              },
              "first_name": {
                "value": "Bob",
                "source": "https://www.company.com/team/"
              },
              "last_name": {
                "value": "Williams",
                "source": "https://www.company.com/team/"
              },
              "subordinates": []
            }
          ]
        },
        {
          "position": {
            "value": "Chief Financial Officer",
            "source": "https://www.company.com/team/"
          },
          "first_name": {
            "value": "David",
            "source": "https://www.company.com/team/"
          },
          "last_name": {
            "value": "Miller",
            "source": "https://www.company.com/team/"
          },
          "subordinates": []
        },
        {
          "position": {
            "value": "Chief Marketing Officer",
            "source": "https://www.company.com/team/"
          },
          "first_name": {
            "value": "Eve",
            "source": "https://www.company.com/team/"
          },
          "last_name": {
            "value": "Wilson",
            "source": "https://www.company.com/team/"
          },
          "subordinates": []
        }
      ]
    }
  ]
}
```

**Example 2: Flat Structure (relationships unclear)**
```json
{
  "insights": "Leadership team includes CEO Jane Smith and 4 C-level executives. No clear reporting structure documented on website.",
  "team": [
    {
      "position": {
        "value": "CEO",
        "source": "https://www.company.com/about/"
      },
      "first_name": {
        "value": "Jane",
        "source": "https://www.company.com/about/"
      },
      "last_name": {
        "value": "Smith",
        "source": "https://www.company.com/about/"
      },
      "subordinates": []
    },
    {
      "position": {
        "value": "CTO",
        "source": "https://www.company.com/about/"
      },
      "first_name": {
        "value": "Alice",
        "source": "https://www.company.com/about/"
      },
      "last_name": {
        "value": "Johnson",
        "source": "https://www.company.com/about/"
      },
      "subordinates": []
    },
    {
      "position": {
        "value": "CFO",
        "source": "https://www.company.com/about/"
      },
      "first_name": {
        "value": "Bob",
        "source": "https://www.company.com/about/"
      },
      "last_name": {
        "value": "Williams",
        "source": "https://www.company.com/about/"
      },
      "subordinates": []
    }
  ]
}
```

**Example 3: Founders from Wikipedia**
```json
{
  "insights": "Founded by three co-founders sharing leadership responsibilities. Founder information from Wikipedia.",
  "team": [
    {
      "position": {
        "value": "Co-Founder & CEO",
        "source": "https://en.wikipedia.org"
      },
      "first_name": {
        "value": "Jane",
        "source": "https://en.wikipedia.org"
      },
      "last_name": {
        "value": "Smith",
        "source": "https://en.wikipedia.org"
      },
      "subordinates": []
    },
    {
      "position": {
        "value": "Co-Founder & CTO",
        "source": "https://en.wikipedia.org"
      },
      "first_name": {
        "value": "John",
        "source": "https://en.wikipedia.org"
      },
      "last_name": {
        "value": "Doe",
        "source": "https://en.wikipedia.org"
      },
      "subordinates": []
    }
  ]
}
```

---

## FINAL CHECKLIST BEFORE OUTPUT

- [ ] Did I include the `insights` field (AI-generated analysis)?
- [ ] Does each team member have `position`, `first_name`, `last_name` with SourcedValue pattern?
- [ ] Does each SourcedValue have `value` and `source`?
- [ ] Is `source` a STRING (not an array)?
- [ ] Did I split names correctly (first_name/last_name)?
- [ ] Did I only include subordinates when relationship is CLEAR?
- [ ] Is the team array the TOP LEVEL of hierarchy (not everyone)?
- [ ] Are subordinates structured recursively (TeamMember[])?
- [ ] Is the output valid JSON only (no markdown code blocks)?

---

## Common Mistakes to Avoid

❌ **WRONG:** Using `sources` array instead of singular `source`
```json
{
  "position": {
    "value": "CEO",
    "sources": ["url1", "url2"]
  }
}
```
✅ **CORRECT:** Using singular `source` string
```json
{
  "position": {
    "value": "CEO",
    "source": "https://company.com/team/"
  }
}
```

❌ **WRONG:** Plain string fields without SourcedValue wrapper
```json
{
  "position": "CEO",
  "firstName": "Jane",
  "lastName": "Smith"
}
```
✅ **CORRECT:** All fields use SourcedValue pattern
```json
{
  "position": { "value": "CEO", "source": "https://..." },
  "first_name": { "value": "Jane", "source": "https://..." },
  "last_name": { "value": "Smith", "source": "https://..." }
}
```

❌ **WRONG:** Using camelCase field names
```json
{
  "firstName": { "value": "Jane", "source": "..." },
  "lastName": { "value": "Smith", "source": "..." }
}
```
✅ **CORRECT:** Using snake_case field names
```json
{
  "first_name": { "value": "Jane", "source": "..." },
  "last_name": { "value": "Smith", "source": "..." }
}
```

❌ **WRONG:** Not splitting names correctly
```json
{
  "first_name": { "value": "Jane Smith", "source": "..." },
  "last_name": { "value": "", "source": "..." }
}
```
✅ **CORRECT:** Split into first and last
```json
{
  "first_name": { "value": "Jane", "source": "..." },
  "last_name": { "value": "Smith", "source": "..." }
}
```

❌ **WRONG:** Inventing subordinates without clear evidence
```json
{
  "position": { "value": "CEO", "source": "..." },
  "subordinates": [
    { "position": { "value": "CTO", "source": "..." } }
  ]
}
```
✅ **CORRECT:** Only include subordinates when explicitly stated or clear from hierarchy

❌ **WRONG:** Including everyone in top-level array
```json
{
  "team": [
    { "position": { "value": "CEO", "source": "..." } },
    { "position": { "value": "CTO", "source": "..." } },
    { "position": { "value": "VP Engineering", "source": "..." } }
  ]
}
```
✅ **CORRECT:** VP should be under CTO in subordinates (if relationship is clear)

❌ **WRONG:** Missing top-level `insights` field
✅ **CORRECT:** Always include `insights` (AI-generated analysis)

❌ **WRONG:** Wrapping JSON in markdown code blocks
✅ **CORRECT:** Return only raw JSON object
