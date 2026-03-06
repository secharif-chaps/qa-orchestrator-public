# CSS & Styling Standards

## Core Principles

- **ALWAYS** use Tailwind CSS classes, avoid custom CSS
- **NEVER** hard-code colors, use semantic color tokens
- **ALWAYS** use flexbox with gap utilities for spacing between siblings
- **NEVER** use margin-based spacing between sibling elements

---

## Semantic Color System

### Philosophy

Use meaning-based tokens instead of palette-based colors. This ensures:

- Automatic theme adaptation (light/dark mode)
- WCAG accessibility compliance
- Consistent visual language
- Easy maintenance and theming

### Semantic Color Tokens

| Token       | Purpose                  | Use Case                        |
| ----------- | ------------------------ | ------------------------------- |
| `primary`   | Main brand actions       | Primary buttons, active states  |
| `secondary` | Secondary brand elements | Secondary buttons, badges       |
| `accent`    | Emphasis and highlights  | Special badges, callouts        |
| `success`   | Positive feedback        | Success messages, confirmations |
| `warning`   | Caution states           | Warnings, pending states        |
| `error`     | Negative feedback        | Error messages, validation      |
| `info`      | Informational            | Info banners, help text         |

### Token Variants

Each semantic color has **5 variants**:

```
{color}                    → Solid background (buttons, solid badges)
{color}-content            → Text/icons on solid background
{color}-light              → Light background (alerts, toasts)
{color}-light-content      → Text/icons on light background
{color}-stroke             → Borders and outlines
```

### Usage Patterns

**Solid Button**

```vue
<button class="bg-primary text-primary-content rounded-lg px-4 py-2">
  Primary Action
</button>
```

**Light Alert**

```vue
<div
    class="bg-success-light text-success-light-content border-success-stroke rounded-lg border p-4"
>
  Operation successful!
</div>
```

**Badge (Light)**

```vue
<span class="bg-info-light text-info-light-content border-info-stroke rounded border px-2 py-1">
  New
</span>
```

**Card with Border**

```vue
<div class="bg-base-200 border-primary-stroke rounded-card border p-6">
  Card content
</div>
```

### Background Layering

Use `base` colors for application hierarchy:

```vue
<body class="bg-base-100">              <!-- Page background -->
  <div class="bg-base-200">             <!-- Card/modal background -->
    <div class="bg-base-300">           <!-- Nested card -->
    </div>
  </div>
</body>
```

### Color Rules

✅ **DO**:

- Use semantic tokens: `bg-success`, `text-success-content`
- Pair backgrounds with their matching `-content` color
- Use `-light` variants for non-critical/informational UI
- Use solid variants for primary actions
- Use `-stroke` for borders

❌ **DON'T**:

- Use palette colors directly: ~~`bg-green-500`~~, ~~`text-red-600`~~
- Use raw hex colors: ~~`bg-[#29ad72]`~~
- Mix incompatible pairs: ~~`bg-success text-error-content`~~
- Ignore content pairing: ~~`bg-primary text-black`~~

---

## Layout & Spacing

### Parent Controls Spacing (Gap Utilities)

**CRITICAL**: Use flexbox with gap utilities for spacing, NEVER margin-based spacing between siblings.

```vue
<!-- ✅ CORRECT: Parent controls spacing with gap -->
<template>
    <div class="flex flex-col gap-4">
        <PageHeader />
        <Filters />
        <Alert v-if="error" />
        <DataTable />
        <Pagination />
    </div>
</template>

<!-- ❌ INCORRECT: Margin-based spacing -->
<template>
    <div>
        <PageHeader class="mb-8" />
        <Filters class="mb-6" />
        <Alert v-if="error" class="mb-6" />
        <DataTable class="mb-4" />
        <Pagination />
    </div>
</template>
```

### Benefits

- **Consistent spacing** - one gap value controls all spacing
- **Easier maintenance** - change spacing in one place
- **Cleaner code** - no margin classes scattered throughout
- **Predictable layouts** - parent always controls child spacing

### Spacing Scale (4px Grid)

| Class   | Size |
| ------- | ---- |
| `gap-1` | 4px  |
| `gap-2` | 8px  |
| `gap-3` | 12px |
| `gap-4` | 16px |
| `gap-6` | 24px |
| `gap-8` | 32px |

---

## Responsive Design

### Mobile-First Approach

Start with mobile layout and progressively enhance:

```vue
<template>
    <!-- Mobile-first grid -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <Card v-for="item in items" :key="item.id">
            {{ item.title }}
        </Card>
    </div>

    <!-- Responsive spacing -->
    <div class="p-4 md:p-6 lg:p-8">
        <h1 class="text-lg md:text-xl lg:text-2xl">Responsive Title</h1>
    </div>

    <!-- Responsive visibility -->
    <div class="hidden md:block">Desktop only</div>
    <div class="block md:hidden">Mobile only</div>
</template>
```

### Breakpoints

| Breakpoint | Width  | Use                 |
| ---------- | ------ | ------------------- |
| `xs`       | 480px  | Mobile              |
| `sm`       | 744px  | Large mobile/tablet |
| `md`       | 1024px | Laptop              |
| `lg`       | 1440px | Desktop             |
| `xl`       | 1920px | Large desktop       |

### Multi-line CSS Classes

For complex responsive styles, use multi-line formatting:

```vue
<div
    class="w-full cursor-pointer rounded bg-gray-50 p-4 hover:bg-gray-100 sm:p-8 sm:font-medium md:p-10 md:text-lg lg:p-12 lg:text-xl lg:font-semibold xl:p-14 xl:text-2xl dark:bg-gray-900 dark:hover:bg-gray-800"
>
  Content
</div>
```

---

## Typography

### Scale

| Class       | Size | Use          |
| ----------- | ---- | ------------ |
| `text-2xl`  | 24px | Headline 3XL |
| `text-xl`   | 20px | Headline 2XL |
| `text-lg`   | 16px | Headline LG  |
| `text-base` | 14px | Body text    |
| `text-sm`   | 12px | Small text   |
| `text-xs`   | 11px | Extra small  |

### Font Weights

- `font-normal` - Regular text
- `font-medium` - Slightly emphasized
- `font-semibold` - Section headers
- `font-bold` - Page titles, important

---

## Dark Mode

### Class-Based Dark Mode

Dark mode is activated by adding `dark` class to parent:

```vue
<div class="dark">
  <!-- All components inside use dark mode -->
  <Button label="Dark mode button" />
</div>
```

### Manual Dark Mode Classes

When needed, use `dark:` prefix:

```vue
<div class="bg-white text-gray-900 dark:bg-gray-900 dark:text-white">
  Theme-aware content
</div>
```

**Note**: Vuellar components and semantic tokens handle dark mode automatically.

---

## Best Practices

### Consistent Methodology

Apply Tailwind patterns consistently across the entire project

### Avoid Overriding Framework Styles

Work with Tailwind patterns, don't fight against them

### Maintain Design Tokens

Use semantic color tokens for consistency

### Minimize Custom CSS

Leverage Tailwind utilities to reduce custom CSS maintenance

### Performance

Tailwind purges unused CSS in production automatically

### Touch-Friendly Design

Ensure tap targets are at least 44x44px for mobile

### Readable Typography

Maintain readable font sizes across all breakpoints
