# Vue Components Best Practices

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

### Badges

- **ALWAYS use the custom `Badge` component** (`@/components/ui/Badge.vue`) for status indicators, labels, and tags
- **NEVER use third-party badge components** from Feathers, Reka, or other UI libraries
- The custom Badge component provides:
  - 6 variants: `primary`, `success`, `warning`, `error`, `info`, `slate`
  - 4 sizes: `xs`, `sm`, `md`, `lg`
  - Optional icon or status dot
  - Subtle gradient or flat solid styles
  - Rounded (pill) style option
  - Dismissible option
  - Theme-aware colors with subtle pastel tones that work beautifully in light/dark mode

#### Common Use Cases:

- **Status indicators**: Module status, online/offline states, task progress
- **Token counts**: Display remaining tokens with appropriate color coding
- **User roles**: Admin, user, viewer badges
- **Feature flags**: Beta, new, coming soon indicators
- **Filters/Tags**: Dismissible filter badges in search interfaces

Example usage:

```vue
<!-- Basic badge -->
<Tag variant="success" label="Active" />

<!-- With icon -->
<Tag variant="warning" icon="fa fa-exclamation" label="Pending" />

<!-- With status dot -->
<Tag variant="success" dot label="Online" />

<!-- Rounded/pill style -->
<Tag variant="primary" label="Admin" rounded />

<!-- Without gradient (flat) -->
<Tag variant="info" label="New" :gradient="false" />

<!-- Dismissible -->
<Tag variant="error" label="Error" dismissible @dismiss="handleDismiss" />

<!-- Custom content -->
<Tag variant="slate">
  <i class="fa fa-users mr-1"></i>
  <span>23 users</span>
</Badge>

<!-- Token count examples -->
<Tag variant="error" icon="fa fa-coins" label="0 tokens" />
<Tag variant="warning" icon="fa fa-coins" label="5 tokens" />
<Tag variant="success" icon="fa fa-coins" label="100 tokens" />

<!-- Module status examples -->
<Tag variant="success" dot label="Screen Module" />
<Tag variant="slate" dot label="Stream Module" />
```

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
    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 hover:shadow-shadow-2 transition-all duration-300 overflow-hidden"
  >
    <!-- Header with Avatar and Title -->
    <div class="p-6 pb-4">
      <div class="flex items-start space-x-3 mb-4">
        <!-- Avatar -->
        <div class="w-10 h-10 rounded-full bg-sage-600 text-white flex items-center justify-center">
          <i class="fa-solid fa-icon text-sm"></i>
        </div>

        <!-- Title and Secondary Text -->
        <div class="flex-1">
          <h3 class="font-semibold text-base text-gray-900 dark:text-white mb-1">
            Title goes here
          </h3>
          <p class="text-sm text-gray-600 dark:text-gray-400">Secondary text</p>
        </div>
      </div>

      <!-- Status Badge -->
      <Tag variant="success" label="Active" size="xs" rounded />
    </div>

    <!-- Visual Background Area (Optional) -->
    <div class="relative h-32 mx-6 mb-4 rounded-xl overflow-hidden">
      <div class="absolute inset-0 rounded-xl bg-gradient-to-br from-almond-200 to-sage-300">
        <!-- Geometric Pattern Overlay -->
        <div class="absolute inset-0 opacity-20">
          <svg class="w-full h-full" viewBox="0 0 200 120" fill="none">
            <circle cx="160" cy="30" r="25" fill="#5D7374" />
            <rect x="20" y="60" width="40" height="40" rx="8" fill="#DCEFE3" />
            <path d="M100 20 L140 40 L120 80 L80 80 Z" fill="#EFC9F3" opacity="0.6" />
          </svg>
        </div>
      </div>
    </div>

    <!-- Description -->
    <div class="px-6 mb-6">
      <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
        Description text goes here
      </p>
    </div>

    <!-- Actions Footer -->
    <div class="px-6 pb-6 flex items-center justify-between">
      <div class="flex space-x-2">
        <Button variant="secondary" size="sm" label="Action" icon="fa-solid fa-external-link" />
      </div>

      <!-- Secondary Actions -->
      <div class="flex items-center space-x-2">
        <button
          class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
        >
          <i class="fa-solid fa-share-nodes text-sm"></i>
        </button>
        <button
          class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:text-rose-500 hover:bg-gray-100 transition-colors"
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
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
  <!-- Cards go here -->
</div>
```

### Dark Mode Support

Ensure all cards work properly in both light and dark modes:

- Use theme-aware background colors: `bg-white dark:bg-gray-800`
- Proper text contrast: `text-gray-900 dark:text-white`
- Border colors: `border-gray-200 dark:border-gray-700`
- Interactive states work in both modes
