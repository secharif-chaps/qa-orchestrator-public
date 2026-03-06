---
name: frontend-accessibility
description: RGAA 4.1 / WCAG 2.1 AA accessibility standards for Vue/Nuxt frontend. Use when adding ARIA attributes (aria-label, aria-describedby, aria-invalid), implementing keyboard navigation with Tab/Escape handlers, ensuring 4.5:1 color contrast ratios, adding alt text to images, or creating accessible forms with proper labels and error associations. Activates when working on Vue components, modals with focus trapping, screen reader announcements, or page structure with landmarks (header, main, nav).
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When adding ARIA attributes (`aria-label`, `aria-describedby`, `aria-invalid`, `aria-modal`)
- When implementing keyboard navigation (Tab order, Escape key for modals)
- When ensuring minimum 4.5:1 color contrast for text
- When adding descriptive alt text to informative images
- When using `alt=""` with `role="presentation"` for decorative images
- When creating accessible forms with `<label for="id">` associations
- When implementing error messages with `aria-describedby` and `role="alert"`
- When building modals with focus trapping and focus restoration
- When adding screen reader announcements with `aria-live` regions
- When structuring pages with landmarks (`<header>`, `<main>`, `<nav>`, `<footer>`)
- When creating descriptive link text (not "click here")
- When ensuring all functionality is keyboard-accessible

# Frontend Accessibility

## Documentation

For detailed patterns, see:

- [Accessibility standards](references/accessibility.md) - Semantic HTML, keyboard navigation, color contrast, forms, modals, screen readers, page structure, testing checklist
