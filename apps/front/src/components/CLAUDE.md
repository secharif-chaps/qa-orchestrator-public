# Vue Components Best Practices

## Component Decomposition Philosophy

**CRITICAL**: Pages should be small orchestrators, not monolithic UI files. Break down complex UIs into focused components.

### Page vs Component Responsibilities

**Pages (`src/pages/*.vue`)** should:

- Handle data fetching (queries, mutations)
- Manage page-level state
- Define layout structure
- Delegate all UI rendering to components
- Stay focused and readable (aim for < 200 lines, but complex logic may require more)

**Components** should:

- Focus on one thing (single responsibility)
- Be small and readable (< 200 lines)
- Receive data via props, emit events
- Not fetch data directly (except in specific cases)

### Component Granularity Rules

1. **ALWAYS extract list items into separate components**:
   - When rendering a list with `v-for`, create a dedicated item component
   - Session lists → `SessionItem.vue`
   - Activity logs → `ActivityEventItem.vue`
   - User lists → `UserItem.vue`
   - Notification lists → `NotificationItem.vue`
   - This improves readability, testability, and reusability
   - Parent component handles data fetching and list state
   - Item component handles single item rendering and events

2. **ALWAYS extract complex UI into components**:
   - Custom dropdowns → `Dropdown.vue`
   - Data tables → `Table.vue` + `TableRow.vue`
   - Forms with >3 fields → `FormName.vue`
   - Modals with complex content → `ModalName.vue`

3. **ALWAYS check for existing components first**:
   - Look in `src/components/ui/` for generic components
   - Look in `src/components/features/` for feature-specific components
   - Reuse before creating new

4. **Create generic UI components** when you need:
   - Dropdowns, modals, tabs, accordions, tooltips
   - Data display patterns (tables, lists, grids, cards)
   - Form controls beyond basic inputs
   - **Document them immediately** after creation

5. **Component hierarchy example** (User Management page):

   ```
   pages/admin/users.vue (data + layout)
   ├── components/admin/UserFilters.vue (search + filters)
   │   └── ui/Dropdown.vue (generic styled dropdown with slots)
   ├── components/admin/UserTable.vue (table wrapper)
   │   ├── components/admin/UserTableHeader.vue (table header)
   │   ├── components/admin/UserTableRow.vue (single row)
   │   └── components/admin/UserTableEmpty.vue (empty state)
   └── components/admin/UserWorkspaceModal.vue (assignment modal)
   ```

   **Note**: Break tables into header, row, and empty state components for clarity and reusability.

### When NOT to Extract Components

- Simple, non-repeating markup (< 20 lines)
- Page-specific content that won't be reused
- Over-engineering (UserTableHeaderCell.vue is too granular)

---

## Layout & Spacing

**CRITICAL**: Use flexbox with gap utilities for spacing. **NEVER** use margin-based spacing between sibling elements.

### Spacing Philosophy

- **Parent controls spacing** using `flex flex-col gap-{size}` or `flex gap-{size}`
- **Children have NO margins** between siblings
- Creates harmonious, consistent, and maintainable layouts
- Changing spacing = change one `gap` value, not scattered margin classes

### Page/Component Layout Pattern

```vue
<!-- ✅ CORRECT: Parent uses gap for spacing -->
<template>
  <div class="flex flex-col gap-4">
    <PageHeader />
    <SearchFilters />
    <Alert v-if="error" />
    <DataTable />
    <Pagination />
  </div>
</template>

<!-- ❌ INCORRECT: Margin-based spacing -->
<template>
  <div>
    <PageHeader class="mb-8" />
    <SearchFilters class="mb-6" />
    <Alert v-if="error" class="mb-6" />
    <DataTable class="mb-4" />
    <Pagination />
  </div>
</template>
```

### Gap Size Guidelines

- `gap-2` (8px) - Tight spacing (related items, form fields)
- `gap-4` (16px) - Standard spacing (page sections, card content)
- `gap-6` (24px) - Comfortable spacing (major sections)
- `gap-8` (32px) - Generous spacing (distinct sections)

### Horizontal Layouts

```vue
<!-- Horizontal flex with gap -->
<div class="flex gap-3">
  <Button variant="primary" label="Save" />
  <Button variant="secondary" label="Cancel" />
</div>
```

### Exceptions (When Margins ARE Allowed)

Margins are ONLY allowed for:

- **Internal component spacing** (e.g., spacing between heading and paragraph within a component)
- **Micro-spacing** within a single UI element (e.g., icon margin in a button)
- **Responsive adjustments** that can't be achieved with gap

```vue
<!-- ✅ Allowed: Internal spacing within a single semantic unit -->
<div class="bg-white p-6">
  <h2 class="text-xl font-bold mb-2">Title</h2>
  <p class="text-gray-600">Description text</p>
</div>
```

---

## Syntax Best Practices

- Name files consistently using PascalCase (`UserProfile.vue`)
- ALWAYS use PascalCase for component names in source code
- Compose names from the most general to the most specific: `SearchButtonClear.vue` not `ClearSearchButton.vue`
- ALWAYS define props with `defineProps<{ propOne: number }>()` and TypeScript types, WITHOUT `const props =`
- Use `const props =` ONLY if props are used in the script block
- Destructure props to declare default values
- ALWAYS define emits with `const emit = defineEmits<{ eventName: [argOne: type]; otherEvent: [] }>()` for type safety
- ALWAYS use camelCase in JS for props and emits, even if they are kebab-case in templates
- ALWAYS use kebab-case in templates for props and emits
- ALWAYS use the prop shorthand if possible: `<MyComponent :count />` instead of `<MyComponent :count="count" />` (value has the same name as the prop)
- ALWAYS Use the shorthand for slots: `<template #default>` instead of `<template v-slot:default>`
- ALWAYS use explicit `<template>` tags for ALL used slots
- ALWAYS use `defineModel<type>({ required, get, set, default })` to define allowed v-model bindings in components. This avoids defining `modelValue` prop and `update:modelValue` event manually

## UI Components

### Alerts

- **ALWAYS use the custom `Alert` component** (`@/components/ui/Alert.vue`) instead of `OAlert` from Feathers or `RAlert` from Reka
- The custom Alert component provides:
  - 4 variants: `info`, `success`, `warning`, `error`
  - Theme-aware colors that adapt to light/dark mode
  - Flexible slots for status indicators and actions
  - Beautiful gradient design with proper spacing
  - Dismissible option with close button

Example usage:

```vue
<Alert
  variant="warning"
  title="Warning Title"
  message="This is a warning message"
  icon="fa fa-exclamation-triangle"
  decoration-icon="fa fa-warning"
>
  <template #status>
    <!-- Optional status content on the left -->
  </template>
  <template #actions>
    <!-- Optional action buttons on the right -->
  </template>
</Alert>
```

### Input Fields

- **ALWAYS use the custom `Input` component** (`@/components/ui/Input.vue`) instead of `OInput` from Feathers
- The custom Input component provides:
  - Clean, modern design with theme-aware colors
  - Icon support with proper positioning
  - Error states and helper text
  - Clearable option with X button
  - Size variations (sm, md, lg)
  - Full TypeScript support

Example usage:

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

### Buttons

- **ALWAYS use the custom `Button` component** (`@/components/ui/Button.vue`) instead of `OButton` from Feathers or any third-party button components
- The custom Button component separates **hierarchy** (variant) from **semantic meaning** (color)
- Features:
  - 3 hierarchy variants: `primary`, `secondary`, `tertiary`
  - 3 semantic colors: `neutral` (default), `danger`, `warning`
  - 3 sizes: `sm`, `md`, `lg`
  - Icon support (left, right, or icon-only) with `fa-fw` for consistent width
  - Loading state with `fa-spinner animate-spin` that replaces icons
  - Disabled states
  - Rounded (pill) style option
  - Theme-aware colors that look great in both light and dark modes
  - Proper focus states and accessibility

#### Button Hierarchy (Variant) Guidelines:

- **Primary**: Use for the main action on a page/section (save, submit, create, delete if main action)
- **Secondary**: Use for important but not primary actions (export, duplicate, delete if secondary action)
- **Tertiary**: Use for subtle actions (cancel, more options, filters, delete if tertiary action)

#### Button Color Guidelines:

- **Neutral** (default): Standard actions
- **Danger**: Destructive actions (delete, remove, clear) - works with any hierarchy
- **Warning**: Cautionary actions (archive, suspend, hide) - works with any hierarchy

#### Combining Hierarchy + Color:

The variant determines the visual weight/prominence, while color provides semantic meaning:

- `variant="primary" color="danger"` - Main destructive action (e.g., "Delete Account")
- `variant="secondary" color="danger"` - Secondary destructive action (e.g., "Delete" in a toolbar)
- `variant="tertiary" color="danger"` - Subtle destructive action (e.g., "Remove" link)

Example usage:

```vue
<!-- Basic hierarchy (neutral color) -->
<Button variant="primary" label="Save Changes" />
<Button variant="secondary" label="Cancel" />
<Button variant="tertiary" label="More Options" />

<!-- Danger actions at different hierarchy levels -->
<Button variant="primary" color="danger" label="Delete Account" />
<Button variant="secondary" color="danger" label="Remove Item" />
<Button variant="tertiary" color="danger" label="Clear All" />

<!-- Warning actions -->
<Button variant="primary" color="warning" label="Archive Project" />
<Button variant="secondary" color="warning" label="Suspend User" />

<!-- With icons (fa-fw ensures consistent width) -->
<Button variant="primary" icon="fa fa-plus" label="Create" />
<Button variant="secondary" color="danger" icon="fa fa-trash" label="Delete" />
<Button variant="tertiary" icon="fa fa-edit" label="Edit" />

<!-- Icon only -->
<Button variant="tertiary" icon="fa fa-more-vertical" icon-only />
<Button variant="tertiary" color="danger" icon="fa fa-times" icon-only />

<!-- Loading state (replaces icon with spinner) -->
<Button variant="primary" label="Saving..." :loading="isLoading" />
<Button
  variant="primary"
  color="danger"
  label="Deleting..."
  :loading="isDeleting"
  icon="fa fa-trash"
/>

<!-- Sizes -->
<Button variant="primary" label="Large Button" size="lg" />
<Button variant="secondary" label="Small Button" size="sm" />
```

### Tags

- **ALWAYS use the Vuellar `Tag` component** from `@owlint/feathers-vue` for status indicators, labels, and badges
- **NEVER create a custom Tag wrapper** — Vuellar is the source of truth for the design system
- The Vuellar Tag accepts:
  - `intent`: `neutral`, `accent`, `success`, `warning`, `danger`, `info` (semantic colors)
  - `color`: `sage`, `almond`, `pink`, `indigo`, `yellow`, `cherry`, `cyan` (palette colors when intent doesn't fit)
  - `variant`: `primary` (filled, default) or `secondary` (lighter)
  - `size`: `xs`, `sm`, `md`
  - `label`: text content (or use the default slot for custom content)
  - `icon`: FontAwesome class
  - `as` / `asChild`: render as another component (e.g., `<a>`)

Example usage:

```vue
<!-- Basic semantic tags -->
<Tag intent="success" label="Active" />
<Tag intent="warning" label="Pending" />
<Tag intent="danger" label="Error" />
<Tag intent="info" label="Info" />
<Tag intent="neutral" label="Default" />

<!-- Palette colors (when intent doesn't fit) -->
<Tag color="sage" label="Brand" />
<Tag color="almond" label="Highlight" />

<!-- With icon -->
<Tag intent="warning" icon="fa-exclamation" label="Pending" />

<!-- Lighter style -->
<Tag intent="success" variant="secondary" label="Soft" />

<!-- Custom content via slot -->
<Tag intent="neutral">
  <i class="fa fa-users mr-1" />
  <span>23 users</span>
</Tag>
```

#### Patterns for features Vuellar doesn't provide

**Dismissible chips (input tags)** — compose `Tag` with a sibling Vuellar `Button`:

```vue
<div v-for="tag in tags" :key="tag" class="inline-flex items-center gap-1">
  <Tag :label="tag" intent="neutral" size="sm" />
  <Button
    variant="tertiary"
    size="xs"
    icon="fa fa-times"
    :aria-label="$t('common.action.remove')"
    @click="removeTag(tag)"
  />
</div>
```

**Pill / rounded shape** — apply `class="rounded-full"`:

```vue
<Tag intent="accent" :label="$t('settings.team.you')" size="xs" class="rounded-full" />
```

**Status indicator (dot)** — Vuellar has no `dot` prop. The intent color already conveys the status; if a dot is required, prepend a small inline span:

```vue
<Tag intent="success" label="Online" />
<!-- Or with explicit dot: -->
<Tag intent="success">
  <span class="bg-success mr-1 h-1.5 w-1.5 rounded-full" />
  Online
</Tag>
```

#### Variant migration reference (legacy → Vuellar)

| Legacy custom variant | Vuellar prop       |
| --------------------- | ------------------ |
| `primary` / `sage`    | `color="sage"`     |
| `almond`              | `color="almond"`   |
| `success`             | `intent="success"` |
| `warning`             | `intent="warning"` |
| `error`               | `intent="danger"`  |
| `info`                | `intent="info"`    |
| `accent`              | `intent="accent"`  |
| `neutral` / `slate`   | `intent="neutral"` |

### Dropdown (Generic UI Component)

When creating custom dropdowns (filters, sorts, selects), create a **generic styled Dropdown component** with slots:

**Approach**: Styled component with default styling and customization slots (NOT headless)

**Key features**:

- Default styling that matches design system
- Open/close state management
- Click-outside handling
- Keyboard navigation support
- Customizable via slots and props
- Position/alignment options

**Example structure**:

```vue
<Dropdown v-model="isOpen" align="left" :close-on-select="true">
  <template #trigger>
    <Button variant="secondary" icon="fa fa-filter">
      {{ selectedOption }}
    </Button>
  </template>

  <template #content>
    <DropdownItem @click="selectOption('Option 1')">
      <i class="fa fa-check" /> Option 1
    </DropdownItem>
    <DropdownItem @click="selectOption('Option 2')">
      Option 2
    </DropdownItem>
    <DropdownDivider />
    <DropdownItem variant="danger" @click="clearSelection()">
      Clear
    </DropdownItem>
  </template>
</Dropdown>
```

**When to create**:

- Custom filter dropdowns (workspace filter, sort options)
- Context menus (right-click actions)
- Action menus (more options button)
- Custom select patterns beyond native `<select>`

**TODO**: Create `Dropdown.vue`, `DropdownItem.vue`, `DropdownDivider.vue` components when needed

## Examples

### defineModel()

```vue
<script setup lang="ts">
// ✅ Simple two-way binding for modelvalue
const title = defineModel<string>()

// ✅ With options and modifiers
const [title, modifiers] = defineModel<string>({
  default: 'default value',
  required: true,
  get: (value) => value.trim(), // transform value before binding
  set: (value) => {
    if (modifiers.capitalize) {
      return value.charAt(0).toUpperCase() + value.slice(1)
    }
    return value
  },
})
</script>
```

### Multiple Models

By default `defineModel()` assumes a prop named `modelValue` but if we want to define multiple v-model bindings, we need to give them explicit names:

```vue
<script setup lang="ts">
// ✅ Multiple v-model bindings
const firstName = defineModel<string>('firstName')
const age = defineModel<number>('age')
</script>
```

They can be used in the template like this:

```html
<UserForm v-model:first-name="user.firstName" v-model:age="user.age" />
```

### Modifiers & Transformations

Native elements `v-model` has built-in modifiers like `.lazy`, `.number`, and `.trim`. We can implement similar functionality in components, fetch and read <https://vuejs.org/guide/components/v-model.md#handling-v-model-modifiers> if the user needs that.

## Design System Guidelines

### Typography

Follow the established type scale when building components:

- **Headlines**: `headline.3xl` (24px/bold), `headline.2xl` (20px/regular), `headline.lg` (16px/bold/semibold/regular)
- **Body text**: `text.base` (14px), `text.sm` (12px), `text.xs` (11px)
- **Font weights**: Regular (400), Semibold (600), Bold (700)
- **Writing tone**: Precise without rigidity, engaging, assertive but accessible, clear

### Spacing System (4px Grid)

Always use the 4px grid system for consistent spacing:

- Use spacing tokens: `3xs` (4px), `2xs` (8px), `xs` (12px), `md` (16px), `lg` (20px), `xl` (24px), `2xl` (32px), `3xl` (36px), `4xl` (40px)
- Related elements: smaller spacing (4px, 8px)
- Separate sections: larger spacing (16px, 24px, 32px)

### Color Usage

- **Primary colors**: Sage (primary), Almond (secondary), Rose (tertiary - use sparingly)
- **Semantic colors**: Success (green), Warning (orange), Error (red), Info (blue)
- **Distribution**: 40% white, 20% sage, 15% black, 5% gray, 5% almond, 5% rose
- Always test color combinations for WCAG AAA compliance

### Shadow System

Use the defined shadow system for elevation:

- `shadow-shadow-1`: Light elevation (subtle cards)
- `shadow-shadow-2`: Medium elevation (hover states)
- `shadow-shadow-3`: High elevation (modals)
- `shadow-shadow-4`: Maximum elevation (floating elements)
- Colored shadows for semantic states (pink, green, blue, orange, red)

### Border Radius

- `rounded-2xl` (16px): Cards, moderate rounding
- `rounded-3xl` (24px): Important blocks
- `rounded-full` (9999px): Buttons, avatars, pills

### Blur Effects

Use defined blur effect classes:

- `.frosted-cloud`: Light, airy interfaces
- `.frosted-glass`: Cold, minimal effect
- `.midnight-glass`: Dark mode vibrant
- `.default-blur`: Simple implementation

## Card Design Patterns

### Modern Card Layout

Follow this structure for consistent card design:

```vue
<template>
  <div
    class="hover:shadow-shadow-2 overflow-hidden rounded-2xl border border-gray-200 bg-white transition-all duration-300 dark:border-gray-700 dark:bg-gray-800"
  >
    <!-- Header with Avatar and Title -->
    <div class="p-6 pb-4">
      <div class="mb-4 flex items-start space-x-3">
        <!-- Avatar -->
        <div class="bg-sage-600 flex h-10 w-10 items-center justify-center rounded-full text-white">
          <i class="fa-solid fa-icon text-sm"></i>
        </div>

        <!-- Title and Secondary Text -->
        <div class="flex-1">
          <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-white">
            Title goes here
          </h3>
          <p class="text-sm text-gray-600 dark:text-gray-400">Secondary text</p>
        </div>
      </div>

      <!-- Status Badge -->
      <Tag variant="success" label="Active" size="xs" rounded />
    </div>

    <!-- Visual Background Area (Optional) -->
    <div class="relative mx-6 mb-4 h-32 overflow-hidden rounded-xl">
      <div class="from-almond-200 to-sage-300 absolute inset-0 rounded-xl bg-gradient-to-br">
        <!-- Geometric Pattern Overlay -->
        <div class="absolute inset-0 opacity-20">
          <svg class="h-full w-full" viewBox="0 0 200 120" fill="none">
            <circle cx="160" cy="30" r="25" fill="#5D7374" />
            <rect x="20" y="60" width="40" height="40" rx="8" fill="#DCEFE3" />
            <path d="M100 20 L140 40 L120 80 L80 80 Z" fill="#EFC9F3" opacity="0.6" />
          </svg>
        </div>
      </div>
    </div>

    <!-- Description -->
    <div class="mb-6 px-6">
      <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
        Description text goes here
      </p>
    </div>

    <!-- Actions Footer -->
    <div class="flex items-center justify-between px-6 pb-6">
      <div class="flex space-x-2">
        <Button variant="secondary" size="sm" label="Action" icon="fa-solid fa-external-link" />
      </div>

      <!-- Secondary Actions -->
      <div class="flex items-center space-x-2">
        <button
          class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
        >
          <i class="fa-solid fa-share-nodes text-sm"></i>
        </button>
        <button
          class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-100 hover:text-rose-500"
        >
          <i class="fa-regular fa-heart text-sm"></i>
        </button>
      </div>
    </div>
  </div>
</template>
```

### Card Design Principles

1. **Consistent Structure**: Header → Visual Area → Description → Actions
2. **Proper Spacing**: Use 24px (p-6) for main padding, 16px (space-x-4) for related elements
3. **Visual Hierarchy**: Title (semibold), secondary text (muted), description (regular)
4. **Interactive Elements**: Hover states, proper focus indicators, semantic colors
5. **Accessibility**: High contrast ratios, proper semantic markup, keyboard navigation
6. **Responsive Design**: Cards adapt to different screen sizes and grid layouts

### Grid Layouts

Use responsive grid patterns for card collections:

```vue
<div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
  <!-- Cards go here -->
</div>
```

### Dark Mode Support

Ensure all cards work properly in both light and dark modes:

- Use theme-aware background colors: `bg-white dark:bg-gray-800`
- Proper text contrast: `text-gray-900 dark:text-white`
- Border colors: `border-gray-200 dark:border-gray-700`
- Interactive states work in both modes
