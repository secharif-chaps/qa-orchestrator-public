# Vue Components Best Practices

## Component Decomposition Philosophy

**CRITICAL**: Pages should be small orchestrators, not monolithic UI files. Break down complex UIs into focused components.

### Page vs Component Responsibilities

**Pages (`src/pages/*.vue`)** should:

- Handle data fetching (queries, mutations)
- Manage page-level state
- Define layout structure
- Delegate all UI rendering to components
- Stay focused and readable (aim for < 200 lines)

**Components** should:

- Focus on one thing (single responsibility)
- Be small and readable (< 200 lines)
- Receive data via props, emit events
- Not fetch data directly (except in specific cases)

### Component Granularity Rules

1. **ALWAYS extract list items into separate components** — when rendering a list with `v-for`, create a dedicated item component (`SessionItem.vue`, `UserItem.vue`, etc.)

2. **ALWAYS extract complex UI into components**:
   - Custom dropdowns → `Dropdown.vue`
   - Data tables → `Table.vue` + `TableRow.vue`
   - Forms with >3 fields → `FormName.vue`
   - Modals with complex content → `ModalName.vue`

3. **ALWAYS check for existing components first**:
   - `src/components/ui/` for generic components
   - `src/components/features/` for feature-specific components

4. **Component hierarchy example** (User Management page):
   ```
   pages/admin/users.vue (data + layout)
   ├── components/admin/UserFilters.vue
   ├── components/admin/UserTable.vue
   │   ├── components/admin/UserTableHeader.vue
   │   ├── components/admin/UserTableRow.vue
   │   └── components/admin/UserTableEmpty.vue
   └── components/admin/UserWorkspaceModal.vue
   ```

### When NOT to Extract Components

- Simple, non-repeating markup (< 20 lines)
- Page-specific content that won't be reused
- Over-engineering (UserTableHeaderCell.vue is too granular)

---

## Syntax Best Practices

- Name files consistently using PascalCase (`UserProfile.vue`)
- Compose names from most general to most specific: `SearchButtonClear.vue` not `ClearSearchButton.vue`
- ALWAYS define props with `defineProps<{ propOne: number }>()` and TypeScript types, WITHOUT `const props =`
- Use `const props =` ONLY if props are used in the script block
- Destructure props to declare default values
- ALWAYS define emits with `const emit = defineEmits<{ eventName: [argOne: type]; otherEvent: [] }>()` for type safety
- ALWAYS use camelCase in JS for props and emits, even if kebab-case in templates
- ALWAYS use the prop shorthand if possible: `<MyComponent :count />` instead of `<MyComponent :count="count" />`
- ALWAYS use the shorthand for slots: `<template #default>` instead of `<template v-slot:default>`
- ALWAYS use `defineModel<type>()` to define v-model bindings (avoids manual `modelValue` prop + `update:modelValue` event)

---

## defineModel Examples

```vue
<script setup lang="ts">
// Simple two-way binding
const title = defineModel<string>()

// With options
const [title, modifiers] = defineModel<string>({
  default: 'default value',
  required: true,
  get: (value) => value.trim(),
  set: (value) => {
    if (modifiers.capitalize) {
      return value.charAt(0).toUpperCase() + value.slice(1)
    }
    return value
  },
})

// Multiple v-model bindings
const firstName = defineModel<string>('firstName')
const age = defineModel<number>('age')
</script>
```

Used in template as:

```html
<UserForm v-model:first-name="user.firstName" v-model:age="user.age" />
```

> For styling, spacing, and color system: see `tailwind-styling` skill.
> For UI components (Alert, Button, Badge, Input, Card): see `frontend-ui-components` skill.
