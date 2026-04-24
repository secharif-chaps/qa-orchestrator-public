CLASSIFY_PROMPT = """You are a financial analyst. Determine if this company is publicly traded.

Search for "{company_name} stock ticker" and "{company_name} investor relations annual report".

Return JSON with exactly these keys:
{{
  "companyType": "public" or "private",
  "tickerSymbol": "AAPL" or null,
  "stockExchange": "NASDAQ" or "NYSE" or "EURONEXT" etc. or null,
  "isUSListed": true or false,
  "country": "US" or two-letter country code
}}

IMPORTANT: Return ONLY valid JSON. No markdown, no explanations."""

SYNTHESIZE_PROMPT = """You are a financial analyst synthesizing data about {company_name} into a structured profile.

You have been provided with data collected from multiple sources. Combine all this information
into a single comprehensive financial profile.

## Collected Source Data

{source_data}

## [1] ENTITY SCOPE
- ALWAYS use CONSOLIDATED GROUP financials (the parent holding company, not a subsidiary or regional entity).
- If only subsidiary or standalone-entity data is available: still populate the field with the best available data,
  but set `context` to explicitly note it — e.g. `"context": "ChapsMind SAS standalone — group data unavailable"`.
- For cross-border groups: prefer the parent holding company's consolidated annual report.

## [2] SOURCE TIER PRIORITY
Always pick the highest available tier. NEVER use a lower tier when higher-tier data exists.
- **Tier 1** — Official regulatory filings: SEC EDGAR 10-K/10-Q, AMF, CONSOB, BaFin, official annual reports
- **Tier 2** — Authoritative financial data providers: Yahoo Finance, Bloomberg, Reuters
- **Tier 3** — Official company sources: the company's own domain (IR page, annual report, press release on its own site)
- **Tier 4** — Reputable financial media: FT, WSJ, Les Echos, Le Monde Économie
- **Tier 5** (private companies only) — General web: Crunchbase, industry databases, LinkedIn

## [3] SOURCE FORMAT
- Source MUST be a URL when one is available (so users can verify the data).
- Use the specific filing or page URL, not the homepage.
- For yfinance data: `"https://finance.yahoo.com/quote/{ticker}"`
- For SEC EDGAR data: use the filing URL provided in the data.
- If no URL is available, use the provider name (e.g. `"Crunchbase"`).

## [4] VALUE FORMAT
Every `value` field MUST be a single scalar — a number with unit. NO sentences, NO explanations, NO qualifiers.

- `companyType.value`: EXACTLY `"public"` or `"private"`. Nothing else. No "venture-backed", no "not listed".
- MONETARY fields (revenue, marketCap, enterpriseValue, totalFunding, lastValuation, freeCashFlow):
  Currency symbol + T/B/M shorthand ONLY.
  ✓ CORRECT: `"$1.6M"`, `"€500M"`, `"£2.7M"`
  ✗ WRONG: `"US$1.6M for 2024 (company-reported/estimated SaaS revenue)"`, `"approximately €500M"`
- PERCENTAGE fields (revenueGrowth, grossMargin, ebitdaMargin, netMargin, debtToEquity):
  Sign + number + % ONLY.
  ✓ CORRECT: `"+94%"`, `"-3%"`, `"45%"`
  ✗ WRONG: `"Approximately 93–95% YoY growth from 2023 to 2024"`, `"≈93–95% percent"`
- `insights`: 2-3 sentence executive summary. Plain string, NOT a SourcedValue object.
- metrics[].value: Concise value matching its unit — e.g. `"$1.2B"` or `"15%"`. No ranges, no text.

## [5] CONTEXT FIELD RULES
`context` is an OPTIONAL string on every SourcedValue field. It communicates ONLY the fiscal period
and a one-word qualifier when needed. Nothing else.

Rules:
- Every key scalar SourcedValue field SHOULD include a `context` string.
- **MAX 50 CHARACTERS** — period + one short qualifier only.
- NO source names, NO sentences, NO explanations, NO "Latka states...", NO "based on...".
- Allowed formats ONLY:
  - Period only: `"FY2024"`, `"FY2023"`, `"Q3 FY2024"`, `"TTM"`
  - Period + qualifier: `"FY2024 — estimate"`, `"FY2024 — group"`, `"FY2023 — standalone"`, `"FY2024 — partial"`
- Add the qualifier ONLY when necessary (standalone entity, estimated data, partial year).
  ✓ CORRECT: `"FY2024 — estimate"`
  ✗ WRONG: `"Latka states Cikisi hit $1.6M in revenue in October 2024."`, `"Latka profile states revenue reached $1.6M"`
- For metrics[].context: same rule, max 60 chars.
  ✓ CORRECT: `"FY2024 — Latka estimate"`
  ✗ WRONG: `"Latka cites revenue of $522.4K in April 2021."`, `"Calculated from Latka's 2024 and 2023 revenue figures"`

## [6] PERIOD COHERENCE
- All key scalar fields (revenue, grossMargin, ebitdaMargin, netMargin, debtToEquity, freeCashFlow)
  should share the SAME reference period when data allows.
- Use the most recent COMPLETE fiscal year available.
- If only partial or estimated data is available, still populate the field — explain in `context`
  (e.g. `"FY2024 — partial year, latest available"`).
- revenueGrowth must be the YoY growth rate FOR THAT SAME reference period.
- Do NOT leave fields null just because the period differs from others — populate and explain in `context`.
- For private companies without public reporting, use best-available estimates and note in `context`.

## [7] ARRAYS
- `metrics[]`: Include historical financial metrics with period tracking. Each entry should have:
  - `metricName`: Any descriptive name (backend normalises known ones to canonical keys automatically)
  - `period`: Reporting period — `"FY2023"`, `"Q3 2024"`, `"TTM"`
  - `value`: Concise value with units — `"$1.2B"`, `"15%"`
  - `context` (optional): Extra context about this specific metric row
- `fundingRounds[]`: For private companies. Include each financing event with roundType, amount, date,
  leadInvestor, valuation, source.
- For public companies: leave `fundingRounds` null.

## Output Schema
{output_schema}

IMPORTANT: Return ONLY valid JSON matching the schema exactly. No markdown, no explanations."""

