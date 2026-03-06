---
name: frontend-responsive
description: Mobile-first responsive design standards for Vue/Nuxt with Tailwind CSS breakpoints. Use when applying responsive classes mobile-first (flex-col lg:flex-row), using breakpoints (sm:640px, md:768px, lg:1024px, xl:1280px), implementing responsive grids (grid-cols-1 sm:grid-cols-2 lg:grid-cols-4), or ensuring 44x44px minimum touch targets. Activates when handling show/hide content by breakpoint (hidden lg:flex), responsive typography scaling, or fluid container patterns.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When building layouts mobile-first (`flex-col lg:flex-row`, NOT `flex-row max-lg:flex-col`)
- When using Tailwind breakpoints (`sm:`, `md:`, `lg:`, `xl:`, `2xl:`)
- When creating responsive grids (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`)
- When implementing fluid containers with `max-w-7xl mx-auto px-4 sm:px-6`
- When showing/hiding content by breakpoint (`hidden lg:flex`, `lg:hidden`)
- When scaling typography responsively (`text-2xl sm:text-3xl lg:text-4xl`)
- When constraining text width for readability (`max-w-prose`)
- When ensuring 44x44px minimum touch targets (`min-h-11 min-w-11`)
- When adding adequate spacing between interactive elements
- When using responsive images with `srcset` and `sizes`
- When maintaining aspect ratios with `aspect-video`, `aspect-square`
- When testing at all breakpoints and 200% browser zoom

# Frontend Responsive

## Documentation

For detailed patterns, see:

- [Responsive standards](references/responsive.md) - Breakpoints, mobile-first development, layout patterns, typography, touch-friendly design, visibility, images, testing checklist
