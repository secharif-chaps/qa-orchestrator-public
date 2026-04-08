---
name: frontend-accessibility
description: RGAA 4.1 / WCAG 2.1 AA accessibility standards. CRITICAL - Activates when creating OR modifying any .vue file that has interactive elements, forms, images, modals, or page structure. When modifying existing components, verify ARIA attributes, keyboard navigation, label associations, and alt text. Fix any violations found.
allowed-tools: Read, Write, Edit, Glob, Grep
metadata:
  author: Lucas Gault
  version: "2.0"
---

# Frontend Accessibility

## Conformity Check (when modifying existing components)

When editing a `.vue` file with interactive elements, verify:
- Forms have `<label for="id">` associations (not floating labels without association)
- Images have descriptive `alt` text (or `alt=""` + `role="presentation"` for decorative)
- Interactive elements are keyboard-accessible (Tab, Enter, Escape)
- Error messages use `aria-describedby` and `role="alert"`
- Modals have focus trapping and restore focus on close
- Links have descriptive text (not "click here")

If any violation is found, **fix it as part of your change**.

## Documentation

For detailed patterns, see:

- [Accessibility standards](references/accessibility.md) - Semantic HTML, keyboard navigation, color contrast, forms, modals, screen readers, page structure, testing checklist
