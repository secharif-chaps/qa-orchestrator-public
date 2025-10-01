# Theming Guidelines

This document outlines the theming rules and best practices for the Mint application.

## Core Principles

1. **Always use semantic color classes** instead of direct Tailwind colors
2. **Exception**: Only use specific colors (e.g., `bg-purple-400`) when you need a color that doesn't fit the theme system
3. **Prefer opacity modifiers** over color shades for better dark mode compatibility

## Semantic Color Classes

### Background Colors

- `bg-bg1` - Primary background for cards, modals, and main content areas
- `bg-bg2` - Secondary background for hover states and subtle backgrounds
- `bg-bg3` - Website/app background (the main canvas)

### Text Colors

- `text-base` - Normal text color (default body text)
- `text-secondary` - Less important text (metadata, hints, descriptions)
- `text-primary` - Accent color text (use sparingly - for emphasis, links, important actions)

### Border Colors

- `border-border-1` - Higher contrast borders (use rarely)
- `border-border-2` - Standard borders (preferred for most cases)

### Primary/Accent Color

- `bg-primary` - Primary background color (buttons, active states)
- `text-primary` - Primary text color (icons, buttons, links)
- `border-primary` - Primary border color (focus states, active elements)

### Status Colors

- `text-error` / `bg-error` - Error states
- `text-success` / `bg-success` - Success states

### Component-Specific Colors

- `bg-sidebar` - Sidebar background
- `border-sidebar-border` - Sidebar border
- `text-sidebar-count` - Sidebar count badges

## Opacity Modifiers

Use opacity modifiers with colors for better dark mode compatibility:

✅ **Good**: `bg-primary/10`, `bg-primary/20`, `text-primary/80`
❌ **Avoid**: `bg-blue-100` (looks bad in dark mode)

Common opacity values:

- `/10` - Very subtle backgrounds
- `/20` - Subtle backgrounds
- `/50` - Medium opacity
- `/80` - Slightly transparent

## Usage Examples

```vue
<!-- Card component -->
<div class="bg-bg1 border border-border-2 rounded-lg p-4">
  <h3 class="text-base font-semibold">Card Title</h3>
  <p class="text-secondary text-sm">Less important description</p>
  <button class="bg-primary text-white hover:bg-primary/80">
    Action
  </button>
</div>

<!-- Hover state with opacity -->
<div class="bg-bg1 hover:bg-primary/10">
  Hoverable item
</div>

<!-- Icon with primary color -->
<i class="fas fa-check text-primary"></i>
```

## Dark Mode

The semantic colors automatically adjust for dark mode. The CSS variables are defined separately for light and dark themes in `main.css`.

Current behavior:

- Background colors invert (light → dark)
- Text colors adjust for contrast
- Primary colors may shift hue for better visibility

## Best Practices

1. **Consistency**: Always use semantic classes for consistency across the app
2. **Contrast**: Ensure sufficient contrast between text and backgrounds
3. **Hover States**: Use `hover:bg-primary/10` or `hover:bg-bg2` for subtle hover effects
4. **Focus States**: Use `focus:ring-primary` for keyboard navigation
5. **Transitions**: Add `transition-colors` when colors change on hover/focus

## Common Patterns

### Interactive Elements

```vue
<!-- Button -->
<button class="bg-primary text-white hover:bg-primary/80 transition-colors"></button>
```

### Status Indicators

```vue
<!-- Success -->
<div class="text-success bg-success/10 border border-success/20"></div>
```

### Disabled States

```vue
<button class="bg-primary/50 text-white/50 cursor-not-allowed"></button>
```

## DO NOT

- ❌ Use direct Tailwind colors like `bg-slate-200` (except for specific non-themed elements)
- ❌ Use color shades like `bg-blue-100` in components (use opacity instead)
- ❌ Override theme colors with inline styles
- ❌ Use `text-primary` excessively (it's an accent color)
- ❌ Mix semantic and direct colors in the same component
