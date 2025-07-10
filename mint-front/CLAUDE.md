# Claude Development Instructions for Mint-Front

## 🤖 AI Assistant Rules

When working on the mint-front project, you MUST follow these rules:

### Package Management
- **ALWAYS use `yarn`** - never use npm commands
- When adding dependencies: `yarn add package-name`
- When adding dev dependencies: `yarn add -D package-name`

### Testing Requirements
- **ALL tests must pass** before considering any task complete
- Run `yarn test:all` to validate all test suites
- Current test counts that must be maintained or improved:
  - Unit tests: 41/41 passing
  - E2E tests: 26/26 passing  
  - UI tests: 23/23 passing

### Code Standards
- **NO comments** in code unless explicitly requested by user
- Follow existing code patterns and conventions
- Use TypeScript strictly
- Prefer editing existing files over creating new ones
- Use Nuxt auto-imports (don't manually import composables)
- Always use composition API for composables, pinia store and vue components and pages.
- Translate every test appearing in a page or template with i18n

## 🔥 Vue 3 & Nuxt Latest Best Practices

### Vue 3 Composition API Rules
- **ALWAYS use `<script setup>`** - Never use Options API or regular script
- **Use `defineProps()` and `defineEmits()`** with TypeScript interfaces
- **Use `defineModel()` for v-model** - Replace props + emit patterns
- **Use reactive props destructuring** with `toRefs()` for reactivity preservation
- **Use `ref()` for primitive values** and `reactive()` for objects
- **Use `computed()` for derived state** - never mutate computed values
- **Use `watch()` and `watchEffect()`** appropriately for side effects
- **Use `onMounted()`, `onUnmounted()`** etc. for lifecycle hooks

### TypeScript Integration & Modern Patterns
```vue
<script setup lang="ts">
// ✅ GOOD: Always use script setup
interface Props {
  title: string
  count?: number
  user: { name: string; age: number }
}

// ✅ GOOD: Use defineModel for v-model components
const modelValue = defineModel<string>()

// ✅ GOOD: Props with TypeScript with desctructuring thanks to vue 3.4
const {title, count = 0, user} = defineProps<Props>()

// ✅ GOOD: Computed based on reactive props
const displayTitle = computed(() => `${title.value} (${count.value})`)

// ✅ GOOD: Watch reactive props
watch(user, (newUser) => {
  console.log('User changed:', newUser.name)
}, { deep: true })
</script>

<template>
  <!-- ✅ GOOD: Use v-model with defineModel -->
  <input v-model="modelValue" />
  
  <!-- ✅ GOOD: Use reactive refs in template -->
  <h1>{{ displayTitle }}</h1>
  <p>{{ user.name }} is {{ user.age }} years old</p>
</template>
```

### Reactivity Best Practices
- **Use `toRefs()`** when destructuring reactive objects
- **Use `unref()`** or `.value` to access ref values in functions
- **Use `shallowRef()`** for large objects that don't need deep reactivity
- **Use `readonly()`** to prevent mutations of reactive data
- **Use `nextTick()`** when you need to wait for DOM updates

### Template Best Practices
- **ALWAYS use `defineModel()` for v-model** - Never use manual props + emit pattern
- **Use `v-memo`** for expensive list rendering optimization
- **Use `v-once`** for static content that never changes
- **Prefer `v-show` vs `v-if`** based on toggle frequency
- **Use `key` attribute` properly in `v-for` loops
- **Access reactive props as `.value`** in script but direct in template

### v-model Best Practices
```vue
<script setup lang="ts">
// ✅ GOOD: Simple v-model
const value = defineModel<string>()

// ✅ GOOD: Named v-model
const title = defineModel<string>('title')
const count = defineModel<number>('count')

// ✅ GOOD: v-model with options
const checked = defineModel<boolean>('checked', {
  default: false
})
</script>

<template>
  <!-- ✅ GOOD: Use v-model directly -->
  <input v-model="value" />
  <input v-model="title" />
  <input v-model.number="count" />
  <input v-model="checked" type="checkbox" />
</template>
```

### Nuxt 3 Specific Rules
- **Use `~/` or `@/` aliases** for imports
- **Leverage auto-imports** - don't manually import Vue functions, components and composables
- **Use `useFetch()`, `$fetch()`, `useLazyFetch()`** for data fetching
- **Use `useState()` for cross-component reactive state**
- **Use `useRoute()` and `useRouter()`** for navigation
- **Use `useHead()` and `useSeoMeta()`** for SEO
- **Use `useRuntimeConfig()`** for environment variables

### Composables Best Practices
```typescript
// Good composable pattern
export const useCounter = () => {
  const count = ref(0)
  
  const increment = () => count.value++
  const decrement = () => count.value--
  
  const isEven = computed(() => count.value % 2 === 0)
  
  return {
    count: readonly(count),
    increment,
    decrement,
    isEven
  }
}
```

### 🚨 MANDATORY: Script Setup Only
```vue
<!-- ✅ ALWAYS USE THIS -->
<script setup lang="ts">
// 1. defineModel() for v-model
const modelValue = defineModel<string>()

// 2. Props & destructuring with toRefs()
interface Props {
  title: string
  items: Array<{ id: number; name: string }>
}
const props = defineProps<Props>()
const { title, items } = toRefs(props)

// 3. Reactive state
const count = ref(0)
const form = reactive({ name: '', email: '' })

// 4. Computed properties
const itemCount = computed(() => items.value.length)

// 5. Methods/functions
const increment = () => count.value++

// 6. Lifecycle hooks
onMounted(() => {
  console.log('Component mounted')
})

// 7. Watchers
watch(count, (newCount) => {
  console.log('Count changed:', newCount)
})
</script>

<template>
  <!-- ✅ GOOD: Access reactive refs directly -->
  <h1>{{ title }}</h1>
  <p>Items: {{ itemCount }}</p>
  <button @click="increment">Count: {{ count }}</button>
  
  <!-- ✅ GOOD: v-model with defineModel -->
  <input v-model="modelValue" />
</template>

<style scoped>
/* Component-specific styles */
</style>
```

### ❌ NEVER USE These Patterns
```vue
<!-- ❌ FORBIDDEN: Options API -->
<script lang="ts">
export default {
  data() {
    return { count: 0 }
  }
}
</script>

<!-- ❌ FORBIDDEN: Regular script with setup() -->
<script lang="ts">
export default {
  setup() {
    // Don't use this pattern
  }
}
</script>
```

### Pinia Store Best Practices
```typescript
export const useUserStore = defineStore('user', () => {
  // State
  const user = ref<User | null>(null)
  
  // Getters (computed)
  const isAuthenticated = computed(() => !!user.value)
  
  // Actions
  const login = async (credentials: LoginCredentials) => {
    // async logic
  }
  
  return {
    user: readonly(user),
    isAuthenticated,
    login
  }
})
```

### Performance Optimization
- **Use `defineAsyncComponent()`** for code splitting
- **Use `Suspense`** with async components
- **Use `KeepAlive`** for expensive components
- **Use `v-memo`** for expensive list items
- **Avoid reactive objects in templates** - use computed instead
- **Use `shallowRef()` and `shallowReactive()`** when appropriate

### Error Handling
- **Use `onErrorCaptured()`** in components for error boundaries
- **Use `try/catch`** in async composables
- **Use Nuxt error handling** with `throw createError()`
- **Always handle promise rejections**

### Testing Considerations
- **Test composables in isolation**
- **Use `@vue/test-utils`** with Composition API patterns
- **Mock external dependencies properly**
- **Test reactive state changes**
- **Test emitted events and props**

## 🎯 Component Selection Policy

### 📋 Priority Order (MANDATORY)
1. **🥇 @owlint/feathers-vue FIRST** - Always check here first
2. **🥈 Reka UI SECOND** - If @owlint doesn't have it or lacks features  
3. **🥉 Custom Component LAST** - Only when neither library fits

### 🤖 Claude Agent Decision Process
When user needs a component, I MUST:
1. **Check @owlint first** - Always start here
2. **Present options** with clear feature comparison if multiple exist
3. **Ask user to choose** with trade-offs explained
4. **Recommend @owlint by default** but highlight Reka advantages if significant
5. **Suggest custom component** only when no suitable option exists

### 📚 Available Components

#### @owlint/feathers-vue Components (Pre-styled, Design System)
```typescript
// ✅ PRIORITY COMPONENTS - Use these first
OAlert           // Alert/notification messages
OBadge           // Status badges and indicators  
OButton          // Buttons with icons, loading states
OCheckbox        // Checkbox inputs
OFilterItem      // Filter tags/chips
OIcon            // Icon system
OIndicator       // Status indicators
OInput           // Text inputs and form fields
OModal           // Modal dialogs
ONotification    // Toast notifications
ONotificationList // Notification container
OPagination      // Pagination controls
OPopper          // Tooltip/popover positioning
ORadio           // Radio button inputs
OTable           // Data tables

// Import pattern:
import { OButton, OInput, OTable } from '@owlint/feathers-vue'
```

#### Reka UI Components (Headless + Style with Tailwind)
```typescript
// ✅ FORM COMPONENTS - Use when @owlint unavailable
Checkbox         // Checkbox inputs
Combobox         // Searchable dropdowns  
Editable         // Inline editing
Listbox          // List selection
NumberField      // Number inputs
Label            // Form labels
PinInput         // PIN/code inputs
RadioGroup       // Radio button groups
Select           // Dropdown selects
Slider           // Range inputs
Switch           // Toggle switches
TagsInput        // Tag input fields
Toggle           // Toggle buttons
ToggleGroup      // Toggle button groups

// ✅ DATE COMPONENTS (Advanced)
Calendar         // Calendar picker
DateField        // Date input field (Alpha)
DatePicker       // Date selection (Alpha)
DateRangeField   // Date range input (Alpha)
DateRangePicker  // Date range selection (Alpha)
RangeCalendar    // Range calendar (Alpha)
TimeField        // Time inputs (Alpha)

// ✅ GENERAL COMPONENTS
Accordion        // Collapsible content
AlertDialog      // Confirmation dialogs
AspectRatio      // Aspect ratio containers
Avatar           // User avatars
Collapsible      // Collapsible content
ContextMenu      // Right-click menus
Dialog           // Modal overlays
DropdownMenu     // Context menus
HoverCard        // Hover overlays
Menubar          // Menu bars
NavigationMenu   // Navigation systems
Pagination       // Pagination controls
Popover          // Overlay positioning
Progress         // Progress bars
ScrollArea       // Custom scrollable areas
Separator        // Visual separators
Splitter         // Resizable panels
Stepper          // Step-by-step navigation (Alpha)
Tabs             // Tab navigation
Toast            // Toast notifications
Toolbar          // Toolbars
Tooltip          // Tooltips
Tree             // Tree structures (Alpha)

// ❌ NOT AVAILABLE in Reka UI:
Card             // Use custom component instead
Button           // Use @owlint OButton instead
Input            // Use @owlint OInput instead
Badge            // Use @owlint OBadge instead
Table            // Use @owlint OTable instead

// ✅ MANDATORY: Always use NAMESPACED syntax for Reka components
import { Dialog, Calendar, DatePicker, Combobox, Avatar } from 'reka-ui/namespaced'
```

### 🎯 Decision Examples

#### Example 1: User needs a button
```typescript
// ✅ ALWAYS use @owlint first
<OButton 
  label="Save" 
  type="primary" 
  color="blue" 
  :loading="saving"
  @click="handleSave" 
/>
```

#### Example 2: User needs a Card component
```
🔍 @owlint/feathers-vue: ❌ No Card component
🔍 Reka UI: ❌ No Card component

Claude should ask:
"I found that neither @owlint nor Reka UI have a Card component:

1. 🏗️ **Keep existing custom Card** (components/global/card.vue)
2. 🆕 **Create enhanced Card** using available components as base
3. 💡 **Request Card component** from @owlint team

Your current Card component is functional. Would you like to enhance it or keep it as-is?"
```

#### Example 3: User needs a date picker
```
🔍 @owlint/feathers-vue: ❌ No date picker
🔍 Reka UI: ✅ Calendar + DatePicker with advanced features

Claude should ask:
"I found these options for date picker:

1. 📅 **Reka DatePicker** (Advanced features: timezone, ranges, validation)
2. 🏗️ **Custom component** using @owlint components as base  
3. 💡 **Request @owlint DatePicker** from the team

The Reka DatePicker has these extra features: [list]. 
Which approach would you prefer?"
```

#### Example 4: User needs a table
```typescript
// ✅ @owlint has OTable - use it first
<OTable 
  :fields="tableFields"
  :items="tableData"
  :pagination="paginationConfig"
/>

// Only suggest Reka if user needs advanced features like:
// - Virtual scrolling for 10k+ rows
// - Column resizing/reordering
// - Advanced filtering
```

### 🎨 Theme System & Styling Rules

#### 🚨 MANDATORY: Theme System Usage
This project uses a centralized theme system with CSS variables defined in `main.css`. **NEVER use `dark:` prefixes** for text and background colors as they are handled automatically by the theme system.

#### ✅ Text Color Rules
```vue
<!-- ✅ GOOD: No Tailwind classes - inherits from global styles -->
<h1>{{ title }}</h1>
<p>{{ description }}</p>

<!-- ✅ GOOD: Use semantic theme colors -->
<p class="text-secondary">{{ subtitle }}</p>
<span class="text-primary">{{ highlight }}</span>

<!-- ❌ FORBIDDEN: Never use dark: prefixes -->
<p class="text-slate-600 dark:text-slate-400">Bad</p>
<span class="text-gray-500 dark:text-gray-300">Bad</span>
```

#### ✅ Background Color Rules
```vue
<!-- ✅ GOOD: Use semantic theme backgrounds -->
<div class="bg-primary">Primary background</div>
<div class="bg-secondary">Secondary background</div>
<div class="bg1">Background level 1</div>
<div class="bg2">Background level 2</div>
<div class="bg3">Background level 3</div>

<!-- ❌ FORBIDDEN: Never use dark: prefixes -->
<div class="bg-bg1">Bad</div>
<div class="bg-gray-50 dark:bg-gray-900">Bad</div>
```

#### 🎯 Theme Colors Available
- **text-primary**: Primary text color (auto-adjusts for dark/light)
- **text-secondary**: Secondary text color (auto-adjusts intensity)
- **bg-primary**: Primary background color
- **bg-secondary**: Secondary background color  
- **bg1, bg2, bg3**: Background hierarchy levels
- **Normal text**: No classes needed - inherits from global styles

#### @owlint Components (Pre-styled)
```vue
<!-- ✅ GOOD: Use provided props for styling -->
<OButton color="blue" size="lg" type="primary" />
<OAlert type="success" />
<OBadge color="green" size="sm" />
```

#### Reka Components (Style with Tailwind + Namespaced Syntax)
```vue
<script setup lang="ts">
// ✅ MANDATORY: Use namespaced imports for Reka components
import { Dialog, Calendar, DatePicker } from 'reka-ui/namespaced'
</script>

<template>
  <!-- ✅ GOOD: Apply Tailwind classes with namespaced components -->
  <Dialog.Root>
    <Dialog.Trigger class="px-4 py-2 bg-blue-500 text-white rounded">
      Open Dialog
    </Dialog.Trigger>
    <Dialog.Portal>
      <Dialog.Overlay class="fixed inset-0 bg-black/50" />
      <Dialog.Content class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white p-6 rounded-lg shadow-lg">
        <Dialog.Title class="text-lg font-semibold mb-4">Settings</Dialog.Title>
        <Calendar.Root class="border rounded-lg shadow-lg bg-white" />
        <DatePicker.Root class="w-full px-3 py-2 border border-gray-300 rounded-md" />
      </Dialog.Content>
    </Dialog.Portal>
  </Dialog.Root>

  <!-- ❌ NEVER: Custom CSS -->
  <style>
  .custom-calendar { /* Never do this */ }
  </style>
</template>
```

### 🏗️ Custom Component Guidelines

Create custom components ONLY when:
- ❌ @owlint doesn't have it
- ❌ Reka doesn't have it
- ❌ Neither meets specific requirements

#### Custom Component Pattern
```vue
<script setup lang="ts">
// ✅ GOOD: Build on top of existing components
import { OButton, OIcon } from '@owlint/feathers-vue'
// ✅ MANDATORY: Use namespaced imports for Reka components
import { Calendar } from 'reka-ui/namespaced'

// Create enhanced functionality
const customFeature = () => {
  // Add your custom logic
}
</script>

<template>
  <div class="mint-custom-component">
    <!-- Use base components -->
    <OButton @click="customFeature">
      <OIcon name="calendar" />
      Advanced Calendar
    </OButton>
    <Calendar.Root class="mt-4 border rounded" />
  </div>
</template>
```

### 🚨 Forms - Special Rules

#### ALWAYS use @owlint for forms
```vue
<script setup lang="ts">
// ✅ GOOD: @owlint form components
import { OInput, OCheckbox, ORadio, OButton } from '@owlint/feathers-vue'
</script>

<template>
  <form>
    <OInput 
      v-model="form.email" 
      type="email" 
      placeholder="Email"
      :error="errors.email" 
    />
    <OCheckbox v-model="form.terms" label="Accept terms" />
    <ORadio v-model="form.type" value="premium" label="Premium" />
    <OButton type="submit" :loading="submitting">Submit</OButton>
  </form>
</template>
```

### 🎯 Reka UI Namespaced Syntax Rules

#### ✅ MANDATORY: Always Use Namespaced Imports
```vue
<script setup lang="ts">
// ✅ CORRECT: Namespaced imports from 'reka-ui/namespaced'
import { Card, Dialog, Select, Calendar } from 'reka-ui/namespaced'

// ❌ FORBIDDEN: Individual imports
import * as Card from 'reka-ui/card'
import { Card, Dialog } from 'reka-ui'
</script>

<template>
  <!-- ✅ CORRECT: Use namespaced components -->
  <Card.Root>
    <Card.Header>
      <Card.Title>Title</Card.Title>
    </Card.Header>
    <Card.Content>
      <Select.Root>
        <Select.Trigger>
          <Select.Value />
        </Select.Trigger>
        <Select.Content>
          <Select.Item value="option1">Option 1</Select.Item>
        </Select.Content>
      </Select.Root>
    </Card.Content>
  </Card.Root>

  <!-- ❌ FORBIDDEN: Direct component usage -->
  <Card>
    <CardHeader>
      <CardTitle>Title</CardTitle>
    </CardHeader>
  </Card>
</template>
```

#### 🎯 Common Namespaced Components
```typescript
// Most used Reka components with namespaced syntax:
Card.Root, Card.Header, Card.Title, Card.Content
Dialog.Root, Dialog.Trigger, Dialog.Content, Dialog.Header, Dialog.Title
Select.Root, Select.Trigger, Select.Value, Select.Content, Select.Item
Calendar.Root, Calendar.Header, Calendar.Grid, Calendar.Cell
DatePicker.Root, DatePicker.Input, DatePicker.Content
Combobox.Root, Combobox.Input, Combobox.Trigger, Combobox.Content
```

### 🔄 When to Ask User

I should present choices when:
- **Feature gaps exist** between @owlint and Reka
- **Reka has significantly more features** (like Calendar vs basic date input)
- **Performance considerations** (virtual scrolling, large datasets)
- **Complex interactions** not available in @owlint

### 💡 @owlint Feature Requests

When @owlint component lacks features, suggest:
```
"The @owlint component is missing [feature]. You could:
1. Use Reka [component] with Tailwind styling
2. Request this feature from the @owlint team
3. Create a custom wrapper that enhances @owlint component

Which approach would you prefer?"
```


### Git and Commits
- **NEVER commit** unless user explicitly asks
- If user asks for commit, always:
  1. Run `yarn test:all` first
  2. Use proper commit message format
  3. Include the standard footer:
    ```
    🤖 Generated with [Claude Code](https://claude.ai/code)
    
    Co-Authored-By: Claude <noreply@anthropic.com>
    ```

### Testing Patterns
- Unit tests: Use Nuxt environment with `mockNuxtImport`
- E2E tests: Use jsdom environment with MSW mocking
- UI tests: Use jsdom environment with global mocks
- Follow existing mock patterns in test files

### File Structure Rules
- Tests go in appropriate directories:
  - `tests/unit/` - Unit tests (*.test.ts)
  - `tests/e2e/` - Integration tests (*.test.ts) 
  - `tests/unit/` - UI-specific tests (*.ui.test.ts)
- Follow Nuxt conventions for pages, components, composables

### Security
- Never expose secrets or API keys
- Use environment variables for configuration
- Follow secure coding practices

### Icons System
- **ALWAYS use Font Awesome icons** - never import icon libraries like Lucide, Heroicons, etc.
- We have Font Awesome Pro license with full access to all icons
- Use Font Awesome kit system with `<i>` tags
- Icon examples:
  ```vue
  <!-- ✅ GOOD: Font Awesome icons -->
  <i class="fas fa-chevron-left"></i>      <!-- Chevron left -->
  <i class="fas fa-chevron-right"></i>     <!-- Chevron right -->
  <i class="fas fa-chevron-double-left"></i>  <!-- First page -->
  <i class="fas fa-chevron-double-right"></i> <!-- Last page -->
  <i class="fas fa-ellipsis-h"></i>        <!-- More horizontal -->
  <i class="fas fa-search"></i>            <!-- Search -->
  <i class="fas fa-plus"></i>              <!-- Add/Plus -->
  <i class="fas fa-trash"></i>             <!-- Delete -->
  <i class="fas fa-edit"></i>              <!-- Edit -->
  <i class="fas fa-cog"></i>               <!-- Settings -->
  <i class="fas fa-user"></i>              <!-- User -->
  <i class="fas fa-building"></i>          <!-- Building -->
  
  <!-- ❌ FORBIDDEN: Never import icon libraries -->
  import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
  import { ChevronLeftIcon } from '@heroicons/vue/24/outline'
  ```

## 🧪 Test Commands You Should Use

```bash
yarn test:unit      # Unit tests (41 tests)
yarn test:e2e       # E2E tests (26 tests)
yarn test:ui        # UI tests (23 tests)
yarn test:all       # Run unit + e2e tests
yarn validate       # Alias for test:all
```

## 🚫 What You Must NOT Do

- Use npm instead of yarn
- Commit without being asked
- Deploy without being asked
- Add comments unless requested
- Break existing tests
- Create unnecessary files
- Change core configuration without discussion

## ✅ What You Should Always Do

- Run tests after any changes
- Follow existing patterns
- Use TypeScript properly
- Maintain or improve test coverage
- Ask clarifying questions when unsure

---

*This file provides instructions for AI assistants working on mint-front*
*Last updated: 2025-07-04*