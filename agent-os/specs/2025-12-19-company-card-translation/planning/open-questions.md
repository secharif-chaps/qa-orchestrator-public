# Open Questions - Company Card Translation

## Questions Requiring Decisions

### Q1: Insights Source Attribution
**Context**: Currently `insights` fields are plain strings with no source tracking.

**Question**: Should insights become `SourcedValue<string>`? If so, what source?

**Options**:
- A) `"llm:claude"` or `"llm:mistral"` - Track specific LLM
- B) Generic `"llm"` - Simpler but less transparent
- C) Keep as plain strings - No change

**Impact**: Affects all 8 company sections that have insights fields.

---

### Q2: Non-Translatable Fields
**Context**: Some fields don't need translation (URLs, structural data).

**Question**: Should we wrap them in SourcedValue for consistency?

**Examples**:
- `socialMediaAccounts` - Just platform + URL
- `products.categories` - Category names (might need translation?)
- `timeline.events[].date` - Dates don't translate

**Options**:
- A) Wrap everything for consistency
- B) Only wrap translatable text fields
- C) Define a clear rule (e.g., "all user-facing text")

---

### Q3: Complex Nested Structures
**Context**: `digital.digitalStrategy` is a `SourcedValue` containing an object with 5 text fields.

```typescript
digitalStrategy?: SourcedValue<{
  overallStrategy: string
  digitalTransformation: string
  eCommerceCapabilities: string
  mobileStrategy: string
  digitalMarketingApproach: string
}>
```

**Question**: How should translations be structured?

**Options**:
- A) Translate the whole object:
  ```json
  translations: {
    "fr": {
      "overallStrategy": "...",
      "digitalTransformation": "..."
    }
  }
  ```
- B) Flatten to individual SourcedValues (breaking change)

**Recommendation**: Option A - Keeps structure, works with current type.

---

### Q4: Migration Strategy
**Context**: Existing company data needs to be transformed to new format.

**Question**: How to handle the migration?

**Options**:
- A) Big bang - One-time migration, update all code simultaneously
- B) Gradual - Support both formats during transition period
- C) Transform on read - Keep old format in DB, transform in API layer

**Considerations**:
- How much existing data?
- Risk tolerance for downtime?
- Development complexity?

---

### Q5: Systran API Details
**Context**: We'll use Systran for translation but don't have docs yet.

**Questions to answer once docs available**:
- [ ] What's the API endpoint URL format?
- [ ] Authentication method (API key header? OAuth?)
- [ ] Rate limits (requests per minute/day?)
- [ ] Batch translation support?
- [ ] Maximum text length per request?
- [ ] Error response format?
- [ ] Supported language codes (match our codes?)

---

### Q6: Translation Caching Strategy
**Context**: Translations are stored in SourcedValue.translations.

**Question**: Any additional caching needed?

**Considerations**:
- If same text appears in multiple companies, translate once or per-company?
- Redis cache for hot translations?
- Or is per-company storage sufficient?

**Initial decision**: Per-company storage is sufficient for MVP.

---

### Q7: Team Section Translation
**Context**: Team uses a different structure with `TeamMember` objects.

```typescript
interface TeamMember {
  position: string       // Translatable
  firstName: string      // NOT translatable
  lastName: string       // NOT translatable
  linkedinUrl?: string   // NOT translatable (URL)
  subordinates?: TeamMember[]
}
```

**Question**: How to handle position translation?

**Options**:
- A) Wrap TeamMember in SourcedValue, translate position only
- B) Add translations field directly to TeamMember interface
- C) Skip team translation for MVP

---

## Answered Questions

### A1: Storage Location
**Decision**: Embed translations in SourcedValue.translations field (not separate columns)

### A2: Languages to Support
**Decision**: EN, FR, DE, ES, IT, PT, NL (7 languages)

### A3: Translation Trigger
**Decision**: Manual trigger via UI button (not automatic on company creation)

### A4: Partial Translation Handling
**Decision**: Show partial translation with fallback to original for failed fields

### A5: Language Persistence
**Decision**: Store in localStorage, remember across sessions

### A6: Translation Reuse
**Decision**: Reuse cached translations (if already translated in a language, don't re-translate)
