---
name: tailwind-styling
description: Tailwind CSS styling with semantic color tokens. Use when styling components, layouts, or handling responsive design. ALWAYS use gap-based spacing (never margins between siblings) and semantic color tokens (never raw colors).
allowed-tools: Read, Write, Edit, Glob, Grep
---

# Tailwind CSS Styling

## Critical Rules

1. **ALWAYS use `flex flex-col gap-*`** for spacing between siblings
2. **NEVER use margins** (`mb-*`, `mt-*`) between sibling elements
3. **NEVER use raw colors** - use semantic tokens only

## Gap-Based Spacing

```vue
<!-- CORRECT: Parent controls spacing -->
<div class="flex flex-col gap-4">
  <Header />
  <Content />
  <Footer />
</div>

<!-- WRONG: Margin-based spacing -->
<div>
  <Header class="mb-4" />
  <Content class="mb-4" />
  <Footer />
</div>
```

## Semantic Color Tokens

| Token | Usage |
|-------|-------|
| `primary` / `primary-content` | Brand, main actions |
| `secondary` / `secondary-content` | Secondary actions |
| `success` / `success-content` | Positive states |
| `warning` / `warning-content` | Caution states |
| `error` / `error-content` | Error states |
| `info` / `info-content` | Informational |

### Light Variants (for backgrounds)

```vue
<!-- Alert with light background -->
<div class="bg-success-light text-success-light-content border border-success-stroke">
  Success message
</div>

<!-- Solid button -->
<button class="bg-primary text-primary-content">
  Submit
</button>
```

### Base Colors (background layering)

```vue
<div class="bg-base-100">  <!-- Main background -->
  <div class="bg-base-200">  <!-- Cards, elevated -->
    <div class="bg-base-300">  <!-- Nested cards -->
    </div>
  </div>
</div>
```

## Common Patterns

```vue
<!-- Page layout -->
<div class="flex flex-col gap-6 p-6">
  <header class="flex items-center justify-between">...</header>
  <main class="flex flex-col gap-4">...</main>
</div>

<!-- Card -->
<div class="bg-base-200 rounded-lg p-4 border border-base-300">
  ...
</div>

<!-- Form -->
<form class="flex flex-col gap-4">
  <Input ... />
  <Input ... />
  <Button ... />
</form>
```

## Never Do

```vue
<!-- WRONG: Raw colors -->
<div class="bg-green-500 text-white">...</div>

<!-- WRONG: Margin spacing -->
<div class="mb-4">...</div>
<div class="mb-4">...</div>

<!-- WRONG: Hex colors -->
<div style="color: #29ad72">...</div>
```
