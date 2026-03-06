# Frontend Component Standards

## Component Library Priority

**CRITICAL**: Always use Vuellar components (`@owlint/feathers-vue`) first. Only create custom components when Vuellar doesn't provide what's needed.

### Component Selection Order

1. **Use Vuellar component** if it exists for your use case
2. **Compose Vuellar components** to build more complex UI
3. **Create custom component** only if Vuellar doesn't meet the need

### Available Vuellar Components

Import from `@owlint/feathers-vue`:

```typescript
import {
    // Electrons (Core Primitives)
    Avatar,
    Badge,
    Bullet,
    Button,
    Checkbox,
    Input,
    Label,
    Link,
    Radio,
    Select,
    Switch,
    Tab,
    Tag,
    Textarea,
    Toggle,

    // Atoms (Building Blocks)
    Breadcrumb,
    Chips,
    DateRangePicker,
    Pagination,
    Searchbar,
    Table,

    // Molecules (Functional Composites)
    Alert,
    Menu,

    // Organisms (Complex Composites)
    Modal,
} from '@owlint/feathers-vue'
```

### When to Use Each Component

| Need                     | Use        | Don't Use  |
| ------------------------ | ---------- | ---------- |
| Navigate to another page | `Link`     | `Button`   |
| Trigger an action        | `Button`   | `Link`     |
| Select multiple options  | `Checkbox` | `Radio`    |
| Select one option        | `Radio`    | `Checkbox` |
| Toggle immediate setting | `Switch`   | `Checkbox` |
| Form boolean choice      | `Checkbox` | `Switch`   |
| Static category label    | `Tag`      | `Chips`    |
| Removable filter/tag     | `Chips`    | `Tag`      |
| Status dot only          | `Bullet`   | `Badge`    |
| Status with text/icon    | `Badge`    | `Bullet`   |
| Short text input         | `Input`    | `Textarea` |
| Long text input          | `Textarea` | `Input`    |
| 5+ dropdown options      | `Select`   | `Radio`    |
| 2-4 visible options      | `Radio`    | `Select`   |
| View mode toggle         | `Toggle`   | `Tab`      |
| Content sections         | `Tab`      | `Toggle`   |

---

## Vuellar Standardized Props

### variant

Controls visual style:

- `primary`: Filled/solid style (default)
- `secondary`: Lighter/outlined style
- `tertiary`: Minimal/text-only style (Button only)

### intent

Semantic colors for meaningful feedback:

- `neutral`: Gray - default, neutral state
- `accent`: Pink - branded, highlighted
- `success`: Green - positive, confirmed
- `warning`: Orange - caution, attention
- `danger`: Red - error, destructive
- `info`: Blue - informational

### color

Decorative colors for visual variety (no semantic meaning):

- `sage`, `almond`, `pink`, `indigo`, `yellow`, `cherry`, `cyan`

### size

Component dimensions: `xs`, `sm`, `md`, `lg` (availability varies)

### Intent vs Color

- Use **intent** for semantic meaning (success, error, warning states)
- Use **color** for decorative purposes (visual variety without meaning)
- When both are provided, **intent takes precedence**

---

## Component Best Practices

### Single Responsibility

Each component should have one clear purpose and do it well

### Reusability

Design components to be reused across different contexts with configurable props

### Composability

Build complex UIs by combining smaller, simpler Vuellar components

### Clear Interface

Define explicit, well-documented props with sensible defaults

### Encapsulation

Keep internal implementation details private and expose only necessary APIs

### Consistent Naming

- Use PascalCase for component files: `UserProfile.vue`, `CompanyCard.vue`
- Compose names from general to specific: `SearchButtonClear.vue` NOT `ClearSearchButton.vue`

### State Management

Keep state as local as possible; lift it up only when needed by multiple components

### Minimal Props

Keep the number of props manageable; if a component needs many props, consider composition

---

## Vue Component Structure

### Script Setup Pattern

**ALWAYS** use Composition API with `<script setup lang="ts">`.

**Template first**: Place `<template>` above `<script setup>` for better readability.

```vue
<template>
    <!-- Template content using Vuellar components -->
    <div class="flex flex-col gap-4">
        <h1>{{ displayName }}</h1>
        <Button label="Click me" @click="handleClick" />
    </div>
</template>

<script setup lang="ts">
// Imports
import { ref, computed, reactive, onMounted } from 'vue'
import { Button, Input, Alert } from '@owlint/feathers-vue'
import type { User } from '@/types'

// Props (interface + destructure with defaults)
interface Props {
    userId: number
    variant?: 'primary' | 'secondary'
    size?: 'sm' | 'md' | 'lg'
}

const { userId, variant = 'primary', size = 'md' } = defineProps<Props>()

// Emits - ALWAYS use TypeScript syntax
const emit = defineEmits<{
    click: [event: MouseEvent]
    update: [value: string]
}>()

// v-model with defineModel
const title = defineModel<string>({ required: true })

// Reactive state
const count = ref(0)
const user = reactive({ name: '', email: '' })

// Computed properties
const displayName = computed(() => `${user.name} (${count.value})`)

// Functions (ALWAYS arrow functions)
const handleClick = () => {
    emit('click', event)
}

// Lifecycle hooks
onMounted(() => {
    // initialization
})
</script>
```

### Template Syntax

```vue
<template>
    <!-- Use kebab-case for props in template -->
    <UserCard :user-name="name" :is-active="active" @update-user="handleUpdate" />

    <!-- Use prop shorthand when variable name matches -->
    <UserCard :count />
    <!-- instead of :count="count" -->

    <!-- Use slot shorthand -->
    <template #header>Header content</template>

    <!-- ALWAYS use :key with unique identifier in v-for -->
    <div v-for="user in users" :key="user.id">
        {{ user.name }}
    </div>
</template>
```

---

## Common Vuellar Patterns

### Form with Validation

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Input, Button, Alert } from '@owlint/feathers-vue'

const email = ref('')
const error = ref('')
const loading = ref(false)

async function handleSubmit() {
    loading.value = true
    try {
        await submitForm({ email: email.value })
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
        <Input
            id="email"
            v-model="email"
            type="email"
            label="Email"
            placeholder="you@example.com"
            :error="error"
            required
        />

        <Alert v-if="error" variant="danger" :title="error" icon="fa-exclamation-circle" />

        <Button type="submit" label="Submit" variant="primary" :loading="loading" />
    </form>
</template>
```

### Data Table

```vue
<script setup lang="ts">
import { Table, Tag, Button, Menu } from '@owlint/feathers-vue'
import { ref } from 'vue'

const fields = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
]

const users = ref([{ id: 1, name: 'John', email: 'john@example.com', status: 'active' }])
</script>

<template>
    <Table :fields="fields" :items="users">
        <template #cell(status)="{ value }">
            <Tag :label="value" :intent="value === 'active' ? 'success' : 'neutral'" />
        </template>
        <template #cell(actions)="{ item }">
            <Menu
                v-model="item.menuOpen"
                :items="[
                    { label: 'Edit', onClick: () => edit(item) },
                    { label: 'Delete', onClick: () => remove(item) },
                ]"
            />
        </template>
    </Table>
</template>
```

### Modal Dialog

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Modal, Button } from '@owlint/feathers-vue'

const showModal = ref(false)

function confirmDelete() {
    // delete logic
    showModal.value = false
}
</script>

<template>
    <Button label="Delete" variant="secondary" @click="showModal = true" />

    <Modal
        v-model:displayModal="showModal"
        title="Confirm Delete"
        icon="fa-trash"
        @close="showModal = false"
    >
        <template #description>
            Are you sure you want to delete this item? This action cannot be undone.
        </template>
        <template #footer>
            <Button label="Cancel" variant="secondary" @click="showModal = false" />
            <Button label="Delete" variant="primary" @click="confirmDelete" />
        </template>
    </Modal>
</template>
```

### Alert Messages

```vue
<template>
    <!-- Success Alert -->
    <Alert
        title="Success!"
        description="Your changes have been saved."
        variant="success"
        icon="fa-check"
    />

    <!-- Warning with Action -->
    <Alert
        title="Warning"
        description="This action cannot be undone."
        variant="warning"
        action="Proceed"
        @click="confirmAction"
    />

    <!-- Error Alert -->
    <Alert
        title="Error"
        description="Something went wrong. Please try again."
        variant="danger"
        icon="fa-exclamation-circle"
    />
</template>
```

---

## Creating Custom Components

Only create custom components when Vuellar doesn't provide what you need.

### Checklist Before Creating

1. ✅ Is there a Vuellar component for this?
2. ✅ Can I compose multiple Vuellar components?
3. ✅ Is this truly reusable (used in 3+ places)?
4. ✅ Does it follow the same props pattern as Vuellar?

### Custom Component Guidelines

If you must create a custom component:

1. **Follow Vuellar props pattern** (variant, intent, color, size)
2. **Use Vuellar primitives inside** your custom component
3. **Document props and slots** with TypeScript
4. **Place in `src/components/`** organized by feature or `ui/` for generic

```vue
<template>
    <div class="flex items-center gap-2">
        <span>{{ title }}</span>
        <Badge v-if="count" :number="String(count)" :intent="intent" />
    </div>
</template>

<script setup lang="ts">
import { Button, Badge } from '@owlint/feathers-vue'

// Follow Vuellar props pattern (interface + destructure with defaults)
interface Props {
    title: string
    count?: number
    variant?: 'primary' | 'secondary'
    intent?: 'neutral' | 'success' | 'warning' | 'danger'
}

const { title, count, variant = 'primary', intent = 'neutral' } = defineProps<Props>()
</script>
```
