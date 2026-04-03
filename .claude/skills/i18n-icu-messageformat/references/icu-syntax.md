# ICU MessageFormat — Advanced Syntax Reference

## Architecture

### Custom Message Compiler

vue-i18n's default pipe syntax (`zero | one | many`) is **replaced** by a custom `messageCompiler` using `@messageformat/core`. This is configured in `src/i18n/index.ts`:

```typescript
import MessageFormat from '@messageformat/core'

const messageCompiler = (message: string, { locale }: { locale: string }) => {
  const mf = new MessageFormat(locale)
  const compiled = mf.compile(message)
  return (ctx: Record<string, unknown>) => compiled(ctx)
}

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  messageCompiler, // replaces default compiler
  // ...
})
```

### Lazy Loading

Only `en-US` is statically imported. Other locales are loaded on demand via `loadLocaleMessages()`:

```typescript
export const loadLocaleMessages = async (locale: string): Promise<void> => {
  if (loadedLanguages.has(locale)) return

  // Deduplicate concurrent requests for the same locale
  const pending = pendingLoads.get(locale)
  if (pending) return pending

  const promise = import(`./locales/${locale}.json`)
    .then((messages) => {
      i18n.global.setLocaleMessage(locale, messages.default)
      loadedLanguages.add(locale)
    })
    .finally(() => {
      pendingLoads.delete(locale)
    })

  pendingLoads.set(locale, promise)
  return promise
}
```

Vite generates one chunk per locale file automatically.

### Fallback Chain

```
{any locale} → en-US (default fallback)
fr-CA → fr-FR → en-US (explicit chain, if configured)
```

`en-US` is the source of truth. Every new key is added in `en-US` first, then translated.

---

## ICU Plural — CLDR Categories

ICU `plural` uses CLDR categories that **vary by locale**. The categories are: `zero`, `one`, `two`, `few`, `many`, `other`. Only `other` is mandatory.

### English CLDR Rules

| Category | Condition | Example values |
|----------|-----------|----------------|
| `one` | n = 1 | 1 |
| `other` | everything else | 0, 2, 3, 42, 100 |

### French CLDR Rules

| Category | Condition | Example values |
|----------|-----------|----------------|
| `one` | n = 0 or n = 1 | **0**, 1 |
| `other` | n >= 2 | 2, 3, 42, 100 |

**Critical**: In French, `count=0` matches `one`, not `other`. Always use `=0` for distinct zero messages:

```json
// en-US
"{count, plural, =0 {No documents} one {# document} other {# documents}}"

// fr-FR — without =0, count=0 would display "0 document" (matches `one`)
"{count, plural, =0 {Aucun document} one {# document} other {# documents}}"
```

### Arabic CLDR Rules (6 categories)

| Category | Condition |
|----------|-----------|
| `zero` | n = 0 |
| `one` | n = 1 |
| `two` | n = 2 |
| `few` | 3-10 |
| `many` | 11-99 |
| `other` | 100+ |

If adding Arabic (`ar-SA`), all plural keys need `few` and `many` branches.

---

## ICU Select — Conditional Branching

`select` is a generic conditional on any string value. The `other` branch is **mandatory**.

### Gender Agreement

```json
{
  "target": {
    "company": {
      "assignedTo": "{gender, select, male {assigné à} female {assignée à} other {assigné(e) à}} {name}"
    }
  }
}
```

```typescript
t('target.company.assignedTo', { gender: 'female', name: 'Marie' })
// → "assignée à Marie"
```

### Entity Type (backend value)

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
t('target.entity.created', { type: 'person', name: 'Marie' })
// → "La personne Marie a été créée"
```

### Status Mapping

```json
{
  "target": {
    "status": {
      "label": "{status, select, active {Actif} inactive {Inactif} pending {En attente} archived {Archivé} other {Inconnu}}"
    }
  }
}
```

---

## Nested Select + Plural

ICU `select` and `plural` compose by nesting. Double braces `{{...}}` escape the inner ICU expression within outer `select` branches.

### Type + Plural

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
t('target.search.resultCount', { type: 'company', count: 5 })
// → "5 entreprises trouvées"

t('target.search.resultCount', { type: 'person', count: 1 })
// → "1 personne trouvée"
```

### Gender + Plural

```json
{
  "target": {
    "actor": {
      "selected": "{gender, select, male {{count, plural, =0 {Aucun acteur sélectionné} one {# acteur sélectionné} other {# acteurs sélectionnés}}} female {{count, plural, =0 {Aucune actrice sélectionnée} one {# actrice sélectionnée} other {# actrices sélectionnées}}} other {{count, plural, =0 {Aucun(e) sélectionné(e)} one {# sélectionné(e)} other {# sélectionné(e)s}}}}"
    }
  }
}
```

```typescript
t('target.actor.selected', { gender: 'female', count: 1 })
// → "1 actrice sélectionnée"

t('target.actor.selected', { gender: 'male', count: 5 })
// → "5 acteurs sélectionnés"
```

---

## HTML in Translations

**Never use `v-html` with `$t()`** (XSS risk). Use the `<i18n-t>` component:

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
  <i18n-t keypath="target.company.createdBy" tag="p">
    <template #author>
      <strong>{{ company.authorName }}</strong>
    </template>
    <template #date>
      <time :datetime="company.createdAt">{{ formattedDate }}</time>
    </template>
  </i18n-t>

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

## Dates and Numbers

Use vue-i18n named datetime/number formats (configured in `src/i18n/datetime-formats.ts`):

```typescript
const { d, n } = useI18n()

d(new Date(), 'short')       // "03/04/2026" (en-US) / "04/03/2026" (fr-FR)
d(new Date(), 'long')        // "03/04/2026, 02:30 PM" / "04/03/2026, 14:30"
d(new Date(), 'eventDate')   // "March 4, 2026" / "4 mars 2026"
n(1234567.89, 'currency')    // "$1,234,567.89" / "1 234 567,89 $"
```

Available date formats: `short`, `long`, `time`, `eventDate`, `eventDateTime`.

---

## Variable Naming in Plurals

The ICU plural selector variable must match the parameter name passed by the component. It does **not** have to be `count`:

```json
{
  "documents": {
    "nb_results": "{nb, plural, =0 {Aucun document} one {# document} other {# documents}}"
  }
}
```

```typescript
// Component passes { nb: totalItems }
t('documents.nb_results', { nb: totalItems })
```

The `#` shorthand always resolves to the **selector variable** value (here `nb`, not `count`).

---

## Common Mistakes

### 1. Using pipe syntax (disabled)

```json
// WRONG — pipe syntax is disabled by messageCompiler
"items": "No items | {count} item | {count} items"

// CORRECT — ICU plural
"items": "{count, plural, =0 {No items} one {# item} other {# items}}"
```

### 2. Fallback string as 2nd argument

```typescript
// WRONG — 2nd arg is treated as params object, not fallback
t('key', 'Fallback text')
t('key', 'Fallback', { param: value })

// CORRECT
t('key')
t('key', { param: value })
```

### 3. Missing `other` branch

```json
// WRONG — no other branch
"{count, plural, one {# item}}"

// CORRECT
"{count, plural, one {# item} other {# items}}"
```

### 4. Missing `=0` for French zero messages

```json
// WRONG for fr-FR — count=0 matches `one`, displays "0 document"
"{count, plural, one {# document} other {# documents}}"

// CORRECT — explicit =0 overrides CLDR
"{count, plural, =0 {Aucun document} one {# document} other {# documents}}"
```

### 5. Positional 3rd argument

```typescript
// WRONG — no positional arguments with messageCompiler
t('key', { count: 5 }, 5)

// CORRECT
t('key', { count: 5 })
```

---

## Adding a New Language

1. Create `src/i18n/locales/{locale}.json` (copy structure from `en-US.json`)
2. Add datetime formats in `src/i18n/datetime-formats.ts`
3. Add the locale option in `LocaleSwitcher.vue`
4. No other changes needed — `loadLocaleMessages()` handles lazy loading automatically

## Legacy Suffix Pattern (deprecated)

The `_male`/`_female`/`_other` suffix pattern still works but **should not be used for new keys**:

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

Migrate to ICU `select` instead:

```json
{
  "target": {
    "actor": {
      "role": "{gender, select, male {{name} est inscrit comme {role}} female {{name} est inscrite comme {role}} other {{name} est inscrit(e) comme {role}}}"
    }
  }
}
```
