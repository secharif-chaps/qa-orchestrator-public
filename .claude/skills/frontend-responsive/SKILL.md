---
name: frontend-responsive
description: Mobile-first responsive design with project custom breakpoints (xs:480, sm:744, md:1024, lg:1440, xl:1920). CRITICAL - Activates when adding responsive classes, grids, show/hide by breakpoint, or touch targets to any .vue file. When modifying existing files, verify breakpoints use project values (NOT Tailwind defaults) and mobile-first approach. Fix violations found.
allowed-tools: Read, Write, Edit, Glob, Grep
metadata:
  author: Lucas Gault
  version: "2.0"
---

# Frontend Responsive

## Critical Rules

1. **ALWAYS mobile-first** — `flex-col lg:flex-row`, NEVER `flex-row max-lg:flex-col`
2. **Use project breakpoints** — NOT Tailwind defaults (see table below)
3. **Touch targets 44x44px minimum** — `min-h-11 min-w-11`

## Conformity Check (when modifying existing files)

When editing a `.vue` file with responsive classes, verify:
- Mobile-first approach (no `max-*:` breakpoints, base styles are mobile)
- No references to wrong breakpoint values in comments (640px, 768px, 1280px, 1536px are NOT our breakpoints)
- Touch targets on interactive elements are at least 44x44px

## Project Breakpoints

| Prefix | Width | Use |
|---|---|---|
| `xs:` | 480px | Mobile |
| `sm:` | 744px | Large mobile/tablet |
| `md:` | 1024px | Laptop |
| `lg:` | 1440px | Desktop |
| `xl:` | 1920px | Large desktop |

**No `2xl:` breakpoint** in this project.

## Documentation

- [responsive.md](references/responsive.md) - Layout patterns, typography, touch design, visibility, images, testing checklist
