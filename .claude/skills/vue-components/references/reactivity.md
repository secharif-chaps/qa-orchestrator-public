# Vue Reactivity Patterns

## Core Reactivity APIs

### ref vs reactive

| Use `ref` for | Use `reactive` for |
|---------------|-------------------|
| Primitives (string, number, boolean) | Objects with multiple properties |
| Single values | Form data objects |
| Values that may be reassigned | State that won't be reassigned |

```vue
<script setup lang="ts">
import { ref, reactive } from 'vue'

// ref for primitives
const count = ref(0)
const name = ref('')
const isActive = ref(false)

// reactive for objects
const user = reactive({
  name: '',
  email: '',
  age: 0,
})

const formData = reactive({
  firstName: '',
  lastName: '',
  email: '',
})
</script>
```

### Accessing ref Values

```vue
<script setup lang="ts">
const count = ref(0)

// In script: use .value
count.value++
console.log(count.value)

// In template: automatic unwrapping (no .value needed)
</script>

<template>
  <span>{{ count }}</span>
  <button @click="count++">Increment</button>
</template>
```

---

## Computed Properties

### Basic Computed
```vue
<script setup lang="ts">
import { ref, computed } from 'vue'

const firstName = ref('John')
const lastName = ref('Doe')

// Computed with getter only
const fullName = computed(() => {
  return `${firstName.value} ${lastName.value}`
})
</script>
```

### Computed with Getter/Setter
```vue
<script setup lang="ts">
const fullName = computed({
  get() {
    return `${firstName.value} ${lastName.value}`
  },
  set(value: string) {
    const parts = value.split(' ')
    firstName.value = parts[0] || ''
    lastName.value = parts[1] || ''
  },
})

// Can now do: fullName.value = 'Jane Smith'
</script>
```

### Rules

- Computed properties are **cached** based on reactive dependencies
- Use computed for derived state, NOT for side effects
- Keep computed getters pure (no mutations)

---

## Watchers

### Watch Single Source
```vue
<script setup lang="ts">
import { ref, watch } from 'vue'

const count = ref(0)

watch(count, (newValue, oldValue) => {
  console.log(`Count changed from ${oldValue} to ${newValue}`)
})
</script>
```

### Watch Multiple Sources
```vue
<script setup lang="ts">
const count = ref(0)
const name = ref('')

watch([count, name], ([newCount, newName], [oldCount, oldName]) => {
  console.log('Values changed:', { newCount, newName })
})
</script>
```

### Watch Reactive Object Property
```vue
<script setup lang="ts">
const user = reactive({ name: '', email: '' })

// Watch specific property with getter
watch(
  () => user.email,
  (newEmail) => {
    console.log('Email changed:', newEmail)
  }
)
</script>
```

### Watch Options
```vue
<script setup lang="ts">
watch(
  searchQuery,
  (newQuery) => {
    performSearch(newQuery)
  },
  {
    immediate: true,  // Run immediately on mount
    deep: true,       // Deep watch for nested objects
    flush: 'post',    // Run after DOM updates
  }
)
</script>
```

### watchEffect

Automatically tracks dependencies:

```vue
<script setup lang="ts">
import { watchEffect } from 'vue'

// Automatically tracks count as dependency
watchEffect(() => {
  console.log('Count is:', count.value)
  document.title = `Count: ${count.value}`
})
</script>
```

### When to Use Each

| Use `watch` when | Use `watchEffect` when |
|------------------|----------------------|
| Need old and new values | Just need current values |
| Want explicit dependencies | Want automatic tracking |
| Need lazy execution | Need immediate execution |
| Watching specific sources | Running side effects |

---

## State Management Patterns

### Local Component State
```vue
<script setup lang="ts">
// Simple local state
const isOpen = ref(false)
const selectedId = ref<number | null>(null)

// Form state
const formData = reactive({
  name: '',
  email: '',
  message: '',
})

// Reset form (arrow function)
const resetForm = () => {
  formData.name = ''
  formData.email = ''
  formData.message = ''
}
</script>
```

### Never Mutate Props
```vue
<template>
  <button @click="increment">Increment</button>
</template>

<script setup lang="ts">
interface Props {
  count: number
}

const { count } = defineProps<Props>()

// NEVER do this
// count = 10  // ERROR!

// Create local copy if mutation needed
const localCount = ref(count)

// Or emit to parent
interface Emits {
  update: [value: number]
}

const emit = defineEmits<Emits>()

const increment = () => {
  emit('update', count + 1)
}
</script>
```

### Sync Props to Local State
```vue
<template>
  <input v-model="localValue" />
</template>

<script setup lang="ts">
interface Props {
  initialValue: string
}

const { initialValue } = defineProps<Props>()

// Initialize from prop
const localValue = ref(initialValue)

// Sync when prop changes (if needed)
watch(
  () => initialValue,
  (newValue) => {
    localValue.value = newValue
  }
)
</script>
```

---

## Composables Pattern

Extract reusable reactive logic:

### Creating Composables
```typescript
// composables/useCounter.ts
import { ref, computed } from 'vue'

export const useCounter = (initialValue = 0) => {
  const count = ref(initialValue)

  const doubleCount = computed(() => count.value * 2)

  const increment = () => {
    count.value++
  }

  const decrement = () => {
    count.value--
  }

  const reset = () => {
    count.value = initialValue
  }

  return {
    count,
    doubleCount,
    increment,
    decrement,
    reset,
  }
}
```

### Using Composables
```vue
<template>
  <div>
    <p>Count: {{ count }}</p>
    <p>Double: {{ doubleCount }}</p>
    <button @click="increment">+</button>
    <button @click="decrement">-</button>
  </div>
</template>

<script setup lang="ts">
import { useCounter } from '@/composables/useCounter'

const { count, doubleCount, increment, decrement } = useCounter(10)
</script>
```

### Composable Naming Convention

- Always prefix with `use`: `useCounter`, `useAuth`, `useCompanyPermissions`
- Return reactive refs and functions
- Keep composables focused on single responsibility

---

## Common Patterns

### Toggle State
```vue
<template>
  <button @click="toggle">Toggle</button>
  <div v-if="isOpen">Content</div>
</template>

<script setup lang="ts">
const isOpen = ref(false)

const toggle = () => {
  isOpen.value = !isOpen.value
}

// Or inline in template
// @click="isOpen = !isOpen"
</script>
```

### Loading State
```vue
<template>
  <div v-if="isLoading">Loading...</div>
  <div v-else-if="error">{{ error.message }}</div>
  <div v-else>{{ data }}</div>
</template>

<script setup lang="ts">
const isLoading = ref(false)
const error = ref<Error | null>(null)
const data = ref<Data | null>(null)

const fetchData = async () => {
  isLoading.value = true
  error.value = null

  try {
    data.value = await api.getData()
  } catch (e) {
    error.value = e as Error
  } finally {
    isLoading.value = false
  }
}
</script>
```

### Form Handling
```vue
<template>
  <form @submit.prevent="handleSubmit">
    <input v-model="formData.name" />
    <span v-if="errors.name">{{ errors.name }}</span>
    <input v-model="formData.email" />
    <span v-if="errors.email">{{ errors.email }}</span>
    <button type="submit" :disabled="isSubmitting">Submit</button>
  </form>
</template>

<script setup lang="ts">
const formData = reactive({
  name: '',
  email: '',
})

const errors = reactive({
  name: '',
  email: '',
})

const isSubmitting = ref(false)

const validate = (): boolean => {
  errors.name = formData.name ? '' : 'Name is required'
  errors.email = formData.email ? '' : 'Email is required'
  return !errors.name && !errors.email
}

const handleSubmit = async () => {
  if (!validate()) return

  isSubmitting.value = true
  try {
    await api.submit(formData)
  } finally {
    isSubmitting.value = false
  }
}
</script>
```

### Debounced Search
```vue
<template>
  <input v-model="searchQuery" placeholder="Search..." />
  <ul>
    <li v-for="result in results" :key="result.id">{{ result.name }}</li>
  </ul>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'

const searchQuery = ref('')
const results = ref([])

const debouncedSearch = useDebounceFn(async (query: string) => {
  if (!query) {
    results.value = []
    return
  }
  results.value = await api.search(query)
}, 300)

watch(searchQuery, (query) => {
  debouncedSearch(query)
})
</script>
```
