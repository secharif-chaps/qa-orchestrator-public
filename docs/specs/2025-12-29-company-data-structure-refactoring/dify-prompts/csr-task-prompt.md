# CSR Task - Dify Workflow Prompt

## Expected JSON Output Format

```typescript
interface CSRAnalysis {
  insights: string  // AI-generated analysis (source: "Chaps-e")
  responsibility?: {
    value: string
    source: string
  }
  initiatives: {
    type: 'responsibility' | 'charity' | 'sustainability' | 'community' | 'diversity' | 'ethics' | 'awards'
    value: string
    source: string  // Single source URL (NOT array)
  }[]
}
```

**CRITICAL CHANGES:**
- Each item uses `source: string` (singular), NOT `sources: string[]` (array)
- All initiatives are in a unified `initiatives` array with a `type` field
- Required top-level `insights` field with source "Chaps-e"

---

## Full Prompt

You are tasked with analyzing input text to extract comprehensive CSR (Corporate Social Responsibility) information about a company. Your goal is to identify and structure the output to match this exact JSON format:

```json
{
  "insights": "A 2-3 sentence analysis of the company's CSR presence and focus areas.",
  "responsibility": {
    "value": "Main CSR responsibility statement or commitment",
    "source": "https://company.com/csr/"
  },
  "initiatives": [
    {
      "type": "sustainability",
      "value": "Carbon neutrality commitment by 2030",
      "source": "https://company.com/sustainability/"
    },
    {
      "type": "charity",
      "value": "$5M annual community grants program",
      "source": "https://company.com/community/"
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
│           - Official pages (/csr, /sustainability)     │
│           - Most up-to-date, direct from source        │
│                                                         │
│  Tier 1B: Wikipedia (IF RELEVANT)                      │
│           - Pre-filter: MUST be about this company     │
│           - If relevant: HIGH TRUST (same as 1A)       │
│           - If NOT relevant: IGNORE completely         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ TIER 2: MEDIUM TRUST (Google Search Results)           │
│         - Third-party sources (news, reports)          │
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

**URL Priority Rules (within Tier 1):**
1. **HIGHEST PRIORITY URLs** (official CSR pages):
   - `/csr/`, `/corporate-social-responsibility/`, `/social-responsibility/`
   - `/sustainability/`, `/esg/`, `/impact/`
   - `/community/`, `/giving/`, `/philanthropy/`
   - `/diversity/`, `/inclusion/`, `/dei/`
   - `/environment/`, `/climate/`, `/carbon/`

2. **MEDIUM PRIORITY URLs** (informational pages):
   - `/about/`, `/values/`, `/mission/`
   - `/careers/` (diversity information)
   - `/press/`, `/news/` (CSR announcements)

3. **LOWER PRIORITY URLs** (generic pages):
   - Homepage `/`
   - Product pages
   - Other sections

**Why Tier 1A is Highest Trust:**
- Most up-to-date CSR information
- Direct from official source
- Contains current initiatives, recent certifications, active programs

#### Tier 1B: Wikipedia (If Relevant to Company)
**Location in input:** `{{#1754928772178.wikipedia#}}`

**MANDATORY PRE-FILTER:**
Before using Wikipedia, check:
1. Does the Wikipedia page title mention the company name?
2. Is the content actually about this company?

**If YES (Wikipedia is about the company):**
- **Trust Level**: HIGH TRUST - same as Tier 1A
- Wikipedia company pages often include CSR controversies and major initiatives
- Use for historical CSR context or major social impact events
- Set source to `"https://en.wikipedia.org"`

**If NO (Wikipedia is NOT about the company):**
- **Action**: IGNORE COMPLETELY
- Wikipedia node can return irrelevant results
- Example: Company "GreenTech" but Wikipedia returns "Green technology" → IGNORE

**Why Tier 1B is High Trust (when relevant):**
- Wikipedia includes verified CSR controversies and initiatives
- Historical CSR events well-documented
- Third-party verification through citations

**When to use Tier 1B:**
- For historical CSR context (past initiatives, controversies)
- Major social impact milestones
- Certifications and awards mentioned
- To supplement Tier 1A data

---

### Tier 2: MEDIUM TRUST - Google Search Scraping Results
**Location in input:** `Specific Scraping results (medium trust) keep the scraped url as the source`
**Data structure:** `{{#1754999165157.items#}}`

**When to use:**
- If Tier 1 (1A + 1B) doesn't contain the specific field you're looking for
- For third-party CSR reports and news coverage
- Sustainability ratings from external sources

**Caution:**
- May contain outdated CSR information
- Cross-reference with Tier 1 when possible
- Verify dates of external reports

### Tier 3: LOW TRUST - Knowledge Bases (Potentially Outdated)
**Mistral KB location:** `{{#1754928772178.mistral#}}`
**Claude KB location:** `{{#1754928772178.claude#}}`

**When to use:**
- ONLY if Tier 1 and Tier 2 don't contain the field
- Use as last resort for missing CSR information

**Why Low Trust:**
- May be outdated (trained on old data)
- Could contain incorrect information superseded by recent changes
- Example: Old sustainability goals, outdated certifications, discontinued programs

**Source Attribution:**
- For Mistral KB: Set source to `"Mistral Knowledge Base"`
- For Claude KB: Set source to `"Claude Knowledge Base"`

---

## CONFLICT RESOLUTION RULES

When multiple sources provide different values for the same field:

**Rule 1: Always Use Highest Tier**
```
Example:
- Tier 1A (Website /sustainability/): Carbon neutrality goal = "2030"
- Tier 3 (Mistral KB): Carbon neutrality goal = "2025"
→ CORRECT OUTPUT: "2030" with source from Tier 1A
```

**Rule 2: Within Tier 1A, Prefer Official CSR Pages**
```
Example (both from Tier 1A - website):
- URL: /csr/ → Charity program = "Community Grants $5M annually"
- URL: /about/ → Charity program = "Various community programs"
→ CORRECT OUTPUT: "Community Grants $5M annually" (from /csr/)
```

**Rule 3: Tier 1A and 1B are Equal Priority**
```
When both Tier 1A (website) and Tier 1B (relevant Wikipedia) have CSR info:
- Prefer Tier 1A for current initiatives and programs
- Use Tier 1B for historical context and controversies
- Both are HIGH TRUST - use judgment based on context

Example:
- Tier 1A: Current sustainability programs from 2024
- Tier 1B (Wikipedia): Historical environmental controversies
→ ACCEPTABLE: Use both, they provide different perspectives
```

**Rule 4: Ignore Lower-Tier Conflicting Data**
```
Do NOT mention or include data from lower tiers when higher tier exists.
Only extract from the highest tier available.
```

**Rule 5: Never Return Empty If Lower Tiers Have Data**

If a field is missing or not found in Tier 1A:
1. Check Tier 1B (Wikipedia, if relevant about the company)
2. Check Tier 2 (Google search) - if present, use it
3. If not in Tier 2, check Tier 3 (Knowledge bases)
4. ONLY return empty `[]` if NO tier has the data

**Rule 6: Tier Priority Beats Specificity**

When higher-tier data is less specific than lower-tier data, STILL use the higher-tier data.

**Example:**
```
- Tier 1A (Website): "Sustainability initiatives in progress"
- Tier 3 (KB): "Solar panel installation at 10 facilities, LED lighting retrofit"
→ CORRECT OUTPUT: "Sustainability initiatives in progress" with Tier 1A source
→ WRONG OUTPUT: "Solar panel installation..." with Tier 3 source (violates tier priority)
```

---

## EXTRACTION REQUIREMENTS

### 1. insights (REQUIRED - AI Generated)
- **Source:** Always set to `"Chaps-e"` (this is AI-generated analysis)
- Summary of company's CSR presence and focus areas
- Format: 2-3 sentence overview
- Example: "Company demonstrates strong commitment to environmental sustainability with carbon neutrality goals and comprehensive renewable energy programs. Active community engagement through annual grants exceeding $5M. Strong DEI focus with women comprising 45% of leadership positions."

### 2. responsibility (Optional)
- Main CSR responsibility statement or commitment
- Format: `{ value: string, source: string }`
- The overarching CSR mission or commitment statement
- Example: `{ "value": "Committed to creating positive social and environmental impact through responsible business practices", "source": "https://company.com/csr/" }`

### 3. initiatives (Array with Type Field)
All CSR initiatives go into a unified array with a `type` field to categorize them.

**Initiative Types:**

| Type | Description | Examples |
|------|-------------|----------|
| `responsibility` | General CSR initiatives | Supplier Code of Conduct, stakeholder engagement |
| `charity` | Charitable donations, philanthropy | Community grants, disaster relief, matching donations |
| `sustainability` | Environmental programs | Carbon neutrality, renewable energy, zero waste |
| `community` | Local community engagement | STEM education, volunteer programs, local hiring |
| `diversity` | DEI initiatives | Women in leadership, DEI training, supplier diversity |
| `ethics` | Business ethics, governance | B-Corp certification, Fair Trade, anti-corruption |
| `awards` | CSR awards and certifications | CDP Climate A-List, Great Place to Work |

**Format for each initiative:**
```json
{
  "type": "sustainability",
  "value": "Carbon neutrality commitment by 2030 with interim target of 50% emissions reduction by 2025",
  "source": "https://company.com/sustainability/climate/"
}
```

---

## STEP-BY-STEP EXTRACTION PROCESS

### Step 1: Pre-Filter Wikipedia
- Check if Wikipedia page is relevant to the company
- If irrelevant (wrong company/topic), mark Wikipedia as "SKIP" (ignore completely)
- If relevant (page is about the company), mark Wikipedia as "TIER 1B" (HIGH TRUST)

### Step 2: Identify Available Data in Tier 1
- Scan Tier 1A (website scraping) for all CSR fields
- Scan Tier 1B (relevant Wikipedia, if applicable) for all fields
- Identify which fields are present in Tier 1 sources

### Step 3: Extract from Tier 1 First
- For each field found in Tier 1A or 1B:
  - Within Tier 1A, prioritize official CSR page URLs (/csr, /sustainability, /community)
  - Tier 1A and 1B are equal priority - prefer 1A if both have complete data
  - Extract all relevant items
  - Assign the appropriate `type` to each initiative
  - Record exact source URL (single string, NOT array)

### Step 4: Fill Gaps from Lower Tiers
- For initiatives NOT found in Tier 1A or 1B:
  - Check Tier 2 (Google search scraping) - if field exists, extract it
  - If not in Tier 2, check Tier 3 (knowledge bases)
  - When multiple lower tiers have the field, use the HIGHEST tier's value
  - NEVER return empty array if ANY tier has the data

### Step 5: Generate Insights
- After extracting all fields, generate the `insights` summary
- Base insights on the extracted data (count of initiatives, themes, focus areas)
- 2-3 sentences maximum
- Source is always "Chaps-e"

### Step 6: Format and Validate
- Ensure all initiatives have `type`, `value`, and `source` fields
- Ensure source is a string (NOT an array)
- Double-check no lower-tier data overwrote higher-tier data

---

## OUTPUT FORMAT

Return ONLY valid JSON matching the structure below. No markdown, no code blocks, no explanations - just the JSON object.

**Example of correct output:**
```json
{
  "insights": "Company demonstrates strong commitment to environmental sustainability with carbon neutrality goals and comprehensive renewable energy programs. Active community engagement through annual grants exceeding $5M and employee volunteer initiatives. Strong DEI focus with women comprising 45% of leadership positions and comprehensive supplier diversity programs.",
  "responsibility": {
    "value": "Committed to creating positive social and environmental impact through responsible business practices and stakeholder engagement",
    "source": "https://www.company.com/csr/"
  },
  "initiatives": [
    {
      "type": "responsibility",
      "value": "Supplier Code of Conduct implemented across global supply chain (2023) - covers labor rights, environmental standards, and ethical business practices",
      "source": "https://www.company.com/csr/supply-chain/"
    },
    {
      "type": "charity",
      "value": "$5M annual community grants program supporting local nonprofits in education, health, and environmental conservation",
      "source": "https://www.company.com/community/grants/"
    },
    {
      "type": "charity",
      "value": "Employee matching donations program up to $5,000 per employee annually",
      "source": "https://www.company.com/csr/employee-giving/"
    },
    {
      "type": "sustainability",
      "value": "Carbon neutrality commitment by 2030 with interim target of 50% emissions reduction by 2025",
      "source": "https://www.company.com/sustainability/climate/"
    },
    {
      "type": "sustainability",
      "value": "100% renewable energy for all facilities worldwide achieved in 2024",
      "source": "https://www.company.com/sustainability/energy/"
    },
    {
      "type": "sustainability",
      "value": "Zero waste to landfill program across all manufacturing sites - 95% waste diversion rate",
      "source": "https://www.company.com/sustainability/waste/"
    },
    {
      "type": "community",
      "value": "STEM education partnership with 50 schools reaching 10,000 students annually through mentorship and funding",
      "source": "https://www.company.com/community/education/"
    },
    {
      "type": "community",
      "value": "Community volunteer program: 5,000+ employee volunteer hours in 2024 supporting local organizations",
      "source": "https://www.company.com/csr/volunteering/"
    },
    {
      "type": "diversity",
      "value": "Women in leadership: 45% of management positions globally (2024)",
      "source": "https://www.company.com/diversity/leadership/"
    },
    {
      "type": "diversity",
      "value": "DEI training program for all employees launched (2023) with quarterly unconscious bias workshops",
      "source": "https://www.company.com/diversity/training/"
    },
    {
      "type": "diversity",
      "value": "Supplier diversity program: 30% spend with minority-owned businesses",
      "source": "https://www.company.com/diversity/suppliers/"
    },
    {
      "type": "ethics",
      "value": "B-Corp certification achieved (2023) - meeting highest standards of social and environmental performance",
      "source": "https://www.company.com/csr/b-corp/"
    },
    {
      "type": "ethics",
      "value": "Fair Trade certification for all coffee and cocoa sourcing",
      "source": "https://www.company.com/sustainability/sourcing/"
    },
    {
      "type": "awards",
      "value": "CDP Climate A-List recognition (2024) for climate action and transparency leadership",
      "source": "https://www.company.com/press/cdp-recognition/"
    },
    {
      "type": "awards",
      "value": "Great Place to Work certification (2024) - 15th consecutive year",
      "source": "https://www.company.com/careers/awards/"
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
- [ ] Did I extract from Tier 1A (website) for all available fields?
- [ ] Did I extract from Tier 1B (relevant Wikipedia) for all available fields?
- [ ] Did I prioritize official CSR page URLs within Tier 1A?
- [ ] Did I ignore Tier 2/3 data when Tier 1A/1B exists?
- [ ] For missing Tier 1 fields, did I check Tier 2, then Tier 3?
- [ ] Did I avoid returning empty arrays when ANY tier has data?
- [ ] Does each initiative have `type`, `value`, and `source` fields?
- [ ] Is `source` a string (NOT an array)?
- [ ] Is the output valid JSON only (no markdown code blocks)?

---

## Common Mistakes to Avoid

❌ **WRONG:** Forgetting the top-level `insights` field
✅ **CORRECT:** Always include `insights` with source `"Chaps-e"`

❌ **WRONG:** Using `sources: []` (array) instead of `source: ""` (string)
```json
{ "type": "sustainability", "value": "...", "sources": ["https://..."] }
```
✅ **CORRECT:** Use singular `source` field with string value
```json
{ "type": "sustainability", "value": "...", "source": "https://..." }
```

❌ **WRONG:** Using separate arrays for each initiative type
```json
{
  "charity_actions": [...],
  "sustainability_programs": [...],
  "diversity_inclusion": [...]
}
```
✅ **CORRECT:** Unified `initiatives` array with `type` field
```json
{
  "initiatives": [
    { "type": "charity", "value": "...", "source": "..." },
    { "type": "sustainability", "value": "...", "source": "..." },
    { "type": "diversity", "value": "...", "source": "..." }
  ]
}
```

❌ **WRONG:** Using outdated sustainability goals from KB when website has current goals
✅ **CORRECT:** Always use website sustainability data over knowledge base

❌ **WRONG:** Returning empty array when lower tiers have CSR data
Example: Tier 1 has no diversity data, but Tier 2 has "40% women in leadership" → returning []
✅ **CORRECT:** Fall back to Tier 2 and return the data

❌ **WRONG:** Including irrelevant Wikipedia data about other companies
✅ **CORRECT:** Filter Wikipedia first, ignore if not relevant

❌ **WRONG:** Missing `type` field in initiatives
```json
{ "value": "Carbon neutrality by 2030", "source": "..." }
```
✅ **CORRECT:** Always include type
```json
{ "type": "sustainability", "value": "Carbon neutrality by 2030", "source": "..." }
```

❌ **WRONG:** Wrapping JSON in markdown code blocks
✅ **CORRECT:** Return only raw JSON object

---

## CSR-Specific Extraction Tips

### For sustainability initiatives:
- Look for climate/carbon goals with target years
- Include metrics (% reduction, renewable energy %)
- Note certifications (ISO 14001, carbon neutral)

### For charity initiatives:
- Include dollar amounts when available
- Note beneficiaries and focus areas
- Include employee programs (matching, volunteering)

### For diversity initiatives:
- Include specific percentages and metrics
- Note leadership representation
- Include supplier diversity programs

### For ethics initiatives:
- Look for certifications (B-Corp, Fair Trade)
- Note governance policies
- Include compliance programs

### For awards:
- Include year and awarding organization
- Note significance (A-list, consecutive years)
- Include external certifications

### Date Format:
- When dates are available, use: "Month YYYY" or "(YYYY)"
- Example: "Carbon neutrality commitment by 2030"
- Example: "B-Corp certification achieved (2023)"
