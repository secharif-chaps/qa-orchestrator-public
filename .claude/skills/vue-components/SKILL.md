---
name: vue-components
description: Vue 3 component structure, props, emits, reactivity, and routing standards. CRITICAL - Activates when creating OR modifying any .vue file. When modifying an existing component, ALWAYS verify the entire file follows these rules (props interface, emits interface, arrow functions, import order, template first). Fix any violations found.
allowed-tools: Read, Write, Edit, Glob, Grep
metadata:
  author: Lucas Gault
  version: "1.1"
---

# Vue 3 Component Development

**CRITICAL**: Always use Composition API with `<script setup lang="ts">`. Never use Options API.

**Template first**: Place `<template>` above `<script setup>`.

## Conformity Check (MANDATORY when modifying existing components)

When editing an existing `.vue` file, **ALWAYS verify the entire file** follows these rules before finishing:

1. `<template>` is above `<script setup>` (not below)
2. Props use `interface Props` + destructuring (not inline, not `withDefaults`)
3. Emits use `interface Emits` + `defineEmits<Emits>()` (not inline)
4. All functions are arrow functions (no `function` keyword)
5. Imports follow the order: Vue → Vuellar → External → Local
6. No Options API patterns (`export default`, `data()`, `methods:`)

If any violation is found, **fix it as part of your change**.

## Component Design Principles

- **Single responsibility**: one clear purpose per component
- **Composability**: build complex UIs by combining smaller components
- **Minimal props**: if a component needs many props, consider composition instead
- **State local-first**: keep state as local as possible, lift only when needed by multiple components
- **Clear interface**: explicit, well-documented props with sensible defaults
- **Encapsulation**: keep internal implementation private, expose only necessary APIs

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

// 3. Emits (ALWAYS use interface, like Props)
interface Emits {
  save: [data: FormData]
  cancel: []
}

const emit = defineEmits<Emits>()

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

## Documentations

- [component-structure.md](references/component-structure.md) - Full patterns
- [reactivity.md](references/reactivity.md) - Reactivity deep dive
- [routing.md](references/routing.md) - File-based routing details
