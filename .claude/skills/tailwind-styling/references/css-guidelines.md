# CSS & Styling Standards

## Core Principles

- **ALWAYS** use Tailwind CSS classes, avoid custom CSS
- **NEVER** hard-code colors, use semantic color tokens
- **ALWAYS** use flexbox with gap utilities for spacing between siblings
- **NEVER** use margin-based spacing between sibling elements

---

## Tailwind v4 — Deprecated Classes

This project uses **Tailwind v4.1**. Several v3 class names are deprecated. ALWAYS use the v4 equivalents:

| Deprecated (v3) | Use instead (v4) |
|---|---|
| `flex-shrink-0` | `shrink-0` |
| `flex-shrink` | `shrink` |
| `flex-grow-0` | `grow-0` |
| `flex-grow` | `grow` |
| `overflow-clip` | `overflow-clip` (same, but check context) |
| `decoration-clone` | `box-decoration-clone` |
| `decoration-slice` | `box-decoration-slice` |

When modifying an existing file, **fix any deprecated classes** found.

---

## Figma → CSS → Template Mapping

When translating a Figma mockup to code, follow this mapping. Figma tokens use the format `{usage}-{semantic}-{variant}` where variant can be `base`, `hovered`, `pressed`, `light`, `lighter`, `muted`, etc.

### Translation Rules

1. Figma `-base` → **removed** (it's the default token)
2. Figma `font-X-Y` → CSS `--color-X-Y-font` → Template `text-X-Y-font`
3. Figma `bg-X-Y` → CSS `--color-X-Y` → Template `bg-X-Y`
4. Figma `stroke-X-Y` → CSS `--color-X-Y-stroke` → Template `border-X-Y-stroke`
5. Interactive variants (`hovered`, `pressed`) → add Tailwind modifier (`hover:`, `active:`)

### Examples

| Figma | CSS variable | Template class |
|---|---|---|
| `font-primary-base` | `--color-primary-font` | `text-primary-font` |
| `font-primary-hovered` | `--color-primary-hovered-font` | `hover:text-primary-hovered-font` |
| `font-error-base` | `--color-error-font` | `text-error-font` |
| `font-success-light` | `--color-success-light-font` | `text-success-light-font` |
| `bg-primary-base` | `--color-primary` | `bg-primary` |
| `bg-primary-hovered` | `--color-primary-hovered` | `hover:bg-primary-hovered` |
| `bg-primary-light` | `--color-primary-light` | `bg-primary-light` |
| `bg-primary-muted` | `--color-primary-muted` | `bg-primary-muted` |
| `stroke-primary-base` | `--color-primary-stroke` | `border-primary-stroke` |
| `stroke-error-base` | `--color-error-stroke` | `border-error-stroke` |
| `stroke-primary-hovered` | `--color-primary-hovered-stroke` | `hover:border-primary-hovered-stroke` |

---

## Semantic Color System

### Semantic Color Tokens

| Token | Purpose | Use Case |
|---|---|---|
| `primary` | Main brand (Sage) | Primary buttons, active states |
| `accent` | Emphasis (Rose) | Special badges, callouts |
| `success` | Positive (Green) | Success messages, confirmations |
| `warning` | Caution (Orange) | Warnings, pending states |
| `error` | Negative (Red) | Error messages, validation |
| `info` | Informational (Blue) | Info banners, help text |

### Token Variants

Each semantic color has **5 variants**:

```
{color}                    → Solid background (buttons, solid badges)
{color}-content            → Text/icons on solid background
{color}-light              → Light background (alerts, toasts)
{color}-light-content      → Text/icons on light background
{color}-stroke             → Borders and outlines
```

Note: `primary` also has `primary-light-stroke`.

### Usage Patterns

```vue
<!-- Solid button -->
<button class="bg-primary text-primary-content px-4 py-2 rounded-lg">
  Primary Action
</button>

<!-- Light alert -->
<div class="bg-success-light text-success-light-content border border-success-stroke rounded-lg p-4">
  Operation successful!
</div>

<!-- Light badge -->
<span class="bg-info-light text-info-light-content border border-info-stroke px-2 py-1 rounded">
  New
</span>
```

### Color Palettes (for decoration only)

Available palettes (each with shades 50-950):

| Palette | Role |
|---|---|
| `sage` | Primary brand |
| `almond` | Secondary brand |
| `rose` | Accent |
| `green` | Success |
| `orange` | Warning |
| `red` | Error |
| `blue` | Info |
| `gray` | Neutral |
| `indigo` | Additional |
| `yellow` | Additional (Target module) |
| `cherry` | Additional (Target module) |
| `cyan` | Additional (Target module) |

Usage: `bg-sage-100`, `text-indigo-600`, etc. **Prefer semantic tokens** over palette colors when the color conveys meaning.

### Background Layering

```vue
<body class="bg-base-100">              <!-- Page background -->
  <div class="bg-base-200">             <!-- Card/modal background -->
    <div class="bg-base-300">           <!-- Nested card -->
    </div>
  </div>
</body>
```

Additional base tokens: `bg-sidebar`, `border-border-1`, `border-border-2`, `bg-sidebar-border`, `text-sidebar-count`.

### Color Rules

**DO**:
- Use semantic tokens: `bg-success`, `text-success-content`
- Pair backgrounds with their matching `-content` color
- Use `-light` variants for alerts, toasts, subtle backgrounds
- Use `-stroke` for borders

**DON'T**:
- Use raw Tailwind palette colors for meaning: ~~`bg-green-500`~~ → use `bg-success`
- Use raw hex colors: ~~`bg-[#29ad72]`~~
- Mix incompatible pairs: ~~`bg-success text-error-content`~~
- Ignore content pairing: ~~`bg-primary text-black`~~

---

## Custom Tokens

### Border Radius

The project defines custom radius tokens. **NEVER** use arbitrary values.

| Class | Value | Use |
|---|---|---|
| `rounded-none` | 0 | No rounding |
| `rounded-card` | 16px | Cards, modals |
| `rounded-block` | 24px | Large blocks, sections |
| `rounded-full` | 9999px | Circles, pills |

For smaller elements (badges, inputs), use standard Tailwind: `rounded`, `rounded-lg`, etc.

### Shadows

**Neutral shadows** (elevation levels):

| Class | Use |
|---|---|
| `shadow-shadow-1` | Subtle elevation (dropdowns, hover) |
| `shadow-shadow-2` | Medium elevation (cards) |
| `shadow-shadow-3` | High elevation (modals) |
| `shadow-shadow-4` | Maximum elevation (floating panels) |
| `shadow-inner` | Inset shadow |
| `shadow-volume` | 3D effect |

**Colored shadows** (for decorative emphasis):

| Class | Color |
|---|---|
| `shadow-pink` | Rose/accent |
| `shadow-green` | Success |
| `shadow-blue` | Info |
| `shadow-orange` | Warning |
| `shadow-red` | Error |

### Breakpoints (Custom)

These are **NOT standard Tailwind breakpoints**:

| Prefix | Width | Use |
|---|---|---|
| `xs:` | 480px | Mobile |
| `sm:` | 744px | Large mobile/tablet |
| `md:` | 1024px | Laptop |
| `lg:` | 1440px | Desktop |
| `xl:` | 1920px | Large desktop |

### Typography

Font: **Hanken Grotesk**

| Class | Size | Use |
|---|---|---|
| `text-3xl` | 24px | — |
| `text-2xl` | 24px | Page titles |
| `text-xl` | 20px | Section headers |
| `text-lg` | 16px | Subheaders |
| `text-base` | 14px | Body text |
| `text-sm` | 12px | Small text, captions |
| `text-xs` | 11px | Extra small |

Font weights: `font-regular` (400), `font-semibold` (600), `font-bold` (700).

Note: `font-medium` is NOT defined as a custom weight. Use `font-semibold` instead.

---

## Layout & Spacing

### Parent Controls Spacing (Gap Utilities)

**CRITICAL**: Use flexbox with gap utilities for spacing, NEVER margin-based spacing between siblings.

```vue
<!-- CORRECT: Parent controls spacing with gap -->
<div class="flex flex-col gap-4">
  <PageHeader />
  <Filters />
  <Alert v-if="error" />
  <DataTable />
  <Pagination />
</div>

<!-- WRONG: Margin-based spacing -->
<div>
  <PageHeader class="mb-8" />
  <Filters class="mb-6" />
</div>
```

### Spacing Scale (4px Grid)

| Class | Size |
|---|---|
| `gap-1` | 4px |
| `gap-2` | 8px |
| `gap-3` | 12px |
| `gap-4` | 16px |
| `gap-6` | 24px |
| `gap-8` | 32px |

---

## Responsive Design

### Mobile-First Approach

```vue
<!-- Mobile-first grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
  <Card v-for="item in items" :key="item.id" />
</div>

<!-- Responsive visibility -->
<div class="hidden md:block">Desktop only</div>
<div class="block md:hidden">Mobile only</div>
```

---

## Dark Mode

Dark mode is class-based (`@custom-variant dark`). Semantic tokens and Vuellar components adapt automatically.

When needed for custom elements, use `dark:` prefix:

```vue
<div class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
  Theme-aware content
</div>
```

Three themes available: `light` (default), `dark`, `contrast`.
