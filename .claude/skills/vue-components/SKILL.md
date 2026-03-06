---
name: vue-components
description: Vue 3 component development with Composition API and TypeScript. Use when creating Vue components, pages, handling reactivity (ref, computed, watch), or setting up file-based routing. ALWAYS use script setup with TypeScript.
allowed-tools: Read, Write, Edit, Glob, Grep
metadata:
  author: chaps-e
  version: "1.0"
---

# Vue 3 Component Development

**CRITICAL**: Always use Composition API with `<script setup lang="ts">`. Never use Options API.

**Template first**: Place `<template>` above `<script setup>`.

## Component Structure

```vue
<template>
  <div class="flex flex-col gap-4">
    <h1>{{ displayTitle }}</h1>
    <Button label="Save" @click="handleSave" />
  </div>
</template>

<script setup lang="ts">
// 1. Imports
import { ref, computed, onMounted } from 'vue'
import { Button } from '@owlint/feathers-vue'

// 2. Props (interface + destructure with defaults)
interface Props {
  title: string
  count?: number
  variant?: 'primary' | 'secondary'
}

const { title, count, variant = 'primary' } = defineProps<Props>()

// 3. Emits
const emit = defineEmits<{
  save: [data: FormData]
  cancel: []
}>()

// 4. Composables
const { data, isLoading } = useQuery(...)

// 5. Reactive State
const isOpen = ref(false)

// 6. Computed
const displayTitle = computed(() => title.toUpperCase())

// 7. Methods (ALWAYS arrow functions)
const handleSave = () => {
  emit('save', formData)
}

// 8. Lifecycle (if needed)
onMounted(() => { ... })
</script>
```

## Reactivity Rules

```typescript
// Primitives: use ref
const count = ref(0);
const name = ref("");

// Objects: use ref (preferred) or reactive
const user = ref({ name: "", email: "" });

// Computed: derived state
const fullName = computed(() => `${first.value} ${last.value}`);

// Watch: side effects
watch(userId, async (newId) => {
  await fetchUser(newId);
});
```

## File-Based Routing

```
src/pages/
├── index.vue              → "/"
├── companies/
│   ├── index.vue         → "/companies"
│   └── [id].vue          → "/companies/:id"
└── (admin)/
    └── settings.vue      → "/settings"
```

Route meta with permissions:

```vue
<route lang="yaml">
meta:
  requiresAuth: true
  permissions:
    - company.view
</route>
```

## Documentation

- [component-structure.md](references/component-structure.md) - Full patterns
- [reactivity.md](references/reactivity.md) - Reactivity deep dive
- [routing.md](references/routing.md) - File-based routing details
