# Company Card Translation Feature

## Overview

Add the ability to translate company card data into multiple languages on-demand. Users can select their preferred language from a dropdown on the company card page. Translations are performed using the Systran API and stored alongside original values for reuse.

## Goals

1. Allow users to view company data in their preferred language
2. Preserve original English data with source attribution
3. Cache translations for reuse across users/sessions
4. Provide seamless UX with progressive loading (show original, then translated)

---

## Supported Languages

| Code | Language | Flag |
|------|----------|------|
| `en` | English | 🇬🇧 | (default/original)
| `fr` | French | 🇫🇷 |
| `de` | German | 🇩🇪 |
| `es` | Spanish | 🇪🇸 |
| `it` | Italian | 🇮🇹 |
| `pt` | Portuguese | 🇵🇹 |
| `nl` | Dutch | 🇳🇱 |

---

## Prerequisites

### PREREQUISITE: SourcedValue Type System Refactoring

**Status**: Required before implementation

Before implementing translation, the Company type system needs refactoring for consistency and to embed translations directly in the data structure.

#### Current Issues

| Pattern | Example | Issue |
|---------|---------|-------|
| `SourcedValue<T>` | `profile.businessLine` | Good, but no translation support |
| `{ value, sources[] }` | `press.articles` | Different structure, plural sources |
| Plain strings | `insights` fields | No source attribution |
| Plain arrays | `timeline.events`, `jobs.offers` | No source per item |
| `{ source }` inline | `timeline.events[].source` | Source mixed in object |

#### Proposed Unified SourcedValue Type

```typescript
/**
 * Universal wrapper for any value with source attribution and translations.
 * ALL data in ChapsMind should use this type for consistency.
 */
export interface SourcedValue<T> {
  value: T                                    // Original value (always English)
  source: string                              // Primary source URL or "llm:claude", "llm:mistral"
  sources?: string[]                          // Additional sources (for aggregated data)
  favicon?: string                            // Source favicon URL
  translations?: Partial<Record<Language, T>> // Translations keyed by language
}

export type Language = 'en' | 'fr' | 'de' | 'es' | 'it' | 'pt' | 'nl'
```

#### Benefits

- Translation embedded IN each SourcedValue (no separate columns)
- One helper function `sv()` instead of multiple complex functions
- Consistent pattern across all company data
- Source attribution always present
- No database schema changes needed (translations stored in existing JSON columns)

---

## Architecture

### Data Flow

```
User clicks language →
  If translation cached → Display immediately
  If not cached → Call Systran API → Store in SourcedValue.translations → Display
```

### Translation Storage

Translations are stored directly in each SourcedValue's `translations` field:

```json
{
  "value": "Food retail and distribution",
  "source": "https://carrefour.com/about",
  "favicon": "https://carrefour.com/favicon.ico",
  "translations": {
    "fr": "Distribution alimentaire",
    "de": "Lebensmitteleinzelhandel"
  }
}
```

### Fields to Translate

The 8 JSON fields containing company data:

1. `profile` - Business info (businessLine, catchphrase, etc.)
2. `digital` - Digital presence (insights, digitalStrategy, etc.)
3. `timeline` - Company history (insights, event descriptions)
4. `products` - Products & services (insights, range descriptions)
5. `jobs` - Job offerings (insights, job descriptions)
6. `csr` - CSR initiatives (insights, initiative descriptions)
7. `press` - Press coverage (insights, article summaries)
8. `team` - Team info (position titles if applicable)

---

## Frontend Implementation

### Translation Store

```typescript
// stores/companyTranslation.ts
export const useCompanyTranslationStore = defineStore('companyTranslation', () => {
  // Current language (persisted in localStorage)
  const selectedLanguage = ref<Language>(
    (localStorage.getItem('companyCardLanguage') as Language) || 'en'
  )

  // Translation in progress
  const isTranslating = ref(false)
  const translatingCompanyId = ref<number | null>(null)

  function setLanguage(lang: Language) {
    selectedLanguage.value = lang
    localStorage.setItem('companyCardLanguage', lang)
  }

  return {
    selectedLanguage,
    isTranslating,
    translatingCompanyId,
    setLanguage,
  }
})
```

### Unified Helper Function

```typescript
// utils/sourcedValue.ts
import { useCompanyTranslationStore } from '@/stores/companyTranslation'

/**
 * Extract value from SourcedValue, respecting current language preference.
 * Automatically falls back to original if translation unavailable.
 */
export function sv<T>(sourcedValue: SourcedValue<T> | undefined): T | undefined {
  if (!sourcedValue) return undefined

  const store = useCompanyTranslationStore()
  const lang = store.selectedLanguage

  // Return translation if available, otherwise original
  if (lang !== 'en' && sourcedValue.translations?.[lang] !== undefined) {
    return sourcedValue.translations[lang]
  }

  return sourcedValue.value
}

/**
 * Get the source from a SourcedValue for display
 */
export function svSource<T>(sourcedValue: SourcedValue<T> | undefined): string | undefined {
  return sourcedValue?.source
}

/**
 * Check if translation exists for current language
 */
export function svHasTranslation<T>(sourcedValue: SourcedValue<T> | undefined): boolean {
  if (!sourcedValue) return false
  const store = useCompanyTranslationStore()
  return sourcedValue.translations?.[store.selectedLanguage] !== undefined
}
```

### Component Usage

```vue
<script setup lang="ts">
import { sv, svSource } from '@/utils/sourcedValue'
</script>

<template>
  <!-- Simple value extraction with auto-translation -->
  <p>{{ sv(company?.profile?.businessLine) }}</p>

  <!-- Source display (unchanged regardless of language) -->
  <Source :source="svSource(company?.profile?.businessLine)" />
</template>
```

### Language Selector Component

A dropdown on the company card page showing:
- All supported languages
- Availability tag (Available / Translate)
- Current selection indicator

**Behavior**:
- Click on available language → Switch display immediately
- Click on unavailable language → Trigger translation, show loading state

---

## Backend Implementation

### Systran API Integration

```python
# services/translation.py
class SystranTranslationService:
    def __init__(self, api_key: str, endpoint: str):
        self.api_key = api_key
        self.endpoint = endpoint

    async def translate(self, text: str, target_lang: str) -> str:
        """Translate text from English to target language."""
        # Implementation details TBD based on Systran API docs
        pass

    async def translate_company(self, company_id: int, target_lang: str) -> Company:
        """Translate all translatable fields of a company."""
        pass
```

### API Endpoint

```python
# POST /companies/{company_id}/translate
@router.post("/{company_id}/translate")
async def translate_company(
    company_id: int,
    target_lang: str,
    current_user: OIDCUser = Depends(idp.get_current_user())
):
    """Trigger translation of company data to target language."""
    pass
```

### SSE Event

When translation completes, broadcast via existing SSE infrastructure:

```python
await task_event_manager.broadcast_to_user(
    user_id=user_id,
    event={
        "type": "translation_completed",
        "company_id": company_id,
        "language": target_lang,
    }
)
```

---

## UX Flow

1. User opens company card page
2. If `localStorage.companyCardLanguage` exists and translation available → Display in that language
3. Otherwise → Display original English
4. User clicks language dropdown
5. Dropdown shows all languages with availability status
6. **If available**: Switch language immediately
7. **If not available**:
   - Show "Translating..." toast
   - Call translation API
   - On SSE event `translation_completed`:
     - Show success toast
     - If user still on page, switch to translated language
     - Invalidate company query cache

### Error Handling

- If translation fails for some fields → Show partial translation, fallback to original for failed fields
- Show warning toast: "Some fields could not be translated"

---

## Open Questions

### To Discuss

1. **Insights fields**: Should they become `SourcedValue<string>` with source `"llm:claude"` or `"llm:mistral"`? Or remain plain strings?

2. **Non-translatable fields**: Fields like `socialMediaAccounts` (just URLs) - keep as plain objects or wrap in SourcedValue for consistency?

3. **Complex nested SourcedValues**: Like `digital.digitalStrategy` containing multiple text fields. How to structure translations?
   - Option A: Flatten keys (`digitalStrategy.overallStrategy`)
   - Option B: Nested structure matching original

4. **Migration strategy**: Existing data needs updating to new SourcedValue format:
   - One-time migration script?
   - Support both formats during transition?

5. **Systran API details**: Need documentation to finalize:
   - Rate limits
   - Batch vs single requests
   - Error handling specifics
   - Authentication method

---

## Technical Tasks

### Phase 1: Prerequisite - SourcedValue Refactoring

- [ ] Design unified SourcedValue type with translations field
- [ ] Update Company interface in frontend
- [ ] Update Company model in backend (if needed)
- [ ] Create migration script for existing data
- [ ] Update all components to use new `sv()` helper
- [ ] Remove old `getSourcedValue()` helper

### Phase 2: Translation Infrastructure

- [ ] Create `useCompanyTranslationStore` Pinia store
- [ ] Create `sv()`, `svSource()`, `svHasTranslation()` helpers
- [ ] Implement Systran API service in backend
- [ ] Create `/companies/{id}/translate` endpoint
- [ ] Add `translation_completed` SSE event type
- [ ] Update `useTaskEvents` to handle translation events

### Phase 3: UI Components

- [ ] Create `LanguageSelector` component
- [ ] Add language selector to company card page header
- [ ] Update company card components to use `sv()` helper
- [ ] Add translation loading states
- [ ] Add success/error toasts for translation

### Phase 4: Testing & Polish

- [ ] Test all language translations
- [ ] Test partial translation fallback
- [ ] Test SSE real-time updates
- [ ] Test localStorage persistence
- [ ] Performance testing with large company data

---

## Dependencies

- Systran API access (API key + endpoint)
- Existing SSE infrastructure (already implemented)
- Pinia store system (already in use)

## Out of Scope

- Automatic translation on company creation (manual trigger only)
- Translation of user-generated content
- Right-to-left language support (Arabic, Hebrew)
- Translation quality feedback/correction system
