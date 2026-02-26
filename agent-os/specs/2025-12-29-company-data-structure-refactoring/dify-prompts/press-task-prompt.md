# Press Task - Dify Workflow Prompt

## Expected JSON Output Format

```typescript
interface PressAnalysis {
  insights: string  // AI-generated analysis (source: "Chaps-e")
  articles?: {
    value: string
    source: string  // Single source URL (NOT array)
  }[]
  press_releases?: {
    value: string
    source: string
  }[]
  media_mentions?: {
    value: string
    source: string
  }[]
  awards_recognition?: {
    value: string
    source: string
  }[]
  product_launches?: {
    value: string
    source: string
  }[]
  executive_interviews?: {
    value: string
    source: string
  }[]
  financial_news?: {
    value: string
    source: string
  }[]
  partnership_announcements?: {
    value: string
    source: string
  }[]
}
```

**CRITICAL CHANGE:** Each item uses `source: string` (singular), NOT `sources: string[]` (array).

---

## Full Prompt

You are tasked with analyzing input text to extract comprehensive press and media information about a company. Your goal is to identify and structure the output to match this exact JSON format:

```json
{
  "insights": "A 2-3 sentence analysis of the company's media presence and recent press activity.",
  "articles": [
    {
      "value": "Article title or summary - Publication, Date",
      "source": "https://publication.com/article-url"
    }
  ],
  "press_releases": [
    {
      "value": "Press release title - Date",
      "source": "https://company.com/press/release-url"
    }
  ],
  "awards_recognition": [
    {
      "value": "Award Name Year - Awarding Organization",
      "source": "https://source-url.com"
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
│           - Official pages (/press, /news, /media)     │
│           - Most up-to-date, direct from source        │
│                                                         │
│  Tier 1B: Wikipedia (IF RELEVANT)                      │
│           - Pre-filter: MUST be about this company     │
│           - If relevant: HIGH TRUST (same as 1A)       │
│           - If NOT relevant: IGNORE completely         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ TIER 2: MEDIUM TRUST (Google Search Results)           │
│         - Third-party media sources                     │
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

### Tier 1: HIGHEST TRUST - Official & Verified Sources

This tier includes sources that are highly reliable and up-to-date:

#### Tier 1A: Company Website Scraping
**Location in input:** `{{#1754928772178.company#}} complete website scraping results`
**Data structure:** `{{#1754928772178.scraped#}}`

**URL Priority Rules (within Tier 1A):**
1. **HIGHEST PRIORITY URLs** (official press/media pages):
   - `/press/`, `/press-releases/`, `/newsroom/`, `/media/`
   - `/news/`, `/media-center/`, `/press-room/`
   - `/blog/`, `/announcements/`

2. **MEDIUM PRIORITY URLs** (informational pages):
   - `/about/`, `/company/`
   - `/awards/`, `/recognition/`
   - Job posting sites mentioning company news

3. **LOWER PRIORITY URLs** (generic pages):
   - Homepage `/`
   - Product pages
   - Other sections

**Why Tier 1A is Highest Trust:**
- Most up-to-date press information
- Direct from official source
- Contains official press releases, announcements, awards

#### Tier 1B: Wikipedia (If Relevant to Company)
**Location in input:** `{{#1754928772178.wikipedia#}}`

**MANDATORY PRE-FILTER:**
Before using Wikipedia, check:
1. Does the Wikipedia page title mention the company name?
2. Is the content actually about this company?

**If YES (Wikipedia is about the company):**
- **Trust Level**: HIGH TRUST - same as Tier 1A
- Wikipedia company pages often list major events, awards, partnerships
- Use for missing fields or to supplement Tier 1A data
- Set source to `"https://en.wikipedia.org"`

**If NO (Wikipedia is NOT about the company):**
- **Action**: IGNORE COMPLETELY
- Wikipedia node can return irrelevant results
- Example: Company "ChapsVision" but Wikipedia returns "David Bowie" → IGNORE

**Why Tier 1B is High Trust (when relevant):**
- Wikipedia company pages are peer-reviewed and fact-checked
- Historical press events (major announcements, awards) especially reliable
- Regularly updated by multiple contributors

**When to use Tier 1B:**
- For historical press events not on Tier 1A
- Major awards and recognition
- Significant partnerships or acquisitions

---

### Tier 2: MEDIUM TRUST - Google Search Scraping Results
**Location in input:** `Specific Scraping results (medium trust) keep the scraped url as the source`
**Data structure:** `{{#1754999165157.items#}}`

**When to use:**
- If Tier 1 (1A + 1B) doesn't contain the specific press information
- For supplementary media coverage not found on official sources

**Caution:**
- May contain outdated information
- Cross-reference with Tier 1 when possible

### Tier 3: LOW TRUST - Knowledge Bases (Potentially Outdated)
**Mistral KB location:** `{{#1754928772178.mistral#}}`
**Claude KB location:** `{{#1754928772178.claude#}}`

**When to use:**
- ONLY if Tier 1 and Tier 2 don't contain the field
- Use as last resort for missing information

**Why Low Trust:**
- May be outdated (trained on old data)
- Could contain incorrect information superseded by recent changes
- Example: Old press releases, outdated award information

**Source Attribution:**
- For Mistral KB: Set source to `"Mistral Knowledge Base"`
- For Claude KB: Set source to `"Claude Knowledge Base"`

---

## CONFLICT RESOLUTION RULES

When multiple sources provide different values for the same field:

**Rule 1: Always Use Highest Tier**
```
Example:
- Tier 1A (Website /press/): Award = "Best Innovation 2024"
- Tier 3 (Mistral KB): Award = "Best Innovation 2023"
→ CORRECT OUTPUT: "Best Innovation 2024" with source from Tier 1A
```

**Rule 2: Within Tier 1A, Prefer Official Press Pages**
```
Example (both from Tier 1A - website):
- URL: /press-releases/ → Award = "Innovation Award 2024"
- URL: /about/ → Award = "Excellence Award 2023"
→ CORRECT OUTPUT: "Innovation Award 2024" (from /press-releases/)
```

**Rule 3: Tier 1A and 1B are Equal Priority**
```
When both Tier 1A (website) and Tier 1B (relevant Wikipedia) have press info:
- Prefer Tier 1A for recent news and press releases
- Use Tier 1B for historical events and major milestones
- Both are HIGH TRUST - use judgment based on context

Example:
- Tier 1A: Recent press releases from 2024
- Tier 1B (Wikipedia): Historical awards and major partnerships
→ ACCEPTABLE: Use both, they complement each other
```

**Rule 4: Ignore Lower-Tier Conflicting Data**
```
Do NOT mention or include data from lower tiers when higher tier exists.
Only extract from the highest tier available.
```

**Rule 5: Never Return Empty If Lower Tiers Have Data**

If a field is missing or marked as "Not specified" in Tier 1A:
1. Check Tier 1B (Wikipedia, if relevant about the company)
2. Check Tier 2 (Google search) - if present, use it
3. If not in Tier 2, check Tier 3 (Knowledge bases)
4. ONLY return empty [] if NO tier has the data

**Rule 6: One Item Per Source**

For array fields, each item should have its own single source. If the same press item appears in multiple places, choose the BEST source (highest tier, most authoritative).

```
Example:
- Press release on company site AND mentioned in TechCrunch
→ CORRECT: Use company site URL as the source (Tier 1A)
→ WRONG: Try to combine multiple sources into one item
```

---

## EXTRACTION REQUIREMENTS

### 1. insights (REQUIRED - AI Generated)
- **Source:** Always set to `"Chaps-e"` (this is AI-generated analysis)
- Summary of overall press and media presence
- Format: 2-3 sentence overview of the company's media visibility
- Include: Recent news trends, media coverage frequency, key themes
- Example: "Company has strong media presence with 15+ press releases in 2024. Recent coverage focuses on AI innovation and sustainability initiatives. Multiple industry awards received including Best Innovation Award."

### 2. articles (optional array)
- News articles about the company from external media
- Each item: `{ value: string, source: string }`
- Include: Title, date (if available), publication, brief summary
- Example: `{ "value": "TechCo's AI Revolution: A Deep Dive - TechCrunch, March 2024", "source": "https://techcrunch.com/2024/03/..." }`

### 3. press_releases (optional array)
- Official press releases published by the company
- Each item: `{ value: string, source: string }`
- Include: Title, date (if available), key announcement
- Example: `{ "value": "Company Announces $50M Series B Funding (March 2024)", "source": "https://company.com/press/..." }`

### 4. media_mentions (optional array)
- Brief mentions of the company in media (not full articles)
- Each item: `{ value: string, source: string }`
- Include: Where mentioned, context, date
- Example: `{ "value": "Featured in Forbes 30 Under 30 list (2024)", "source": "https://forbes.com/..." }`

### 5. awards_recognition (optional array)
- Awards, certifications, rankings received by the company
- Each item: `{ value: string, source: string }`
- Include: Award name, organization, year, category
- Example: `{ "value": "Best Startup Award 2024 - Tech Excellence Awards", "source": "https://company.com/awards/..." }`

### 6. product_launches (optional array)
- New product or service launches announced in the press
- Each item: `{ value: string, source: string }`
- Include: Product name, launch date, key features
- Example: `{ "value": "Launched AI Analytics Suite v2.0 (June 2024) - Advanced ML capabilities", "source": "https://company.com/press/..." }`

### 7. executive_interviews (optional array)
- Interviews with company executives in media
- Each item: `{ value: string, source: string }`
- Include: Executive name, publication, date, topic
- Example: `{ "value": "CEO Interview on AI Strategy - Bloomberg (May 2024)", "source": "https://bloomberg.com/..." }`

### 8. financial_news (optional array)
- Financial announcements, earnings, funding news
- Each item: `{ value: string, source: string }`
- Include: Type (funding/IPO/earnings), amount, date, investors
- Example: `{ "value": "Series B Funding $50M led by Sequoia Capital (March 2024)", "source": "https://company.com/press/..." }`

### 9. partnership_announcements (optional array)
- Strategic partnerships, collaborations, acquisitions
- Each item: `{ value: string, source: string }`
- Include: Partner name, type of partnership, date, goals
- Example: `{ "value": "Partnership with Microsoft for AI Integration (Feb 2024)", "source": "https://company.com/press/..." }`

---

## STEP-BY-STEP EXTRACTION PROCESS

### Step 1: Pre-Filter Wikipedia
- Check if Wikipedia page is relevant to the company
- If irrelevant (wrong company/person), mark Wikipedia as "SKIP" (ignore completely)
- If relevant (page is about the company), mark Wikipedia as "TIER 1B" (HIGH TRUST)

### Step 2: Identify Available Data in Tier 1
- Scan Tier 1A (website scraping) for all press-related fields
- Scan Tier 1B (relevant Wikipedia, if applicable) for all press-related fields
- Identify which fields are present in Tier 1 sources

### Step 3: Extract from Tier 1 First
- For each field found in Tier 1A or 1B:
  - Within Tier 1A, prioritize official press/news page URLs (/press, /news, /media)
  - Tier 1A and 1B are equal priority - prefer 1A for recent news, 1B for historical events
  - Extract value
  - Record exact source URL (single string, NOT array)
  - For array fields, create separate items for each press entry

### Step 4: Fill Gaps from Lower Tiers
- For fields NOT found in Tier 1A or 1B:
  - Check Tier 2 (Google search scraping) - if field exists, extract it
  - If not in Tier 2, check Tier 3 (knowledge bases)
  - When multiple lower tiers have the field, use the HIGHEST tier's value
  - NEVER return empty [] if ANY tier has the data

### Step 5: Generate Insights
- After extracting all fields, generate the `insights` summary
- Base insights on the extracted data (count of items, themes, recency)
- 2-3 sentences maximum
- Source is always "Chaps-e"

### Step 6: Format and Validate
- Ensure all array items have proper `{ value: string, source: string }` structure
- Ensure source URLs are preserved exactly as found (single string, NOT array)
- Double-check no lower-tier data overwrote higher-tier data
- Verify insights are accurate based on extracted data

---

## OUTPUT FORMAT

Return ONLY valid JSON matching the structure below. No markdown, no code blocks, no explanations - just the JSON object.

**Example of correct output:**
```json
{
  "insights": "Company has strong media presence with 12 press releases in 2024. Recent coverage focuses on AI innovation and Series B funding. Multiple industry awards received including Best Innovation Award from Tech Excellence.",
  "press_releases": [
    {
      "value": "Company Announces $50M Series B Funding - March 2024",
      "source": "https://www.company.com/press/series-b-funding-2024"
    },
    {
      "value": "Launches AI Analytics Platform 2.0 - January 2024",
      "source": "https://www.company.com/press/product-launch-ai-platform"
    },
    {
      "value": "Opens European Headquarters in Berlin - November 2023",
      "source": "https://www.company.com/press/berlin-hq-opening"
    }
  ],
  "articles": [
    {
      "value": "TechCo's AI Revolution: A Deep Dive - TechCrunch, March 2024",
      "source": "https://techcrunch.com/2024/03/techco-ai-revolution"
    },
    {
      "value": "How TechCo is Transforming Enterprise Analytics - Forbes, February 2024",
      "source": "https://forbes.com/2024/02/techco-enterprise-analytics"
    }
  ],
  "awards_recognition": [
    {
      "value": "Best Innovation Award 2024 - Tech Excellence Awards",
      "source": "https://www.company.com/awards/"
    },
    {
      "value": "Top 50 AI Startups 2024 - AI Magazine",
      "source": "https://aimagazine.com/top-50-2024"
    }
  ],
  "product_launches": [
    {
      "value": "AI Analytics Platform 2.0 (January 2024) - Advanced ML capabilities, real-time insights",
      "source": "https://www.company.com/press/product-launch-ai-platform"
    }
  ],
  "partnership_announcements": [
    {
      "value": "Strategic Partnership with Microsoft for AI Integration - February 2024",
      "source": "https://www.company.com/press/microsoft-partnership"
    },
    {
      "value": "Partnership with Salesforce for CRM Integration - December 2023",
      "source": "https://www.company.com/press/salesforce-partnership"
    }
  ],
  "financial_news": [
    {
      "value": "Series B Funding $50M led by Sequoia Capital (March 2024)",
      "source": "https://www.company.com/press/series-b-funding-2024"
    }
  ],
  "executive_interviews": [
    {
      "value": "CEO Interview: The Future of AI in Enterprise - Bloomberg, May 2024",
      "source": "https://bloomberg.com/2024/05/techco-ceo-interview"
    }
  ],
  "media_mentions": [
    {
      "value": "Featured in Forbes 30 Under 30 Europe - Technology (2024)",
      "source": "https://forbes.com/30-under-30-europe-2024"
    }
  ]
}
```

---

## DATA INPUT STRUCTURE

### Tier 1A: Company Website Scraping (HIGHEST TRUST)
```
{{#1754928772178.company#}} complete website scraping results (high trust) keep the scraped url as the source:
{{#1754928772178.scraped#}}
```

### Tier 1B: Wikipedia (HIGH TRUST IF RELEVANT, IGNORE IF NOT)
```
Wikipedia search (HIGH TRUST if about company, IGNORE if not) (set the source to "https://en.wikipedia.org"):
{{#1754928772178.wikipedia#}}
```

### Tier 2: Google Search Results (MEDIUM TRUST)
```
Specific Scraping results (medium trust) keep the scraped url as the source:
{{#1754999165157.items#}}
```

### Tier 3: Knowledge Bases (LOW TRUST)

#### Mistral Knowledge Base
```
Mistral knowledge base (low trust because it can be outdated) set the source to "Mistral Knowledge base"):
{{#1754928772178.mistral#}}
```

#### Claude Knowledge Base
```
Claude knowledge base (low trust because it can be outdated) set the source to "Claude Knowledge base"):
{{#1754928772178.claude#}}
```

---

## FINAL CHECKLIST BEFORE OUTPUT

- [ ] Did I include the `insights` field with source "Chaps-e"?
- [ ] Did I pre-filter Wikipedia first? (IGNORE if not about company, promote to Tier 1B if relevant)
- [ ] Did I extract from Tier 1A (website) for all available press fields?
- [ ] Did I extract from Tier 1B (relevant Wikipedia) for all available press fields?
- [ ] Did I prioritize official press/news page URLs within Tier 1A?
- [ ] Did I ignore Tier 2/3 data when Tier 1A/1B exists?
- [ ] For missing Tier 1 fields, did I check Tier 2, then Tier 3?
- [ ] Did I avoid returning empty arrays when ANY tier has data?
- [ ] Does each item have `value` (string) and `source` (string, NOT array)?
- [ ] Are all sources correctly attributed with exact URLs?
- [ ] Is the output valid JSON only (no markdown code blocks)?

---

## Common Mistakes to Avoid

❌ **WRONG:** Forgetting the top-level `insights` field
✅ **CORRECT:** Always include `insights` with source `"Chaps-e"`

❌ **WRONG:** Using `sources: []` (array) instead of `source: ""` (string)
```json
{ "value": "Award", "sources": ["https://..."] }
```
✅ **CORRECT:** Use singular `source` field with string value
```json
{ "value": "Award", "source": "https://..." }
```

❌ **WRONG:** Using press data from Mistral KB when website has current press releases
✅ **CORRECT:** Always use website press data over knowledge base

❌ **WRONG:** Including irrelevant Wikipedia data about other companies
✅ **CORRECT:** Filter Wikipedia first, ignore if not relevant

❌ **WRONG:** Returning empty array when lower tiers have press data
Example: Tier 1A has no awards, but Tier 2 has "Best Startup 2024" → returning []
✅ **CORRECT:** Fall back to Tier 2 and return `[{ "value": "Best Startup 2024", "source": "..." }]`

❌ **WRONG:** Missing source field in items
```json
{ "value": "Press Release Title" }
```
✅ **CORRECT:** Always include source
```json
{ "value": "Press Release Title", "source": "https://..." }
```

❌ **WRONG:** Treating relevant Wikipedia as low trust
Example: Ignoring Wikipedia awards when it's actually about the company
✅ **CORRECT:** Treat relevant Wikipedia as HIGH TRUST - same level as Tier 1A website data

❌ **WRONG:** Setting source to generic "website" or "knowledge base"
✅ **CORRECT:** Use exact URL as source string

❌ **WRONG:** Returning explanation or markdown around JSON
✅ **CORRECT:** Return only raw JSON object

❌ **WRONG:** Fabricating insights not based on extracted data
Example: "Company has 20 press releases" when only 5 were extracted
✅ **CORRECT:** Base insights on actual extracted data counts and content

---

## Press-Specific Extraction Tips

### For press_releases:
- Look for official press release pages (/press/, /newsroom/)
- Include date in the value if available
- Format: "Title - Date" or "Title (Date): Summary"

### For articles:
- Include publication name in the value
- Format: "Title - Publication, Date"
- External media only (not company blog posts)

### For awards_recognition:
- Include year and awarding organization
- Format: "Award Name Year - Organization"
- Verify it's an actual award, not a self-proclaimed title

### For product_launches:
- Include product name and launch date
- Format: "Product Name (Date) - Brief description"
- Only significant launches, not minor updates

### For partnership_announcements:
- Include partner name and date
- Format: "Partnership with [Partner] - Date: Description"
- Only strategic partnerships, not minor vendor relationships

### For financial_news:
- Include amount and type (funding/IPO/acquisition)
- Format: "Type $Amount led by [Investor] (Date)"
- Only official announcements, not rumors

### Date Format:
- When dates are available, use: "Month YYYY" (e.g., "March 2024")
- If only year: "YYYY" (e.g., "2024")
- If no date: Omit date from value
