---
name: frontend-ui-components
description: Custom UI components for ChapsMind frontend — Alert, Input, Button, Badge/Tag, Dropdown, Card. CRITICAL - Activates when creating OR modifying any .vue file that uses buttons, alerts, inputs, badges, or cards. Use these project-specific components, never third-party alternatives (OButton, OAlert, OInput from Feathers or Reka).
allowed-tools: Read, Write, Edit, Glob, Grep
metadata:
  author: chaps-e
  version: "1.0"
---

# ChapsMind Custom UI Components

These are project-specific custom components in `@/components/ui/`. Never use Feathers (`OButton`, `OAlert`, `OInput`) or Reka alternatives.

---

## Alert

Always use `@/components/ui/Alert.vue`. Variants: `info`, `success`, `warning`, `error`.

```vue
<Alert
  variant="warning"
  title="Warning Title"
  message="This is a warning message"
  icon="fa fa-exclamation-triangle"
>
  <template #status><!-- Optional status content left --></template>
  <template #actions><!-- Optional action buttons right --></template>
</Alert>
```

---

## Input

Always use `@/components/ui/Input.vue`. Sizes: `sm`, `md`, `lg`.

```vue
<Input
  v-model="value"
  label="Field Label"
  placeholder="Enter text..."
  icon="fa fa-search"
  :error="errorMessage"
  clearable
  required
/>
```

---

## Button

Always use `@/components/ui/Button.vue`. Two orthogonal axes: **variant** (visual weight) + **color** (semantic meaning).

**Variants** (hierarchy): `primary` · `secondary` · `tertiary`
**Colors** (meaning): `neutral` (default) · `danger` · `warning`
**Sizes**: `sm` · `md` · `lg`

```vue
<!-- Hierarchy -->
<Button variant="primary" label="Save Changes" />
<Button variant="secondary" label="Cancel" />
<Button variant="tertiary" label="More Options" />

<!-- Danger at different hierarchy levels -->
<Button variant="primary" color="danger" label="Delete Account" />
<Button variant="secondary" color="danger" label="Remove Item" />
<Button variant="tertiary" color="danger" label="Clear All" />

<!-- With icons (fa-fw for consistent width) -->
<Button variant="primary" icon="fa fa-plus" label="Create" />
<Button variant="tertiary" icon="fa fa-edit" icon-only />

<!-- Loading state -->
<Button variant="primary" label="Saving..." :loading="isLoading" />
```

**Guidelines**:
- `primary` → main action on a page (save, submit, create)
- `secondary` → important but not primary (export, duplicate)
- `tertiary` → subtle actions (cancel, more options, filters)
- `danger` → any destructive action regardless of hierarchy
- `warning` → cautionary actions (archive, suspend)

---

## Badge / Tag

File: `@/components/ui/Badge.vue` — always use as **`<Tag>`** in templates. Variants: `primary`, `success`, `warning`, `error`, `info`, `slate`. Sizes: `xs`, `sm`, `md`, `lg`.

```vue
<Tag variant="success" label="Active" />
<Tag variant="warning" icon="fa fa-exclamation" label="Pending" />
<Tag variant="success" dot label="Online" />
<Tag variant="primary" label="Admin" rounded />
<Tag variant="error" label="Error" dismissible @dismiss="handleDismiss" />

<!-- Custom content -->
<Tag variant="slate">
  <i class="fa fa-users mr-1"></i>
  <span>23 users</span>
</Tag>
```

---

## Dropdown

For custom dropdowns (filters, sorts, context menus), use a **Dropdown** component with slots — styled, not headless.

```vue
<Dropdown v-model="isOpen" align="left" :close-on-select="true">
  <template #trigger>
    <Button variant="secondary" icon="fa fa-filter">{{ selectedOption }}</Button>
  </template>
  <template #content>
    <DropdownItem @click="selectOption('Option 1')">Option 1</DropdownItem>
    <DropdownItem @click="selectOption('Option 2')">Option 2</DropdownItem>
    <DropdownDivider />
    <DropdownItem variant="danger" @click="clearSelection()">Clear</DropdownItem>
  </template>
</Dropdown>
```

Key features: open/close state, click-outside handling, keyboard navigation, position/alignment options.

---

## Card Design Pattern

```vue
<template>
  <div class="hover:shadow-shadow-2 overflow-hidden rounded-2xl border border-gray-200 bg-white transition-all duration-300 dark:border-gray-700 dark:bg-gray-800">
    <!-- Header: Avatar + Title -->
    <div class="p-6 pb-4">
      <div class="mb-4 flex items-start space-x-3">
        <Badge variant="secondary" color="sage" icon="fa-solid fa-icon" />
        <div class="flex-1">
          <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-white">Title</h3>
          <p class="text-sm text-gray-600 dark:text-gray-400">Secondary text</p>
        </div>
      </div>
      <Tag variant="success" label="Active" size="xs" rounded />
    </div>

    <!-- Description -->
    <div class="mb-6 px-6">
      <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">Description</p>
    </div>

    <!-- Actions Footer -->
    <div class="flex items-center justify-between px-6 pb-6">
      <Button variant="secondary" size="sm" label="Action" icon="fa-solid fa-external-link" />
    </div>
  </div>
</template>
```

**Card principles**: Header → Description → Actions. Padding `p-6`. Dark mode: `bg-white dark:bg-gray-800`, `text-gray-900 dark:text-white`, `border-gray-200 dark:border-gray-700`.

**Grid for card collections**:
```vue
<div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
  <!-- Cards -->
</div>
```

---

## Shadow System

- `shadow-shadow-1` — light elevation (subtle cards)
- `shadow-shadow-2` — medium elevation (hover states)
- `shadow-shadow-3` — high elevation (modals)
- `shadow-shadow-4` — maximum elevation (floating)
