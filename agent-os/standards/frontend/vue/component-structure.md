# Vue Component Structure

## File Naming

- **ALWAYS** use PascalCase: `UserProfile.vue`, `CompanyCard.vue`
- Compose names general to specific: `SearchButtonClear.vue` NOT `ClearSearchButton.vue`
- Keep names descriptive and specific

---

## Component Structure Pattern

**ALWAYS** use Composition API with `<script setup lang="ts">`. **NEVER** use Options API.

**Template first**: Place `<template>` above `<script setup>` for better readability.

```vue
<template>
  <!-- Template using Vuellar components -->
  <div class="flex flex-col gap-4">
    <h1>{{ displayTitle }}</h1>
    <Button label="Submit" @click="handleSubmit" />
  </div>
</template>

<script setup lang="ts">
// 1. Vue imports
import { ref, computed, onMounted } from 'vue'

// 2. Vuellar components
import { Button, Input, Alert, Modal } from '@owlint/feathers-vue'

// 3. External libraries
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'

// 4. Local imports (stores, composables, types)
import { useAuthStore } from '@/stores/auth'
import { companyByIdQuery } from '@/queries/companies'
import type { Company } from '@/types'

// 5. Props (interface + destructure with defaults)
interface Props {
  companyId: number
  title: string
  variant?: 'primary' | 'secondary'
  size?: 'sm' | 'md' | 'lg'
}

const { companyId, title, variant = 'primary', size = 'md' } = defineProps<Props>()

// 6. Emits (ALWAYS interface, like Props)
interface Emits {
  submit: [data: FormData]
  cancel: []
  update: [value: string]
}

const emit = defineEmits<Emits>()

// 7. Models (v-model binding)
const searchQuery = defineModel<string>('query')
const isOpen = defineModel<boolean>({ required: true })

// 8. Composables and stores
const route = useRoute()
const authStore = useAuthStore()

// 9. Reactive state
const isLoading = ref(false)
const formData = reactive({ name: '', email: '' })

// 10. Computed properties
const displayTitle = computed(() => title.toUpperCase())
const canSubmit = computed(() => !isLoading.value && formData.name.length > 0)

// 11. Functions (ALWAYS arrow functions)
const handleSubmit = () => {
  emit('submit', formData)
}

const handleCancel = () => {
  emit('cancel')
}

// 12. Lifecycle hooks
onMounted(() => {
  // initialization logic
})
</script>
```

---

## Props Definition

**ALWAYS** use interface + destructuring with defaults. **NEVER** use `withDefaults()`.

### Basic Props (no defaults needed)
```vue
<template>
  <div>{{ userId }} - {{ userName }}</div>
</template>

<script setup lang="ts">
interface Props {
  userId: number
  userName: string
}

const { userId, userName } = defineProps<Props>()
</script>
```

### Props with Defaults (destructuring pattern)
```vue
<template>
  <button :class="[variant, size]" :disabled="disabled">
    <slot />
  </button>
</template>

<script setup lang="ts">
interface Props {
  variant?: 'primary' | 'secondary'
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
}

const { variant = 'primary', size = 'md', disabled = false } = defineProps<Props>()
</script>
```

### Rules

| Do | Don't |
|----|-------|
| `interface Props { ... }` | Inline type in `defineProps<{ ... }>()` |
| Destructure with defaults: `const { x = 'default' } = defineProps<Props>()` | `withDefaults(defineProps<...>(), {...})` |
| TypeScript generic syntax | Runtime props validation |
| Template above script | Script above template |

---

## Emits Definition

**ALWAYS** use a named `interface Emits` with typed arguments, same pattern as Props:

```vue
<template>
  <button @click="handleClick">Click me</button>
</template>

<script setup lang="ts">
// ALWAYS declare a named interface for emits
interface Emits {
  click: [event: MouseEvent]
  update: [value: string]
  submit: [data: { name: string; email: string }]
  cancel: []  // No arguments
}

const emit = defineEmits<Emits>()

// Usage (ALWAYS arrow functions)
const handleClick = (event: MouseEvent) => {
  emit('click', event)
}

const handleUpdate = (value: string) => {
  emit('update', value)
}

const handleCancel = () => {
  emit('cancel')
}
</script>
```

### Rules

| Do | Don't |
|----|-------|
| `interface Emits { click: [e: MouseEvent] }` then `defineEmits<Emits>()` | Inline `defineEmits<{ click: [e: MouseEvent] }>()` |
| Named interface (like Props) | Anonymous inline type |
| TypeScript tuple syntax | `defineEmits(['click'])` runtime array |

---

## defineModel (v-model)

Replace `modelValue` prop + `update:modelValue` emit pattern:

### Simple v-model
```vue
<template>
  <input v-model="value" />
</template>

<script setup lang="ts">
// Replaces: defineProps + emit pattern
const value = defineModel<string>()
</script>

<!-- Parent usage -->
<ChildComponent v-model="searchQuery" />
```

### With options
```vue
<template>
  <input v-model="title" />
</template>

<script setup lang="ts">
const title = defineModel<string>({
  required: true,
  default: 'Untitled',
})
</script>
```

### Multiple models
```vue
<template>
  <div class="flex flex-col gap-2">
    <input v-model="firstName" placeholder="First name" />
    <input v-model="lastName" placeholder="Last name" />
    <input v-model="age" type="number" placeholder="Age" />
  </div>
</template>

<script setup lang="ts">
const firstName = defineModel<string>('firstName')
const lastName = defineModel<string>('lastName')
const age = defineModel<number>('age')
</script>

<!-- Parent usage -->
<UserForm
  v-model:first-name="user.firstName"
  v-model:last-name="user.lastName"
  v-model:age="user.age"
/>
```

### With transformations
```vue
<template>
  <input v-model="title" />
</template>

<script setup lang="ts">
const [title, modifiers] = defineModel<string>('title', {
  get: (value) => value?.trim(),
  set: (value) => {
    if (modifiers.capitalize) {
      return value.charAt(0).toUpperCase() + value.slice(1)
    }
    return value
  },
})
</script>
```

---

## Template Syntax

### Prop and Event Naming
```vue
<template>
  <!-- kebab-case in template -->
  <UserCard
    :user-name="name"
    :is-active="active"
    @update-user="handleUpdate"
  />
</template>

<script setup lang="ts">
// camelCase in script
interface Props {
  userName: string
  isActive: boolean
}

const { userName, isActive } = defineProps<Props>()

interface Emits {
  updateUser: [user: User]
}

const emit = defineEmits<Emits>()
</script>
```

### Shorthand Syntax
```vue
<template>
  <!-- Prop shorthand when variable matches -->
  <UserCard :count />  <!-- instead of :count="count" -->

  <!-- Slot shorthand -->
  <template #header>Header content</template>
  <template #default>Default content</template>

  <!-- DON'T use verbose syntax -->
  <template v-slot:header>Header</template>  <!-- Bad -->
</template>
```

### Conditional Rendering
```vue
<template>
  <!-- v-if/v-else-if/v-else for conditional blocks -->
  <div v-if="isLoading">Loading...</div>
  <div v-else-if="error">Error: {{ error.message }}</div>
  <div v-else>{{ data }}</div>

  <!-- v-show for toggle visibility (stays in DOM) -->
  <div v-show="isVisible">Toggle visibility</div>

  <!-- Permission checks -->
  <Button v-if="canCreateCompany" label="Create" />
</template>
```

### List Rendering
```vue
<template>
  <!-- ALWAYS use :key with unique identifier -->
  <div v-for="user in users" :key="user.id">
    {{ user.name }}
  </div>

  <!-- With index (only if no unique id) -->
  <div v-for="(item, index) in items" :key="index">
    {{ item }}
  </div>

  <!-- NEVER omit :key -->
  <!-- <div v-for="user in users">{{ user.name }}</div> -->
</template>
```

---

## Functions

**ALWAYS use arrow functions** for all functions and methods:

```vue
<template>
  <button @click="handleSubmit">Submit</button>
</template>

<script setup lang="ts">
// Arrow functions for all methods
const handleSubmit = () => {
  emit('submit', formData)
}

const handleClick = (event: MouseEvent) => {
  emit('click', event)
}

const fetchData = async () => {
  isLoading.value = true
  try {
    await api.getData()
  } finally {
    isLoading.value = false
  }
}

// Arrow functions for computed
const filteredItems = computed(() => {
  return items.value.filter((item) => item.active)
})

// Arrow functions for callbacks
users.value.map((user) => user.name)
</script>
```

---

## Component Communication

### Parent to Child (Props)
```vue
<!-- Parent.vue -->
<template>
  <ChildComponent :user-name="userName" />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import ChildComponent from './ChildComponent.vue'

const userName = ref('John Doe')
</script>

<!-- ChildComponent.vue -->
<template>
  <span>{{ userName }}</span>
</template>

<script setup lang="ts">
interface Props {
  userName: string
}

const { userName } = defineProps<Props>()
</script>
```

### Child to Parent (Emits)
```vue
<!-- ChildComponent.vue -->
<template>
  <button @click="handleUpdate('new value')">Update</button>
</template>

<script setup lang="ts">
interface Emits {
  update: [value: string]
}

const emit = defineEmits<Emits>()

const handleUpdate = (value: string) => {
  emit('update', value)
}
</script>

<!-- Parent.vue -->
<template>
  <ChildComponent @update="handleUpdate" />
</template>
```

### Two-Way Binding (v-model)
```vue
<!-- ChildComponent.vue -->
<template>
  <input v-model="value" />
</template>

<script setup lang="ts">
const value = defineModel<string>()
</script>

<!-- Parent.vue -->
<template>
  <ChildComponent v-model="searchQuery" />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import ChildComponent from './ChildComponent.vue'

const searchQuery = ref('')
</script>
```
