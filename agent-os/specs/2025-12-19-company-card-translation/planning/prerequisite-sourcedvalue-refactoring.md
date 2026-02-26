# PREREQUISITE: SourcedValue Type System Refactoring

**Status**: Must be completed before Company Card Translation feature

## Problem Statement

The current Company type system has inconsistent patterns for storing sourced data, making it difficult to:
1. Add translation support uniformly
2. Maintain consistent source attribution
3. Build reusable helper functions

## Current State Analysis

### File: `front/src/types/company.ts`

#### Pattern 1: SourcedValue<T> (Good)
```typescript
profile: {
  businessLine: SourcedValue<string>  // { value, source, favicon? }
  revenue: SourcedValue<string>
}
```
**Used in**: profile, some digital fields, products arrays, csr arrays, jobs insights

#### Pattern 2: { value, sources[] } (Different)
```typescript
press: {
  articles?: {
    value: string
    sources: string[]  // Note: plural "sources", no favicon
  }[]
}
```
**Used in**: All press fields (articles, press_releases, media_mentions, etc.)

#### Pattern 3: Plain strings (No source)
```typescript
digital: {
  insights?: string  // No source attribution!
}
timeline: {
  insights?: string
}
```
**Used in**: All `insights` fields across sections

#### Pattern 4: Objects with inline source
```typescript
timeline: {
  events?: {
    title: string
    description: string
    source: string  // Source mixed into object
  }[]
}
```
**Used in**: timeline.events

#### Pattern 5: Plain arrays (No source)
```typescript
jobs: {
  offers?: {
    title: string
    description: string
    // No source!
  }[]
}
digital: {
  socialMediaAccounts?: {
    platform: string
    url: string
    // No source needed - URL is the source
  }[]
}
```
**Used in**: jobs.offers, digital.socialMediaAccounts

---

## Proposed Unified Type

### New SourcedValue Definition

```typescript
// types/sourced.ts

export type Language = 'en' | 'fr' | 'de' | 'es' | 'it' | 'pt' | 'nl'

/**
 * Universal wrapper for any value with source attribution and translations.
 * ALL data in ChapsMind should use this type for consistency.
 */
export interface SourcedValue<T> {
  /** Original value (always in English) */
  value: T

  /** Primary source URL or identifier (e.g., "llm:claude", "llm:mistral") */
  source: string

  /** Additional sources for aggregated data */
  sources?: string[]

  /** Favicon URL for the source */
  favicon?: string

  /** Translations keyed by language code */
  translations?: Partial<Record<Language, T>>
}
```

### Helper Functions

```typescript
// utils/sourcedValue.ts

/**
 * Extract value from SourcedValue, respecting current language.
 * Falls back to original if translation unavailable.
 */
export function sv<T>(sourcedValue: SourcedValue<T> | undefined): T | undefined

/**
 * Get primary source from SourcedValue
 */
export function svSource<T>(sourcedValue: SourcedValue<T> | undefined): string | undefined

/**
 * Get all sources from SourcedValue
 */
export function svSources<T>(sourcedValue: SourcedValue<T> | undefined): string[]

/**
 * Check if translation exists for current language
 */
export function svHasTranslation<T>(sourcedValue: SourcedValue<T> | undefined): boolean
```

---

## Migration Plan

### Phase 1: Type Updates (Frontend)

Update `front/src/types/company.ts`:

#### Profile Section
```typescript
// No changes needed - already uses SourcedValue correctly
export interface CompanyProfile {
  groupName?: SourcedValue<string>
  businessLine?: SourcedValue<string>
  // ... etc
}
```

#### Digital Section
```typescript
export interface CompanyDigital {
  insights?: SourcedValue<string>  // CHANGE: was plain string
  digitalStrategy?: SourcedValue<DigitalStrategy>
  onlineServices?: SourcedValue<OnlineService[]>
  socialMediaAccounts?: SocialMediaAccount[]  // KEEP: URL is the source
  loyaltyProgram?: SourcedValue<string>
}
```

#### Timeline Section
```typescript
export interface CompanyTimeline {
  insights?: SourcedValue<string>  // CHANGE: was plain string
  events?: SourcedValue<TimelineEvent>[]  // CHANGE: wrap each event
}

export interface TimelineEvent {
  date: string
  title: string
  description: string
  category: string
  location?: string
  impact?: string
  // REMOVE: source - now in SourcedValue wrapper
}
```

#### Products Section
```typescript
export interface CompanyProducts {
  insights?: SourcedValue<string>  // CHANGE: was plain string
  customerType?: SourcedValue<string>  // CHANGE: add SourcedValue
  marketingPositioning?: SourcedValue<string>  // CHANGE: add SourcedValue
  range?: SourcedValue<string>[]
  partnerBrands?: SourcedValue<string>[]
  privateLabels?: SourcedValue<string>[]
  categories?: Record<string, string[]>  // KEEP: structural data
}
```

#### Jobs Section
```typescript
export interface CompanyJobs {
  insights?: SourcedValue<JobInsights>  // CHANGE: wrap insights object
  offers?: SourcedValue<JobOffer>[]  // CHANGE: wrap each offer
}

export interface JobOffer {
  title: string
  location: string
  department: string
  description: string
  requirements: string
  posted_date: string
}

export interface JobInsights {
  total_openings: number
  top_departments: string[]
  hiring_focus: string
  growth_indicators: string
}
```

#### CSR Section
```typescript
export interface CompanyCsr {
  insights?: SourcedValue<string>  // CHANGE: was plain string
  responsibility?: SourcedValue<string>  // CHANGE: was plain string
  responsibility_initiatives?: SourcedValue<string>[]
  charity_actions?: SourcedValue<string>[]
  // ... etc (arrays already correct)
}
```

#### Press Section
```typescript
export interface CompanyPress {
  insights?: SourcedValue<string>  // CHANGE: was plain string
  articles?: SourcedValue<string>[]  // CHANGE: unify pattern
  press_releases?: SourcedValue<string>[]
  media_mentions?: SourcedValue<string>[]
  awards_recognition?: SourcedValue<string>[]
  product_launches?: SourcedValue<string>[]
  executive_interviews?: SourcedValue<string>[]
  financial_news?: SourcedValue<string>[]
  partnership_announcements?: SourcedValue<string>[]
}
```

#### Team Section
```typescript
// KEEP: Team structure is different - hierarchical with linkedIn URLs as sources
export interface TeamMember {
  position: string
  firstName: string
  lastName: string
  linkedinUrl?: string  // This IS the source
  subordinates?: TeamMember[]
}
```

---

### Phase 2: Backend Updates

1. Update Pydantic schemas to match new structure
2. Update any data transformation logic
3. Ensure Dify workflows output in correct format (or add transformer)

### Phase 3: Data Migration

Create Alembic migration to transform existing data:

```python
def upgrade():
    # Transform existing data to new format
    # - Wrap plain insights strings in SourcedValue with source="llm:claude"
    # - Convert press items from {value, sources[]} to SourcedValue
    # - Wrap timeline events in SourcedValue
    # - etc.
    pass
```

### Phase 4: Component Updates

1. Replace all `getSourcedValue()` calls with `sv()`
2. Update Source component to work with new helpers
3. Remove old helper functions

---

## Open Decisions

### Decision 1: Insights Source Attribution

**Question**: What source should insights fields have?

**Options**:
- A) `"llm:claude"` or `"llm:mistral"` based on which LLM generated it
- B) Generic `"llm"` source
- C) Keep as plain strings (no SourcedValue wrapper)

**Recommendation**: Option A - Track which LLM generated the insight for transparency

### Decision 2: Fields Without Natural Sources

**Question**: What about fields where the source is structural/derived?

**Examples**:
- `products.categories` - Derived from product data
- `products.customerType` - LLM analysis

**Options**:
- A) Wrap everything in SourcedValue for consistency
- B) Keep structural/derived data unwrapped

**Recommendation**: Option A for translatable text, Option B for structural data

### Decision 3: Migration Approach

**Question**: How to handle transition period?

**Options**:
- A) Big bang - migrate all data at once, update all code
- B) Gradual - support both formats, migrate incrementally

**Recommendation**: Option A - Cleaner, less technical debt

---

## Acceptance Criteria

- [ ] All text data in Company has source attribution
- [ ] Single `SourcedValue<T>` type used everywhere
- [ ] Single `sv()` helper function for value extraction
- [ ] Existing data migrated to new format
- [ ] All components updated to use new helpers
- [ ] No regressions in company card display
- [ ] Type safety maintained (no `any` types)
