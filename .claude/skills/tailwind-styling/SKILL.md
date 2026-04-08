---
name: tailwind-styling
description: Tailwind CSS v4 styling with project-specific design tokens. CRITICAL - Activates when styling any .vue file. Enforces semantic color tokens (never raw colors), gap-based spacing (never margins between siblings), custom radius/shadow/breakpoint tokens, and Tailwind v4 class names (no deprecated v3 classes like flex-shrink-0). When modifying existing files, fix any deprecated classes or raw colors found.
allowed-tools: Read, Write, Edit, Glob, Grep
metadata:
  author: Lucas Gault
  version: "2.0"
---

# Tailwind CSS Styling (v4.1)

## Critical Rules

1. **NEVER use raw colors** — use semantic tokens (`bg-success`, not `bg-green-500`)
2. **NEVER use margins between siblings** — use `flex flex-col gap-*` on parent
3. **NEVER use deprecated v3 classes** — `shrink-0` not `flex-shrink-0`, `grow` not `flex-grow`
4. **Use project custom tokens** — `rounded-card` not `rounded-2xl`, `shadow-shadow-2` not `shadow-lg`

## Conformity Check (when modifying existing files)

When editing a `.vue` file, check for and fix:
- Deprecated classes: `flex-shrink-0` → `shrink-0`, `flex-grow` → `grow`
- Raw colors: `bg-green-500` → `bg-success`, `text-red-600` → `text-error`
- Margin spacing between siblings: `mb-4` on children → `gap-4` on parent
- Non-token radius: `rounded-2xl` → `rounded-card` (16px) if appropriate

## Semantic Colors

| Token | Purpose |
|---|---|
| `primary` | Brand (Sage) |
| `accent` | Emphasis (Rose) |
| `success` | Positive (Green) |
| `warning` | Caution (Orange) |
| `error` | Negative (Red) |
| `info` | Informational (Blue) |

Each has 5 variants: `{color}`, `{color}-content`, `{color}-light`, `{color}-light-content`, `{color}-stroke`.

## Figma → Template Quick Reference

When translating Figma mockups, drop `-base` and apply suffix mapping:
- Figma `font-X-Y` → `text-X-Y-font` (CSS adds `-font` suffix)
- Figma `bg-X-Y` → `bg-X-Y` (no suffix)
- Figma `stroke-X-Y` → `border-X-Y-stroke` (CSS adds `-stroke` suffix)
- Interactive variants (`hovered`, `pressed`) → add `hover:`/`active:` modifier

Example: Figma `font-primary-hovered` → `hover:text-primary-hovered-font`

## Custom Tokens

| Token | Class | Value |
|---|---|---|
| Radius | `rounded-card` | 16px |
| Radius | `rounded-block` | 24px |
| Shadow | `shadow-shadow-1` to `shadow-shadow-4` | Elevation levels |
| Shadow | `shadow-pink/green/blue/orange/red` | Colored shadows |
| Base | `bg-base-100/200/300` | Background layering |

## Documentation

- [css-guidelines.md](references/css-guidelines.md) - Full token reference, deprecated classes, responsive design, typography
