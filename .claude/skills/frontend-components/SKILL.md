---
name: frontend-components
description: Vue component standards with Vuellar UI library (@owlint/feathers-vue) priority. Use when selecting between Vuellar components (Button, Input, Select, Tag, Badge, Modal, Table, Alert), following component selection rules (Link vs Button, Checkbox vs Switch, Tag vs Chips), or using standardized props (variant, intent, color, size). Activates when creating `.vue` files in pwa/components/, deciding between creating custom components vs using Vuellar primitives, or implementing forms with validation patterns.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When deciding to use Vuellar components before creating custom ones
- When selecting between similar Vuellar components (Link vs Button, Checkbox vs Switch)
- When using `variant` prop (`primary`, `secondary`, `tertiary`)
- When using `intent` prop for semantic colors (`neutral`, `success`, `warning`, `danger`)
- When using `color` prop for decorative colors (`sage`, `pink`, `indigo`)
- When creating Vue components with `<script setup lang="ts">`
- When defining typed props with interface and destructured defaults
- When using `defineEmits<{}>()` TypeScript syntax
- When implementing forms with Input, Button, Alert from Vuellar
- When building data tables with Table component and slot templates
- When creating modals with Modal component and footer slots
- When using PascalCase for component files (`UserProfile.vue`)

# Frontend Components

## Documentation

For detailed patterns, see:

- [Component standards](references/components.md) - Vuellar component library, selection matrix, standardized props, Vue component structure, common patterns
