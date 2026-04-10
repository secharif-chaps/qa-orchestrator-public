# ADR-0014: Frontend Translation Management Strategy

## Status

**Status:** Accepted

**Date:** 2026-03-04

**Decision Makers:** ChapsMind Engineering Team

**Tags:** frontend, i18n, internationalization, vue, ci, architecture, monorepo

**Supersedes:** Extends [ADR-0008](./0008-translation-i18n.md) (vue-i18n choice)

---

## Context

### Current Situation

ChapsMind is a multi-module application (TARGET, SCREEN, STREAM, EXPLORE) being unified into a single Vue 3 frontend. Translation management currently presents several inconsistencies that need to be resolved before scaling up:

**Two formats coexist:**
- **Main layer** (`src/i18n/locales/en-US.ts`, `fr-FR.ts`): TypeScript files with nested objects (~2900 lines, i.e. ~1750 translation keys + ~1150 lines of JSON structure: braces, indentation, commas)
- **Target layer** (`src/target/i18n/locales/en-US.json`, `fr-FR.json`): JSON files with flat dot-notation keys (~580 keys, nearly 1:1 line/key in flat format)

**Total number of translation keys: ~2330** (1750 + 580), spread across ~3480 lines of files.

**Identified problems:**
1. **Dual format**: two conventions (nested TS + flat JSON) merged at runtime via spread operator
2. **Monolithic files**: ~2330 keys per language in a single bundle, loaded at startup
3. **No tooling**: no ESLint i18n, no i18n Ally, no CI validation
4. **No detection** of missing, orphaned, or duplicate keys
5. **Inconsistent naming conventions**: `feature.element` vs `module.page.component.element`
6. **Inline fallbacks** in some components: `$t('key', 'Fallback text')` (anti-pattern)
7. **Limited languages**: only `en-US` and `fr-FR`, with no lazy loading strategy

**Current languages:** `en-US` (default/fallback), `fr-FR`

**Likely future languages:** `de-DE`, `es-ES`, `it-IT`, `ar-SA`, `ja-JP`, `zh-CN` (international clients)

---

## Decision

### 1. Translation File Format

**Decision: Nested JSON, one file per language**

All translation files use the **nested JSON** format, in a single file per language with top-level namespaces to organize keys.

#### Evaluated Options

| Criterion | Nested JSON | Flat JSON | TypeScript | YAML | ICU MessageFormat |
|-----------|:---:|:---:|:---:|:---:|:---:|
| vue-i18n compatibility | Native | Native | Via import | Via plugin | Via plugin |
| Readability | Medium (many structure lines) | Good (1 line = 1 key) | Good | Excellent | Poor |
| Key lookup | Indirect (segments, i18n Ally) | Direct (`Ctrl+F` on full key) | Indirect | Indirect | Indirect |
| Tooling (i18n Ally, ESLint) | Excellent | Good | Limited | Medium | Limited |
| Loading performance | Native JSON.parse | Native | Requires compilation | Requires parsing | Requires compilation |
| Lines/keys ratio | ~1.6 lines per key (braces, indentation) | 1 line = 1 key | ~1.6 | ~1.3 | 1 |
| Git diffability | Medium (adding a key can shift braces) | Excellent (1 added line = 1 diff) | Good | Good | Medium |
| Industry standard | Yes | Yes | No | Partial | Yes (but complex) |

> **Note on nested JSON readability**: a file of 2330 keys in nested JSON amounts to ~3700 lines (ratio ~1.6x) because each nesting level adds opening/closing brace lines. In flat JSON, the same number of keys amounts to ~2330 lines. This is a trade-off: we gain visual hierarchical structure but lose density.

**Choice: Nested JSON** because:
- Native vue-i18n support without additional plugins
- Best tooling support (i18n Ally, eslint-plugin-vue-i18n) - i18n Ally compensates for the manual search deficit
- Native `JSON.parse()` = optimal loading performance
- De facto standard in the Vue ecosystem
- Visual hierarchy helps understand key organization, even though it costs more lines

#### File Organization: Single File vs Module Folders

Two approaches were evaluated:

**Option A: One file per language (CHOSEN)**

```
front/src/i18n/
├── index.ts                          # vue-i18n configuration
└── locales/
    ├── en-US.json                    # All English keys
    └── fr-FR.json                    # All French keys
```

**Option B: One folder per module**

```
front/src/i18n/
├── index.ts
└── locales/
    ├── common/
    │   ├── en-US.json
    │   └── fr-FR.json
    ├── target/
    │   ├── en-US.json
    │   └── fr-FR.json
    └── ... (6+ folders)
```

| Criterion | Single file (A) | Module folders (B) |
|-----------|:---:|:---:|
| **Simplicity** | 1 file to open, everything is there | Navigate between 6+ folders, find the right key |
| **Key lookup** | Ctrl+F in the file, done | Search which folder/file contains the key |
| **Adding a key** | Open 1 file, add in the right place | Determine the right module, open the right subfolder |
| **Git conflict risk** | More conflicts on a single file | Fewer conflicts (separate files) |
| **Lazy loading** | Possible per language (not per module) | Possible per module AND per language |
| **Migration** | Low effort (convert format only) | Significant effort (split ~2330 keys into modules) |
| **Cross-module keys** | No problem, everything is together | Endless debate: does this key go in `common/` or `target/`? |
| **File size** | ~2330 keys = ~3700 lines (manageable with i18n Ally) | ~300-800 lines per file |
| **i18n Ally DX** | Works natively | Requires `pathMatcher` + `namespace` config |

**Decision: Option A - Single file per language**

Simplicity wins. With ~2330 keys and 2 languages, a single file per language is perfectly manageable. Nested JSON already provides a clear hierarchical structure (the `common.*`, `target.*`, `dashboard.*` namespaces exist within the file tree). Splitting into folders adds complexity without real benefit at our scale.

**When to reconsider**: If the file exceeds ~10,000 keys or the number of simultaneous contributors on translations generates too many Git conflicts, consider splitting by module.

#### Chosen File Structure

```
front/src/i18n/
├── index.ts                          # vue-i18n configuration + lazy loading
└── locales/
    ├── en-US.json                    # ~2330 keys (~3700 lines), source of truth
    └── fr-FR.json                    # ~2330 keys (~3700 lines), French translation
```

Top-level namespaces in JSON serve as "logical modules":

```json
{
  "common": { ... },
  "target": { ... },
  "screen": { ... },
  "dashboard": { ... },
  "settings": { ... },
  "admin": { ... }
}
```

This provides the same logical organization as folders, but in a single easy-to-search file.

#### Example of the `common` Section in `en-US.json`

```json
{
  "common": {
    "actions": {
      "save": "Save",
      "cancel": "Cancel",
      "delete": "Delete",
      "edit": "Edit",
      "create": "Create",
      "confirm": "Confirm",
      "close": "Close",
      "search": "Search",
      "filter": "Filter",
      "retry": "Retry"
    },
    "status": {
      "loading": "Loading...",
      "error": "An error occurred",
      "empty": "No data available",
      "success": "Operation successful"
    },
    "validation": {
      "required": "{field} is required",
      "minLength": "{field} must be at least {min} characters",
      "maxLength": "{field} must be at most {max} characters",
      "email": "Please enter a valid email address"
    },
    "pagination": {
      "previous": "Previous",
      "next": "Next",
      "page": "Page {current} of {total}",
      "items": "No items | {count} item | {count} items"
    },
    "time": {
      "justNow": "Just now",
      "minutesAgo": "{count} min ago | {count} min ago",
      "hoursAgo": "{count} hour ago | {count} hours ago",
      "daysAgo": "{count} day ago | {count} days ago"
    }
  }
}
```

---

### 2. Key Naming Convention

**Decision: `module.feature.element` in camelCase**

#### Format

```
{module}.{feature}.{element}
```

- **module**: top-level namespace in JSON (see list below)
- **feature**: functionality or page (`companies`, `watchFiles`, `actors`, `search`)
- **element**: UI element or context (`title`, `description`, `placeholder`, `label`, `tooltip`, `error`, `success`)

#### Top-Level Namespaces

The initial namespaces are:

| Namespace | Scope |
|-----------|-------|
| `common` | Shared keys used across 2+ modules (actions, status, validation, pagination) |
| `target` | TARGET module specific keys |
| `screen` | SCREEN module specific keys |
| `dashboard` | Dashboard pages |
| `settings` | Settings/preferences pages |
| `admin` | Administration pages |

**This list is not fixed.** New namespaces can be added as the application evolves. To add a new top-level namespace:
1. Propose it in an MR with justification (new module, new major feature area)
2. Get team agreement during code review
3. Add the namespace to both `en-US.json` and `fr-FR.json`
4. Update this ADR's namespace table

**Rule**: Avoid proliferation — a new namespace is warranted only for a distinct functional area, not for individual features (which belong as sub-keys under an existing namespace).

#### Rules

| Rule | Correct example | Incorrect example |
|------|----------------|-------------------|
| camelCase for each segment | `target.companyCard.title` | `target.company_card.title` |
| No abbreviations | `common.actions.delete` | `common.act.del` |
| Singular for feature names | `target.company.name` | `target.companies.name` |
| Present tense for actions | `common.actions.save` | `common.actions.saved` |
| Max 4 levels of depth | `target.company.detail.title` | `target.company.detail.header.main.title` |
| No UI type references | `target.search.placeholder` | `target.search.inputPlaceholder` |

#### Shared Keys

Reusable keys across modules go in the `common` namespace:

```
common.actions.*          # Generic buttons and actions
common.status.*           # States (loading, error, empty)
common.validation.*       # Form validation messages
common.pagination.*       # Pagination
common.time.*             # Relative time
common.confirm.*          # Confirmation dialogs
```

**Rule**: If a key is used in 2+ modules, it must be in `common/`.

#### How to Find a Key in Translation Files (Nested JSON)

Nested JSON stores keys as a tree structure. When you see `$t('common.modal.error.title')` in a `.vue` file, the key does not exist as-is in the JSON file: it is split across multiple nesting levels.

**Concrete example:**

In a component:
```vue
<h2>{{ $t('common.modal.error.title') }}</h2>
```

In `en-US.json`, the key is stored as:
```json
{
  "common": {
    "modal": {
      "error": {
        "title": "An error occurred"
      }
    }
  }
}
```

You **cannot** simply `Ctrl+F` for `common.modal.error.title` in the JSON file: it won't match.

**Method 1: Search for the last key segment**

The simplest method: search for `"title"` in the JSON file. Since the file is structured by namespaces, you can visually locate the right section (`common` → `modal` → `error`).

In practice, search for the most specific segment. For `common.modal.error.title`, search for `"error"` (more unique than `"title"`) and navigate through the context.

**Method 2: IDE Plugin (recommended)**

**VS Code — i18n Ally:**

The **i18n Ally** extension fully solves this problem:
- **Hover over the key** in the `.vue` file → displays the translation inline, without opening the JSON file
- **Click on the key** → navigates directly to the location in the JSON file
- **Inline annotations** → displays the translated value next to each `$t()` directly in the editor
- **Translation tree** → sidebar panel with the full navigable tree

**JetBrains (WebStorm / IntelliJ IDEA):**

WebStorm provides built-in i18n support for Vue projects:
- **Navigate to key**: `Ctrl+Click` on a `$t('key')` string navigates to the JSON key definition (requires configuring the i18n framework in Settings → Languages & Frameworks → Vue)
- **Autocomplete**: WebStorm autocompletes translation keys inside `$t()` calls when JSON files are properly referenced
- **Find Usages**: right-click a key in the JSON file → Find Usages to see all components using it
- **Structural Search**: use `Edit → Find → Search Structurally` to search across nested JSON structures

For richer inline annotations similar to i18n Ally, install the **i18n Support** plugin from the JetBrains Marketplace (compatible with WebStorm 2024+).

This IDE integration is **the main reason** why the tooling section marks IDE plugins as high priority.

**Method 3: Grep with key segments**

From the command line, search for a unique intermediate segment:

```bash
# Search for "error" in the JSON file context
grep -n '"error"' src/i18n/locales/en-US.json

# Or with more context to see the tree structure
grep -n -A2 '"error"' src/i18n/locales/en-US.json
```

**Method 4: `i18n:find` utility script**

A utility script can be added to resolve a dot-notation key to its position in the file:

```bash
# Usage: yarn i18n:find common.modal.error.title
# Output: src/i18n/locales/en-US.json:142 → "An error occurred"
```

This script is included in the CI tasks to implement (`TASK-CI-08`).

**Method summary:**

| Method | Effort | Precision | Recommendation |
|--------|--------|-----------|----------------|
| i18n Ally (hover/click) | None | Exact | **Use daily** |
| Search for last segment | Low | Good (if segment is unique) | When i18n Ally is unavailable |
| Grep on intermediate segment | Low | Medium | In terminal / CI |
| `i18n:find` script | None (after install) | Exact | For scripts and CI |

> **Note**: This is an inherent trade-off of nested JSON. Flat JSON (`"common.modal.error.title": "..."`) allows direct `Ctrl+F` but loses hierarchical readability and tooling support. The choice of nested JSON is justified because **with i18n Ally, lookup is instant and frictionless** — it's better than `Ctrl+F` since you don't even need to open the file.

---

### 3. Fallback Strategy

**Decision: Local fallback chain with `en-US` as root language**

```
Configuration:
  fallbackLocale: 'en-US'

Resolution chain:
  fr-CA → fr-FR → en-US     (explicit: Canadian French falls back to French first)
  {any other locale} → en-US (via default)
```

#### Implementation

```typescript
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: savedLocale || 'en-US',
  fallbackLocale: {
    'fr-CA': ['fr-FR', 'en-US'],
    default: ['en-US'],
  },
  missingWarn: import.meta.env.DEV,
  fallbackWarn: import.meta.env.DEV,
})
```

**Rule**: `en-US` is the source of truth. Every new key is first added in `en-US`, then translated into other languages. A missing key in a language displays the `en-US` fallback (not an empty string).

---

### 4. Plurals, Interpolations, and ICU MessageFormat

**Decision: ICU MessageFormat via `@messageformat/core` as custom message compiler**

vue-i18n does not natively support ICU `select` (gender) or advanced `plural` syntax. To handle gender, plurals, and their combinations in a scalable way, we use [`@messageformat/core`](https://messageformat.github.io/) as a custom message compiler. This is the official extension point provided by vue-i18n v9+.

#### Setup

```bash
yarn add @messageformat/core
```

```typescript
// src/i18n/index.ts
import MessageFormat from '@messageformat/core'
import { createI18n } from 'vue-i18n'
import type { MessageCompiler, MessageCompilerContext, MessageContext } from 'vue-i18n'

const messageCompiler: MessageCompiler = (message, { locale }: MessageCompilerContext) => {
  if (typeof message === 'string') {
    const mf = new MessageFormat(locale)
    const compiled = mf.compile(message)
    return (ctx: MessageContext) => compiled(ctx.values ?? {})
  }
  return () => String(message)
}

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'en-US',
  messageCompiler, // replaces default compiler with ICU MessageFormat
  messages: { ... },
})
```

> **Important**: With `messageCompiler`, the native vue-i18n pipe syntax (`zero | one | many`) is **no longer available**. All plurals must use ICU `{count, plural, ...}` syntax. This is a project-wide choice.

---

#### Plurals

ICU `plural` syntax with CLDR categories (`=0`, `one`, `other`):

```json
{
  "common": {
    "pagination": {
      "items": "{count, plural, =0 {No items} one {# item} other {# items}}"
    }
  }
}
```

```vue
<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <p>{{ t('common.pagination.items', { count: 0 }) }}</p>
  <!-- → "No items" -->

  <p>{{ t('common.pagination.items', { count: 1 }) }}</p>
  <!-- → "1 item" -->

  <p>{{ t('common.pagination.items', { count: 42 }) }}</p>
  <!-- → "42 items" -->
</template>
```

> `#` is replaced by the value of `count`. CLDR categories vary per locale (e.g., French treats 0 as `one`, not `other`).

---

#### Interpolations

Always use named parameters:

```json
{
  "target": {
    "company": {
      "created": "Company {name} created successfully",
      "deleteConfirm": "Are you sure you want to delete {name}?"
    }
  }
}
```

```typescript
// CORRECT
t('target.company.created', { name: 'Acme Corp' })

// FORBIDDEN: fallback as second parameter
// t('target.company.created', 'Company created', { name: 'Acme Corp' })
```

---

#### HTML in Translations

**Never use `v-html` with `$t()`** (XSS risk). Use the `<i18n-t>` component to inject HTML or components inside translations:

```json
{
  "target": {
    "company": {
      "createdBy": "Created by {author} on {date}",
      "termsNotice": "By continuing, you accept the {terms} and {privacy}"
    }
  }
}
```

```vue
<template>
  <!-- Inject styled elements inside a translation -->
  <i18n-t keypath="target.company.createdBy" tag="p">
    <template #author>
      <strong>{{ company.authorName }}</strong>
    </template>
    <template #date>
      <time :datetime="company.createdAt">{{ formattedDate }}</time>
    </template>
  </i18n-t>

  <!-- Inject links inside a translation -->
  <i18n-t keypath="target.company.termsNotice" tag="p">
    <template #terms>
      <a href="/terms" class="text-primary underline">{{ t('common.legal.terms') }}</a>
    </template>
    <template #privacy>
      <a href="/privacy" class="text-primary underline">{{ t('common.legal.privacy') }}</a>
    </template>
  </i18n-t>
</template>
```

---

#### Conditional Branching with ICU `select`

ICU `select` is a **generic conditional branching mechanism** on any string value — not limited to gender. It allows adapting wording based on any discriminating field (gender, entity type, status, role, category, etc.) in a **single translation key**, avoiding suffixed keys or component-side `switch` statements.

The `other` branch is **mandatory** and serves as fallback for unexpected values.

##### Gender (Masculine / Feminine)

```json
{
  "target": {
    "company": {
      "assignedTo": "{gender, select, male {assigné à} female {assignée à} other {assigné(e) à}} {name}"
    }
  }
}
```

```vue
<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Assignment {
  name: string
  gender: 'male' | 'female' | 'other'
}

const assignment: Assignment = { name: 'Marie', gender: 'female' }
</script>

<template>
  <p>{{ t('target.company.assignedTo', { gender: assignment.gender, name: assignment.name }) }}</p>
  <!-- gender='female' → "assignée à Marie" -->
  <!-- gender='male'   → "assigné à Marie" -->
  <!-- gender='other'  → "assigné(e) à Marie" -->
</template>
```

##### Entity Types (backend return values)

Adapt wording based on the type returned by the backend API:

```json
{
  "target": {
    "entity": {
      "label": "{type, select, company {Entreprise} person {Personne} organization {Organisation} other {Entité}}",
      "created": "{type, select, company {L'entreprise {name} a été créée} person {La personne {name} a été créée} other {L'élément {name} a été créé}}"
    }
  }
}
```

```typescript
t('target.entity.label', { type: entity.type })                        // "Entreprise"
t('target.entity.created', { type: 'person', name: 'Marie' })          // "La personne Marie a été créée"
t('target.entity.created', { type: 'unknown_value' })                  // "L'élément  a été créé" (other branch)
```

##### Statuses

```json
{
  "target": {
    "status": {
      "label": "{status, select, active {Actif} inactive {Inactif} pending {En attente} archived {Archivé} other {Inconnu}}"
    }
  }
}
```

```typescript
t('target.status.label', { status: item.status })  // "En attente"
```

##### Legacy suffix pattern (deprecated)

The `_male`/`_female`/`_other` suffix pattern below still works but **should not be used for new keys** — prefer ICU `select` syntax as the project standard:

```json
{
  "target": {
    "actor": {
      "role_male": "{name} est inscrit comme {role}",
      "role_female": "{name} est inscrite comme {role}",
      "role_other": "{name} est inscrit(e) comme {role}"
    }
  }
}
```

```typescript
// DEPRECATED: suffix pattern — migrate to ICU select
const genderSuffix = actor.gender === 'female' ? '_female' : actor.gender === 'male' ? '_male' : '_other'
t(`target.actor.role${genderSuffix}`, { name: actor.name, role: actor.role })
```

---

#### Combined Plural + Select

ICU `select` and `plural` compose naturally by nesting. A single key handles all combinations.

##### Type + Plural (entity type from backend)

```json
{
  "target": {
    "search": {
      "resultCount": "{type, select, company {{count, plural, =0 {Aucune entreprise trouvée} one {# entreprise trouvée} other {# entreprises trouvées}}} person {{count, plural, =0 {Aucune personne trouvée} one {# personne trouvée} other {# personnes trouvées}}} other {{count, plural, =0 {Aucun résultat} one {# résultat} other {# résultats}}}}"
    }
  }
}
```

```typescript
t('target.search.resultCount', { type: 'company', count: 5 })  // "5 entreprises trouvées"
t('target.search.resultCount', { type: 'person', count: 1 })   // "1 personne trouvée"
t('target.search.resultCount', { type: 'other', count: 0 })    // "Aucun résultat"
```

##### Gender + Plural

```json
{
  "target": {
    "actor": {
      "selected": "{gender, select, male {{count, plural, =0 {Aucun acteur sélectionné} one {# acteur sélectionné} other {# acteurs sélectionnés}}} female {{count, plural, =0 {Aucune actrice sélectionnée} one {# actrice sélectionnée} other {# actrices sélectionnées}}} other {{count, plural, =0 {Aucun(e) sélectionné(e)} one {# sélectionné(e)} other {# sélectionné(e)s}}}}"
    }
  }
}
```

```vue
<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <p>{{ t('target.actor.selected', { gender: 'male', count: 0 }) }}</p>
  <!-- → "Aucun acteur sélectionné" -->

  <p>{{ t('target.actor.selected', { gender: 'female', count: 1 }) }}</p>
  <!-- → "1 actrice sélectionnée" -->

  <p>{{ t('target.actor.selected', { gender: 'male', count: 5 }) }}</p>
  <!-- → "5 acteurs sélectionnés" -->

  <p>{{ t('target.actor.selected', { gender: 'other', count: 3 }) }}</p>
  <!-- → "3 sélectionné(e)s" -->
</template>
```

> The nested syntax uses double braces `{{...}}` to escape the inner ICU expression within the outer `select` branches. This is standard ICU MessageFormat, supported by all major translation platforms (Crowdin, Lokalise, Phrase).

#### Dates and Numbers

Use vue-i18n named formats:

```typescript
const { d, n } = useI18n()

d(new Date(), 'short')      // "03/04/2026" (en-US) / "04/03/2026" (fr-FR)
d(new Date(), 'long')       // "March 4, 2026" / "4 mars 2026"
n(1234567.89, 'currency')   // "$1,234,567.89" / "1 234 567,89 $"
```

---

### 5. Lazy Loading by Language and Module

**Decision: Asynchronous loading for non-default locales**

Only `en-US` (default language) is included in the initial bundle. Other languages are loaded on demand.

#### Implementation

```typescript
// src/i18n/index.ts
import { createI18n } from 'vue-i18n'
import enUS from './locales/en-US.json'

// Only en-US in the initial bundle (static import)
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'en-US',
  fallbackLocale: 'en-US',
  messages: {
    'en-US': enUS,
  },
})

// Asynchronous loading for other languages (1 file per language)
export const loadLocale = async (locale: string): Promise<void> => {
  if (i18n.global.availableLocales.includes(locale)) return

  const messages = await import(`./locales/${locale}.json`)
  i18n.global.setLocaleMessage(locale, messages.default)
  i18n.global.locale.value = locale
}

export default i18n
```

Vite's dynamic import automatically generates one chunk per language file. The browser only downloads the selected language.

#### Estimated Bundle Impact

| Configuration | Estimated size (gzip) |
|--------------|----------------------|
| Current (everything bundled) | ~55 KB (2 languages, ~2330 keys) |
| With lazy loading (en-US only) | ~28 KB initial + ~28 KB per loaded language |
| With 8 languages (no lazy loading) | ~220 KB |
| With 8 languages (lazy loading) | ~28 KB initial + ~28 KB on demand |

Lazy loading halves the initial bundle and scales linearly with added languages.

---

### 6. Workflow for Adding New Translations

#### For a Developer

1. **Identify the namespace**: `common.*`, `target.*`, `dashboard.*`, etc.
2. **Open `en-US.json`** and add the key in the right place in the tree
3. **Open `fr-FR.json`** and add the translation at the same location
4. **Use the key** in the component via `$t()` or `t()`
5. **Verify**: run the CI script locally (`yarn i18n:check`)

With **i18n Ally**, steps 2-3 are simplified: right-click on the key in the `.vue` file → "Create Key" → fill in translations in the inline panel.

#### Finding Existing Keys Before Creating New Ones

**Before creating a new key**, always check if an existing translation already covers (or closely matches) the text you need. This avoids duplication and promotes reuse.

**Method 1: Exact search by keyword**

Search the `en-US.json` or `fr-FR.json` file for a keyword from the text you want to translate:

```bash
# Search for an existing translation by its value
grep -i "save" apps/front/src/i18n/locales/en-US.json
# → "save": "Save",  (under common.actions)

# Partial word search
grep -i "compan" apps/front/src/i18n/locales/en-US.json
# → finds "company", "companies", etc.
```

**Method 2: i18n Ally / IDE search**

- **VS Code (i18n Ally)**: Use the sidebar tree view to browse existing keys by namespace. The search bar in the i18n Ally panel searches both keys and values.
- **WebStorm**: Use `Ctrl+Shift+F` (Find in Files) scoped to `src/i18n/locales/` to search by translated text.

**Method 3: `i18n:search` — Fuzzy search by approximate text (recommended)**

When looking for translations with similar but not identical wording, use the `i18n:search` script. It compares your input against all translation values using string similarity (Levenshtein distance) and returns the closest matches:

```bash
yarn i18n:search "Do you really want to delete"
# Output:
#   92% │ common.confirm.deleteMessage   → "Are you sure you want to delete {name}?"
#   78% │ target.company.deleteConfirm   → "Are you sure you want to delete this company?"
#   45% │ common.actions.delete          → "Delete"

yarn i18n:search "Company created"
# Output:
#   88% │ target.company.created         → "Company {name} created successfully"
#   62% │ target.company.createTitle     → "Create a new company"

# Adjust similarity threshold (default: 40%)
yarn i18n:search --threshold 70 "Are you sure"

# Search in a specific language
yarn i18n:search --locale fr-FR "Êtes-vous sûr de vouloir supprimer"
```

**Implementation**: uses `fastest-levenshtein` (lightweight, zero-config) to compute normalized similarity between the input and every leaf value in the JSON tree. See `TASK-CI-11` for implementation details.

**Method 4: `i18n:find` — Search by key path**

```bash
# Resolve a dot-notation key to its position and value
yarn i18n:find common.confirm.deleteMessage
# Output: src/i18n/locales/en-US.json:142 → "Are you sure you want to delete {name}?"
```

This script (see `TASK-CI-08`) resolves key paths to their file location.

**Reuse rules**:
- If an exact match exists in `common.*`, use it directly
- If a close match exists (>80% similarity), evaluate whether the existing key can be generalized or if a new specific key is needed
- If a key is used in only one module but you need it in another, move it to `common.*`

#### Decision Diagram

```
New translation key needed?
  │
  ├─ 1. Search existing keys: yarn i18n:search "your text"
  │   ├─ Exact match (100%)? → Reuse the existing key
  │   ├─ Close match (>80%)? → Evaluate if existing key can be generalized
  │   └─ No match (<40%)? ↓
  │
  ├─ 2. Generic key (button, status, validation)?
  │   └─ → Add under common.* in en-US.json / fr-FR.json
  │
  └─ 3. Module-specific key?
      └─ → Add under {module}.* in en-US.json / fr-FR.json
```

#### Contribution Rules

- **Who adds keys**: the developer who creates/modifies the component
- **Validation**: review in MR + automated CI
- **Translation**: the developer adds en-US + fr-FR. For other languages, a translation task is created

---

### 7. CI Automation

#### CI Jobs to Implement

| Job | Objective | Recommended tool | Trigger | Estimated execution time |
|-----|----------|-----------------|---------|-------------------------|
| `i18n:missing` | Detect keys present in one language but absent in another | Custom script or `vue-i18n-extract` | Each MR | ~2-3s (comparison of 2 JSON trees) |
| `i18n:orphans` | Detect keys defined but never used in the code | `vue-i18n-extract` | Weekly | ~10-15s (scan of all `.vue` and `.ts` files + comparison with keys) |
| `i18n:duplicates` | Detect duplicate keys between namespaces | Custom script | Each MR | ~1-2s (traversal of a single JSON file) |
| `i18n:format` | Validate JSON format (syntax, alphabetical sorting) | `jsonlint` + `sort-json` | Each MR | ~1s (validation of 2 files) |
| `i18n:coverage` | Coverage report per language (% of translated keys) | Custom script | Each MR | ~1-2s (key count per file) |

**Total estimated CI time**: ~5-8s in parallel execution on each MR (excluding `i18n:orphans` which runs weekly). The `i18n:orphans` job takes ~10-15s because it must scan the entire source code to verify that each key is used. These times are negligible compared to the rest of the pipeline (build, tests, lint).

#### Local Verification Script

```json
{
  "scripts": {
    "i18n:check": "node scripts/i18n-check.js",
    "i18n:sort": "node scripts/i18n-sort.js",
    "i18n:coverage": "node scripts/i18n-coverage.js",
    "i18n:search": "node scripts/i18n-search.js",
    "i18n:find": "node scripts/i18n-find.js"
  }
}
```

#### Taskfile Integration

An aggregated task `front:i18n` runs all i18n checks (except `coverage` which is informational):

```yaml
# Taskfile.yml
front:i18n:
  desc: Run all i18n validation checks
  dir: apps/front
  cmds:
    - yarn i18n:check       # Missing keys
    - yarn i18n:duplicates  # Duplicate keys
    - yarn i18n:format      # JSON syntax + alphabetical sorting

front:i18n:sort:
  desc: Sort i18n JSON keys alphabetically
  dir: apps/front
  cmds:
    - yarn i18n:sort

front:i18n:coverage:
  desc: Report i18n translation coverage per language
  dir: apps/front
  cmds:
    - yarn i18n:coverage

front:i18n:orphans:
  desc: Detect unused i18n keys in source code
  dir: apps/front
  cmds:
    - yarn i18n:orphans
```

Developers can run `task front:i18n` before pushing to catch issues early.

#### Pre-commit Hook (via lint-staged)

Translation files are automatically sorted alphabetically on commit using `lint-staged`. This avoids noisy diffs and merge conflicts.

```javascript
// lint-staged.config.mjs
export default {
  'apps/front/src/i18n/locales/*.json': [
    'yarn --cwd apps/front i18n:sort',
  ],
}
```

This ensures translation files are always committed in a consistent, sorted order regardless of where developers inserted new keys. lint-staged only processes files that are actually staged, which is more efficient and reliable than a manual `git diff --cached` check.

#### GitLab CI Pipeline Example

```yaml
i18n-validation:
  stage: lint
  script:
    - cd front
    - yarn i18n:check
  rules:
    - changes:
        - front/src/i18n/**/*
```

#### Thresholds

- **Blocking** (MR rejected): missing key in `en-US` or `fr-FR`
- **Warning** (non-blocking): orphaned key detected
- **Informational**: secondary language coverage < 100%

#### Implementation Tasks

Each CI job should have a **dedicated task** in the backlog to ensure clear tracking:

| Task | Description | Dependency |
|------|-------------|------------|
| `TASK-CI-01` | Create the `i18n:check` script (detect missing keys between `en-US` and `fr-FR`) | None |
| `TASK-CI-02` | Create the `i18n:orphans` script (detect unused keys in source code) | None |
| `TASK-CI-03` | Create the `i18n:duplicates` script (detect duplicate keys between namespaces) | None |
| `TASK-CI-04` | Create the `i18n:format` script (validate JSON syntax + alphabetical sorting) | None |
| `TASK-CI-05` | Create the `i18n:coverage` script (report % of translated keys per language) | None |
| `TASK-CI-06` | Integrate scripts into the GitLab CI pipeline (lint stage) | CI-01 to CI-05 |
| `TASK-CI-07` | Document script usage in the dev README | CI-06 |
| `TASK-CI-08` | Create the `i18n:find` script (resolve a dot-notation key to its position in JSON) | None |
| `TASK-CI-09` | Add `front:i18n` aggregated tasks in `Taskfile.yml` | CI-01 to CI-05 |
| `TASK-CI-10` | Add lint-staged config for automatic i18n key sorting on commit | CI-04 |
| `TASK-CI-11` | Create the `i18n:search` script (fuzzy search by approximate text using `fastest-levenshtein`) | None |

Each task is independent (except CI-06, CI-07, CI-09, and CI-10) and can be developed in parallel.

---

### 8. Recommended Tooling

| Tool | Usage | Priority |
|------|-------|----------|
| **i18n Ally** (VS Code) / **i18n Support** (JetBrains) | Autocomplete, inline preview, missing key detection | High |
| **eslint-plugin-vue-i18n** | Lint keys in templates, detect `$t` with fallback | High |
| **vue-i18n-extract** | CLI key extraction and comparison | Medium |
| **sort-json** | Alphabetical sorting of JSON files | Medium |

#### i18n Ally Configuration

```json
// .vscode/settings.json
{
  "i18n-ally.localesPaths": ["src/i18n/locales"],
  "i18n-ally.keystyle": "nested",
  "i18n-ally.sourceLanguage": "en-US",
  "i18n-ally.displayLanguage": "en-US",
  "i18n-ally.enabledFrameworks": ["vue"],
  "i18n-ally.pathMatcher": "{locale}.json"
}
```

#### ESLint Configuration

```javascript
// In eslint.config.ts, add:
import vueI18n from '@intlify/eslint-plugin-vue-i18n'

// Recommended rules:
// '@intlify/vue-i18n/no-missing-keys': 'error'
// '@intlify/vue-i18n/no-raw-text': 'warn'
// '@intlify/vue-i18n/no-unused-keys': 'warn'
// '@intlify/vue-i18n/no-duplicate-keys-in-locale': 'error'
```

---

### 9. Target + Screen Merge Architecture

#### Current State

```
front/src/
├── i18n/locales/          # Main layer (nested TS)
│   ├── en-US.ts           # ~2900 lines (~1750 keys)
│   └── fr-FR.ts           # ~2965 lines (~1750 keys)
└── target/i18n/locales/   # Target layer (flat JSON)
    ├── en-US.json          # ~580 lines (~578 keys)
    └── fr-FR.json          # ~587 lines (~578 keys)
```

#### Migration Plan

**Phase 1: Format Normalization** (prerequisite)
1. Convert `.ts` files to nested `.json` → `en-US.json`, `fr-FR.json`
2. Convert flat Target keys (dot-notation) to nested JSON
3. Merge both layers into a single file per language
4. Remove the spread operator merge and old `.ts` files

**Phase 2: Namespace Cleanup**
1. Rename/reorganize top-level keys to follow the convention (`common`, `target`, `screen`, `dashboard`, `settings`, `admin`)
2. Move shared keys under `common.*`
3. Remove identified orphaned keys

**Phase 3: Screen Integration**
1. Import existing Screen translations into the single file
2. Place them under the `screen.*` namespace
3. Identify common Screen/Target keys and move them under `common.*`
4. Resolve key conflicts (same key, different translation)

**Phase 4: Lazy Loading**
1. Implement asynchronous per-language loading (dynamic import)
2. Remove synchronous loading of `fr-FR`

#### Conflict Resolution Strategy

When merging Target + Screen, if the same key exists with different translations:
1. **Same key, same translation**: keep under `common.*`
2. **Same key, different translation**: rename with module prefix (`target.company.name` vs `screen.company.name`)
3. **Key unique to a module**: keep in the corresponding module

#### Source of Truth

**Decision: Files in the Git repository**

The JSON files in the repo are the source of truth. No external platform (Crowdin, Lokalise) for now.

**Justification**:
- Simple workflow (standard PRs)
- Currently 2 languages = manageable manually
- No external translators for now
- Code review integrated into the process

**Future evolution**: When the number of languages exceeds 4 or an external translation team is involved, evaluate adopting Lokalise or Crowdin with bidirectional Git synchronization.

---

## Consequences

### Positive

- **Consistency**: a single format (nested JSON) and a unified naming convention
- **Maintainability**: one file per language, easy to navigate with i18n Ally
- **Quality**: automated CI detecting issues before merge
- **Scalability**: lazy loading allowing languages to be added without impacting the initial bundle
- **DX**: i18n Ally + ESLint = autocomplete, inline detection, lint errors
- **Onboarding**: a new developer understands the system by reading this ADR

### Negative

- **Migration effort**: converting existing files (~2330 keys) requires initial work
- **Large file**: a single file ~3700 lines per language, of which ~40% is JSON structure (braces, indentation) — navigable with i18n Ally
- **Manual lookup**: without i18n Ally, finding a nested key requires searching by segments (see dedicated section)
- **Tooling to install**: i18n Ally, eslint-plugin-vue-i18n, CI scripts

### Neutral

- Existing inline fallbacks (`$t('key', 'Fallback')`) will need to be migrated progressively
- Colons (`:`) in Target keys will need to be replaced with camelCase-compatible separators
- The contribution workflow remains simple (adding keys to JSON files via PR)

---

## Implementation Plan

| Phase | Description | Priority |
|-------|-------------|----------|
| 1 | Install i18n Ally + eslint-plugin-vue-i18n | P0 |
| 2 | Convert `.ts` → nested `.json` + merge Target layer into single file per language | P0 |
| 3 | Clean up top-level namespaces | P0 |
| 4 | Remove inline fallbacks in components | P1 |
| 5 | Implement CI scripts (see tasks TASK-CI-01 to CI-08) | P1 |
| 6 | Implement per-language lazy loading | P1 |
| 7 | Integrate Screen translations | P2 (post-merge) |

---

## References

- [ADR-0008: vue-i18n for Internationalization](./0008-translation-i18n.md) - Initial library choice decision
- [vue-i18n Documentation](https://vue-i18n.intlify.dev/)
- [i18n Ally VS Code Extension](https://marketplace.visualstudio.com/items?itemName=Lokalise.i18n-ally)
- [eslint-plugin-vue-i18n](https://eslint-plugin-vue-i18n.intlify.dev/)
- [vue-i18n-extract](https://github.com/pixari/vue-i18n-extract)
- [MADR Template](https://adr.github.io/madr/)
- [ADR-0001: Vue.js 3 with Composition API](./0001-vue3-composition-api.md)
