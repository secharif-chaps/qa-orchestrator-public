---
name: frontend-css
description: Tailwind CSS and styling standards for Vue/Nuxt frontend. Use when applying semantic color tokens (bg-primary, text-success-content, border-error-stroke), using gap utilities instead of margin for sibling spacing, implementing dark mode with `dark:` prefix, or following the 4px spacing scale. Activates when styling `.vue` components, NEVER using raw colors (bg-green-500) or hex values (bg-[#29ad72]), using base-100/200/300 for background layering, or adding responsive typography classes.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When using semantic color tokens (`bg-primary`, `text-success-content`, `border-error-stroke`)
- When using `{color}-light` variants for alerts and toasts
- When pairing backgrounds with matching `-content` colors
- When using `gap-*` utilities for spacing between siblings (NEVER margins)
- When applying background layering with `base-100`, `base-200`, `base-300`
- When AVOIDING raw colors (`bg-green-500`) or hex values (`bg-[#29ad72]`)
- When using `dark:` prefix for dark mode styles
- When following the 4px spacing scale (`gap-1` = 4px, `gap-4` = 16px)
- When applying responsive classes mobile-first (`md:`, `lg:`, `xl:`)
- When using typography scale (`text-xs`, `text-sm`, `text-base`, `text-lg`)
- When ensuring touch targets are at least 44x44px

# Frontend CSS

## Documentation

For detailed patterns, see:

- [CSS standards](references/css.md) - Semantic color system, color tokens, layout and spacing, responsive design, typography, dark mode
