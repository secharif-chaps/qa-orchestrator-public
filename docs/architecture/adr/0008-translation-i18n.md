# ADR-0008: vue-i18n for Internationalization

## Status

**Status:** Accepted

**Date:** 2024-01-15

**Decision Makers:** ChapsMind Engineering Team

**Tags:** frontend, i18n, internationalization, vue

---

## Context

ChapsMind serves international clients and needs to support multiple languages for the user interface. The application requires:

- Support for French and English initially, with ability to add more languages
- Dynamic language switching without page reload
- Interpolation for dynamic values in translations
- Pluralization support for quantities
- Date and number formatting according to locale
- TypeScript integration for type-safe translation keys
- Compatibility with Vue 3 Composition API architecture (ADR-0001)

The frontend is built with Vue 3 using the Composition API exclusively, which influences how internationalization should be implemented.

---

## Decision

We will use **vue-i18n** with **Composition API mode** (`legacy: false`) for internationalization.

Key implementation decisions:

- Configure vue-i18n with `legacy: false` to use Composition API mode
- Use the `useI18n()` composable in components
- Translation files organized by locale in JSON format
- Type-safe translation keys using TypeScript
- **Critical usage pattern**: `t(key, { param: value })` - NEVER use fallback as second parameter

### Correct Translation Pattern

```typescript
// Configuration
import { createI18n } from 'vue-i18n'

const i18n = createI18n({
  legacy: false,  // REQUIRED: Enable Composition API mode
  locale: 'en',
  fallbackLocale: 'en',
  messages: {
    en: { /* translations */ },
    fr: { /* translations */ }
  }
})
```

```vue
<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// CORRECT: Parameters as named object
const greeting = t('hello', { name: 'John' })
const count = t('items', { count: 5 })

// INCORRECT: Do NOT use fallback as second parameter
// const wrong = t('key', 'fallback', { param: value })  // WRONG!
</script>

<template>
  <h1>{{ t('page.title') }}</h1>
  <p>{{ t('welcome.message', { username }) }}</p>
</template>
```

### Translation File Structure

```
src/
  locales/
    en.json
    fr.json
  plugins/
    i18n.ts
```

---

## Options Considered

### Option 1: vue-i18n with Composition API Mode (Chosen)

**Description:** Use vue-i18n, the official internationalization library for Vue.js, configured in Composition API mode (`legacy: false`) to align with the project's Vue 3 architecture.

**Pros:**
- Official Vue.js i18n solution with full ecosystem support
- Native Composition API support with `useI18n()` composable
- Tree-shakable for smaller bundle sizes
- Excellent TypeScript support
- Built-in interpolation, pluralization, and formatting
- Hot module replacement for translations during development
- Large community and comprehensive documentation
- Battle-tested in production applications

**Cons:**
- Must use correct API pattern (Composition vs Legacy differs)
- Learning curve for message format syntax
- Bundle size increases with more locales

### Option 2: vue-i18n with Legacy Mode

**Description:** Use vue-i18n in Legacy mode (Vue 2 compatible API) with Options API patterns.

**Pros:**
- Familiar API for developers from Vue 2 projects
- More tutorials and Stack Overflow answers available
- `this.$t()` pattern is intuitive

**Cons:**
- **Incompatible with Composition API architecture** (ADR-0001)
- Uses `this` context not available in `<script setup>`
- Mixes paradigms (Options API in Composition API project)
- Deprecated pattern that may lose support
- Cannot use directly in composables

### Option 3: Custom i18n Solution

**Description:** Build a custom internationalization solution using simple key-value lookups and reactive locale state.

**Pros:**
- Full control over implementation
- Minimal bundle size
- No external dependencies
- Tailored to exact requirements

**Cons:**
- Must implement pluralization, interpolation, formatting manually
- Missing features (date/number formatting, message compilation)
- Maintenance burden for i18n infrastructure
- No community support or shared knowledge
- Reinventing tested solutions
- Missing TypeScript definitions for keys

### Option 4: @intlify/unplugin-vue-i18n

**Description:** Use vue-i18n with the unplugin for build-time optimizations and SFC i18n blocks.

**Pros:**
- Pre-compiles messages for better runtime performance
- Supports `<i18n>` blocks in SFC files
- Smaller runtime bundle

**Cons:**
- Additional build configuration complexity
- Co-located translations harder to manage across locales
- Not necessary for current application scale
- Can be added later as optimization

---

## Consequences

### Positive

- **Ecosystem Alignment**: Uses official Vue.js i18n solution with active maintenance
- **Composition API Native**: `useI18n()` composable fits project architecture perfectly
- **Type Safety**: TypeScript integration provides autocomplete for translation keys
- **Feature Complete**: Built-in pluralization, interpolation, date/number formatting
- **Developer Experience**: Hot reload for translations during development
- **Scalability**: Easy to add new languages by adding locale files

### Negative

- **API Learning Curve**: Team must understand Composition API mode vs Legacy mode differences
- **Strict Pattern**: Must use `t(key, { param })` pattern, not `t(key, fallback, { param })`
- **Bundle Size**: Each locale adds to bundle (can mitigate with lazy loading)

### Neutral

- Translation file organization requires team conventions (by feature vs by locale)
- Some developers may need to unlearn Vue 2 / Legacy mode patterns

---

## Implementation Notes

### Plugin Configuration

```typescript
// src/plugins/i18n.ts
import { createI18n } from 'vue-i18n'
import en from '@/locales/en.json'
import fr from '@/locales/fr.json'

export const i18n = createI18n({
  legacy: false,          // CRITICAL: Enables Composition API mode
  locale: 'en',
  fallbackLocale: 'en',
  messages: { en, fr },
  missingWarn: import.meta.env.DEV,
  fallbackWarn: import.meta.env.DEV,
})
```

### Usage in Components

```vue
<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()

// Simple translation
const title = t('page.title')

// With interpolation - CORRECT PATTERN
const welcome = t('welcome.message', { name: 'John' })

// Pluralization
const items = t('cart.items', { count: 3 })

// Change locale
function switchLocale(newLocale: string) {
  locale.value = newLocale
}
</script>

<template>
  <h1>{{ t('page.title') }}</h1>
  <p>{{ t('user.greeting', { username }) }}</p>
  <button @click="switchLocale('fr')">
    {{ t('language.switch') }}
  </button>
</template>
```

### Translation File Format

```json
// src/locales/en.json
{
  "page": {
    "title": "Dashboard"
  },
  "user": {
    "greeting": "Hello, {username}!"
  },
  "cart": {
    "items": "No items | {count} item | {count} items"
  },
  "company": {
    "created": "Company {name} created successfully",
    "deleted": "{count} companies deleted"
  }
}
```

### Common Mistakes to Avoid

```typescript
// WRONG: Fallback as second parameter (Legacy mode syntax)
t('key', 'Fallback text', { name: 'value' })

// CORRECT: Only key and named parameters
t('key', { name: 'value' })

// WRONG: Positional parameters (Legacy mode)
t('key', ['value1', 'value2'])

// CORRECT: Named parameters in object
t('key', { first: 'value1', second: 'value2' })
```

### Usage in Composables

```typescript
// composables/useFormValidation.ts
import { useI18n } from 'vue-i18n'

export function useFormValidation() {
  const { t } = useI18n()

  function getErrorMessage(field: string, rule: string) {
    return t(`validation.${rule}`, { field: t(`fields.${field}`) })
  }

  return { getErrorMessage }
}
```

---

## References

- [vue-i18n Documentation](https://vue-i18n.intlify.dev/)
- [Composition API Mode Guide](https://vue-i18n.intlify.dev/guide/advanced/composition.html)
- [Message Format Syntax](https://vue-i18n.intlify.dev/guide/essentials/syntax.html)
- [ADR-0001: Vue.js 3 with Composition API](./0001-vue3-composition-api.md)
- [ChapsMind Frontend Standards](../../../agent-os/standards/frontend/)
