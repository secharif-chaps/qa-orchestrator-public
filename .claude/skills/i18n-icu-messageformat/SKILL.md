---
name: i18n-icu-messageformat
description: >
  ICU MessageFormat internationalization. CRITICAL - Activates when creating OR modifying
  any .vue file that uses t() or $t(), or any file in src/i18n/locales/. When modifying
  existing components, verify correct t() usage (no fallback as 2nd arg, no pipe syntax,
  ICU plural/select syntax). When adding keys, add to ALL locale files. Fix violations found.
license: MIT
allowed-tools: Read, Write, Edit, Glob, Grep
metadata:
  author: owlint
  version: "1.0"
  stack: vue
---

## When to use this skill

- When adding or modifying translation keys in `src/i18n/locales/*.json`
- When implementing plurals in translation messages
- When implementing gender agreement or conditional branching (select)
- When nesting select + plural combinations in a single key
- When calling `t()` or `$t()` in Vue components with parameters
- When adding a new language to the application
- When reviewing i18n code for correctness

# ICU MessageFormat for vue-i18n

**CRITICAL**: The pipe syntax (`zero | one | many`) is **disabled**. A custom `messageCompiler` using `@messageformat/core` replaces the default vue-i18n compiler. All plurals and branching use ICU MessageFormat syntax.

## Key Rules

1. **Always** use `t(key, { param: value })` — never `t(key, 'fallback', { param })` and never `t(key, { count }, count)` (no positional 3rd arg)
2. **Plurals**: `"{count, plural, =0 {No items} one {# item} other {# items}}"`
3. **Select**: `"{gender, select, male {assigned to} female {assigned to} other {assigned to}} {name}"`
4. **`other` branch is mandatory** in both `plural` and `select`
5. **`#`** is shorthand for the selector variable value; other variables stay as `{name}`
6. **French CLDR**: 0 falls under `one` (not `other`); use `=0` for distinct zero messages ("Aucun...")
7. **Key naming**: `module.feature.element` in camelCase — namespaces: `common`, `target`, `screen`, `dashboard`, `settings`, `admin`
8. **File format**: Nested JSON, one file per language in `src/i18n/locales/`
9. **All locales**: Add keys to ALL locale files (`en-US.json`, `fr-FR.json`)

## Patterns

### Plural (2 forms)

```json
{
  "common": {
    "time": {
      "hoursAgo": "{count, plural, one {# hour ago} other {# hours ago}}"
    }
  }
}
```

```typescript
t('common.time.hoursAgo', { count: 1 })  // "1 hour ago"
t('common.time.hoursAgo', { count: 5 })  // "5 hours ago"
```

### Plural (3 forms with zero)

```json
{
  "target": {
    "watchFiles": {
      "sub_title": "{count, plural, =0 {No actors displayed} one {1 actor displayed out of {total}} other {# actors displayed out of {total}}}"
    }
  }
}
```

```typescript
t('target.watchFiles.sub_title', { count: 0, total: 10 })  // "No actors displayed"
t('target.watchFiles.sub_title', { count: 1, total: 10 })  // "1 actor displayed out of 10"
t('target.watchFiles.sub_title', { count: 5, total: 10 })  // "5 actors displayed out of 10"
```

### Select (gender)

```json
{
  "target": {
    "company": {
      "assignedTo": "{gender, select, male {assigned to} female {assigned to} other {assigned to}} {name}"
    }
  }
}
```

```typescript
t('target.company.assignedTo', { gender: 'female', name: 'Marie' })
```

### Select (entity type from backend)

```json
{
  "target": {
    "entity": {
      "created": "{type, select, company {Company {name} created} person {Person {name} created} other {Item {name} created}}"
    }
  }
}
```

```typescript
t('target.entity.created', { type: entity.type, name: entity.name })
```

### Nested select + plural

```json
{
  "search": {
    "resultCount": "{type, select, company {{count, plural, =0 {No companies found} one {# company found} other {# companies found}}} person {{count, plural, =0 {No people found} one {# person found} other {# people found}}} other {{count, plural, =0 {No results} one {# result} other {# results}}}}"
  }
}
```

### Variable named `{nb}` instead of `{count}`

The ICU plural selector must match the parameter name passed by the component:

```json
{
  "documents": {
    "nb_results": "{nb, plural, =0 {No documents} one {# document} other {# documents}}"
  }
}
```

```typescript
// Component passes { nb: totalItems } — NOT { count: totalItems }
t('documents.nb_results', { nb: totalItems })
```

## French CLDR specifics

In French, 0 falls under `one` (not `other`). Use `=0` to override:

```json
{
  "en-US": "{count, plural, =0 {No documents} one {# document} other {# documents}}",
  "fr-FR": "{count, plural, =0 {Aucun document} one {# document} other {# documents}}"
}
```

Without `=0`, French count=0 would match `one` and display "0 document" instead of "Aucun document".

## Adding a new language

1. Create `src/i18n/locales/{locale}.json` (copy structure from `en-US.json`)
2. Add datetime formats in `src/i18n/datetime-formats.ts`
3. Add the locale option in `LocaleSwitcher.vue`
4. No other changes needed — the `messageCompiler` handles all locales automatically

## References

- Architecture and advanced patterns: [references/icu-syntax.md](references/icu-syntax.md)
- ADR: `docs/architecture/adr/0012-frontend-translation-management-strategy.md` (section 4)
