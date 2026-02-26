# Specification: Company Card Translation

## Goal

Enable users to view company card data in their preferred language (French, German, Spanish, Italian, Portuguese, Dutch) with on-demand translation via Systran API, while preserving original English data with source attribution.

## User Stories

- As a user, I want to select a language from a dropdown on the company card page so that I can view company data in my preferred language
- As a user, I want my language preference to persist across sessions so that I don't have to re-select my language each time

## Specific Requirements

**Prerequisite: SourcedValue Type System Refactoring**
- Unify inconsistent data patterns (plain strings, `{value, sources[]}`, inline source) into single `SourcedValue<T>` type
- Add `translations?: Partial<Record<Language, T>>` field to SourcedValue for embedded translations
- Create new helper functions: `sv()`, `svSource()`, `svSources()`, `svHasTranslation()`
- Migrate existing data via Alembic migration to new format
- Update all company card components to use new `sv()` helper instead of `getSourcedValue()`
- This prerequisite MUST be completed before translation feature work begins

**Supported Languages Configuration**
- English (en) as default/original language - no translation needed
- Target languages: French (fr), German (de), Spanish (es), Italian (it), Portuguese (pt), Dutch (nl)
- Define `Language` type: `'en' | 'fr' | 'de' | 'es' | 'it' | 'pt' | 'nl'`
- Store language preference in localStorage key `companyCardLanguage`

**Translation Store (Pinia)**
- Create `useCompanyTranslationStore` with `selectedLanguage` ref (persisted in localStorage)
- Track translation state: `isTranslating`, `translatingCompanyId`
- Provide `setLanguage(lang)` action that updates localStorage and triggers translation if needed
- Store must be accessible from `sv()` helper to determine current language

**SourcedValue Helper Functions**
- `sv<T>(sourcedValue)`: Extract value respecting current language, fallback to original if translation unavailable
- `svSource<T>(sourcedValue)`: Get primary source (unchanged regardless of language)
- `svSources<T>(sourcedValue)`: Get all sources array
- `svHasTranslation<T>(sourcedValue)`: Check if translation exists for current language
- Place helpers in `front/src/utils/sourcedValue.ts`

**Language Selector Component**
- Dropdown showing all 7 languages with country flags
- Display availability status per language: "Available" (checkmark) or "Translate" (translate icon)
- Current selection highlighted with visual indicator
- Position in company card page header, aligned with existing controls
- Clicking unavailable language triggers translation with loading state

**Backend Translation Endpoint**
- `POST /companies/{company_id}/translate?target_lang={lang}` endpoint
- Requires `company.view` permission (same as viewing company)
- Call Systran API to translate all translatable fields in the 8 JSON columns
- Store translations in each SourcedValue's `translations` field
- Return updated company data after translation completes

**Systran API Integration Service**
- Create `SystranTranslationService` class in `back/app/services/translation.py`
- Implement `translate(text: str, target_lang: str) -> str` method
- Implement `translate_company(company_id: int, target_lang: str) -> Company` for bulk translation
- Handle API errors gracefully with partial translation fallback
- Configuration: API key and endpoint URL from environment variables

**SSE Event for Translation Completion**
- Add new event type `translation_completed` to existing SSE infrastructure
- Event payload: `{ type: "translation_completed", company_id: int, language: str }`
- Frontend `useTaskEvents` composable handles event to invalidate company query cache
- Display success toast: "Translation to {language} complete"

**Fields to Translate**
- Profile: businessLine, catchphrase, groupName (text fields only)
- Digital: insights, digitalStrategy (all 5 nested text fields), onlineServices descriptions, loyaltyProgram
- Timeline: insights, events descriptions/titles/impacts
- Products: insights, customerType, marketingPositioning, range items, partnerBrands, privateLabels
- Jobs: insights object (hiring_focus, growth_indicators), offers descriptions/requirements
- CSR: insights, responsibility, all initiative arrays
- Press: insights, all article/mention/release arrays
- Skip: URLs, dates, numbers, structural data, team names (firstName, lastName)

**Translation UX Flow**
- User opens company card - display in stored language preference (or English default)
- If translation cached - switch display immediately on language change
- If not cached - show "Translating..." toast, call API, wait for SSE event
- On SSE `translation_completed` - show success toast, invalidate cache, display translated content
- Partial translation failure - show warning toast, display available translations with fallback to original

## Existing Code to Leverage

**SourcedValue Type and Helpers**
- `front/src/types/company.ts` defines current `SourcedValue<T>` type (needs translation field added)
- `front/src/components/helpers/sourcedValues.ts` has `getSourcedValue()`, `getSourcedSource()` functions to replace with new `sv()` helpers
- Existing pattern provides foundation for unified approach

**Source Component**
- `front/src/components/company/Source.vue` displays source attribution with LLM detection
- Already handles `sourcedValue` prop and `isLLMSource()` logic
- Component can be reused unchanged - source display is language-independent

**SSE Task Events Infrastructure**
- `back/app/services/task_events.py` has `TaskEventManager` with `broadcast_to_user()` method
- Add new `broadcast_translation_completed()` method following existing `broadcast_all_tasks_completed()` pattern
- `front/src/composables/useTaskEvents.ts` handles SSE events with cache invalidation
- Add `translation_completed` case to `handleEvent()` switch statement

**Company Pinia Store and Queries**
- `front/src/stores/companies.ts` and `front/src/queries/companies.ts` for data fetching patterns
- Use `COMPANY_QUERY_KEYS` for cache invalidation after translation
- Follow existing query/mutation patterns with Pinia Colada

**Toast Notification System**
- `front/src/utils/toast.ts` provides `toast.success()`, `toast.warning()` methods
- Use for translation progress and completion feedback
- Follow existing pattern from task completion notifications

## Out of Scope

- Automatic translation on company creation (manual trigger only via language selector)
- Translation of user-generated content or comments
- Right-to-left language support (Arabic, Hebrew, etc.)
- Translation quality feedback or correction system
- Translation caching at text-level across companies (each company stores its own translations)
- Translation of team member names (firstName, lastName)
- Redis or external caching layer for translations
- Batch translation of multiple companies at once
- Translation progress percentage/indicator (just loading/complete states)
- Language detection for source content (assume English source)
