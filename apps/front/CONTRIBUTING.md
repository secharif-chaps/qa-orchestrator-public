# Contributing to MINT Front

This document describes the code quality tools and rules applied on this project. The CI pipeline and pre-commit hooks enforce them automatically.

---

## Tools Overview

### Prettier — The Formatter

- Handles **formatting only**: indentation, quotes, semicolons, line length, line breaks
- Runs on everything: JS, TS, Vue, CSS, JSON, HTML, etc.
- Has no opinion on code quality, only on appearance
- Example: `const x = "hello"` → `const x = 'hello'`

### ESLint — The JavaScript/TypeScript Linter

- Detects **logic and quality errors** in JS/TS/Vue code
- Unused variables, missing imports, dead code, bad practices, complexity
- Can also auto-fix some issues
- Example: `Variable 'x' is defined but never used`

### Stylelint — The CSS Linter

- Same role as ESLint but for **CSS** (including `<style>` blocks in `.vue` files)
- Unknown properties, invalid selectors, unrecognized at-rules (like `@reference`)
- Delegates CSS formatting to Prettier via `stylelint-prettier`
- Example: `Unexpected unknown property 'colr'`

### Summary

| Tool          | Role         | Scope                             |
| ------------- | ------------ | --------------------------------- |
| **Prettier**  | Formatting   | Everything (JS, CSS, HTML, JSON…) |
| **ESLint**    | Code quality | JS / TS / Vue                     |
| **Stylelint** | CSS quality  | CSS / `<style>` in Vue            |

The three are complementary and do not overlap (when properly configured).

---

## Configuration

### Prettier (`.prettierrc.json`)

| Rule                          | Effect                                                 |
| ----------------------------- | ------------------------------------------------------ |
| `semi: false`                 | No semicolons at end of lines                          |
| `singleQuote: true`           | Single quotes (`'`) instead of double quotes (`"`)     |
| `printWidth: 100`             | Automatic line break beyond 100 characters             |
| `prettier-plugin-tailwindcss` | Automatic Tailwind class sorting in the official order |

### ESLint (`eslint.config.ts`)

| Key                                   | Effect                                                    |
| ------------------------------------- | --------------------------------------------------------- |
| `flat/essential`                      | Vue essential rules (template errors, invalid directives) |
| `vueTsConfigs.recommended`            | Recommended TypeScript rules for Vue files                |
| `skipFormatting`                      | Disables ESLint formatting rules (Prettier handles that)  |
| `vue/multi-word-component-names: off` | Allows single-word component names (e.g. `Index.vue`)     |
| `vue/no-v-html: error`                | Forbids `v-html` to prevent XSS vulnerabilities           |

### Stylelint (`.stylelintrc`)

| Key                                  | Effect                                                                   |
| ------------------------------------ | ------------------------------------------------------------------------ |
| `stylelint-config-recommended`       | Base CSS rules (valid properties, correct syntax)                        |
| `stylelint-config-tailwindcss`       | Allows Tailwind directives (`@apply`, `@tailwind`, etc.)                 |
| `stylelint-config-recommended-vue`   | Support for `<style>` blocks in `.vue` files                             |
| `stylelint-prettier`                 | Delegates CSS formatting to Prettier (avoids conflicts)                  |
| `at-rule-no-unknown` + ignoreAtRules | Allows Tailwind v4 directives: `@reference`, `@custom-variant`, `@theme` |
