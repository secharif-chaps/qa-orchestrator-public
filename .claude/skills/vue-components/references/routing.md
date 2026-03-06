# Vue Router & File-Based Routing

## File-Based Routing with unplugin-vue-router

Routes are automatically generated from `src/pages/` directory structure.

---

## Basic Patterns

```
src/pages/
├── index.vue                    → "/"
├── about.vue                    → "/about"
├── users/
│   ├── index.vue               → "/users"
│   ├── [id].vue                → "/users/:id"
│   ├── [id]/
│   │   └── edit.vue            → "/users/:id/edit"
│   └── create.vue              → "/users/create"
└── [...path].vue               → "/*" (catch-all)
```

### Dynamic Parameters
```
[id].vue           → /users/:id      (required param)
[[id]].vue         → /users/:id?     (optional param)
[...slug].vue      → /users/*        (catch-all)
[[...slug]].vue    → /users/*?       (optional catch-all)
```

### Advanced Filename Patterns
```
# Repeatable params (matches /posts/some/nested/path)
posts.[[slug]]+.vue    → /posts/:slug+    (one or more segments)

# Multiple sub-segments in a single file
@[username].vue        → /@:username      (e.g. /@posva)
with-[name]-[lastName].vue → /with-:name-:lastName
```

---

## Route Groups

Organize files without affecting URLs using parentheses:

```
src/pages/
├── (admin)/
│   ├── dashboard.vue          → "/dashboard"
│   └── users.vue              → "/users"
├── (public)/
│   ├── login.vue              → "/login"
│   └── register.vue           → "/register"
```

---

## Breaking Out of Layouts

Use dot notation to bypass parent layouts:

```
src/pages/
├── users.vue                  → Layout for /users/*
├── users/
│   ├── index.vue             → Uses users.vue layout
│   └── [id].vue              → Uses users.vue layout
└── users.create.vue          → "/users/create" (NO layout)
```

---

## definePage() — Customize Route Properties

Use `definePage()` inside `<script setup>` to customize the route's `meta`, `name`, `path`, `alias`, etc:

```vue
<script setup lang="ts">
definePage({
  name: 'custom-route-name',
  meta: {
    requiresAuth: true,
    permissions: ['company.view'],
    title: 'Company Details',
  },
  alias: ['/old-path'],
})
</script>
```

> **Note**: `definePage()` and `<route lang="yaml">` are both supported. Prefer `<route lang="yaml">` for simple meta, use `definePage()` when you need dynamic or programmatic route configuration.

---

## Route Meta with YAML Block

Define route metadata in a `<route>` block:

```vue
<template>
  <div>Page content</div>
</template>

<route lang="yaml">
meta:
  permissions:
    - company.view
    - company.create
  requiresAuth: true
  title: 'Company Details'
</route>

<script setup lang="ts">
// Page logic
</script>
```

### Available Meta Properties

| Property | Type | Description |
|----------|------|-------------|
| `requiresAuth` | boolean | Requires authenticated user |
| `permissions` | string[] | Required permissions (OR logic) |
| `title` | string | Page title |
| `layout` | string | Layout component name |

### Permission Logic

Permissions use **OR** logic - user needs **any one** of the listed permissions:

```yaml
meta:
  permissions:
    - admin.organizations    # OR
    - organization.write     # User needs either permission
```

---

## Navigation

### Programmatic Navigation
```vue
<script setup lang="ts">
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()

// Navigate by name (preferred)
function goToCompany(id: number) {
  router.push({
    name: '/companies/[id]',
    params: { id },
  })
}

// Navigate with query params
function goToSearch(query: string) {
  router.push({
    name: '/search',
    query: { q: query },
  })
}

// Navigate back
function goBack() {
  router.back()
}

// Replace current entry
function replaceRoute() {
  router.replace({ name: '/dashboard' })
}
</script>
```

### RouterLink Component
```vue
<template>
  <!-- Basic link -->
  <RouterLink :to="{ name: '/companies' }">
    Companies
  </RouterLink>

  <!-- With params -->
  <RouterLink
    :to="{
      name: '/companies/[id]',
      params: { id: company.id }
    }"
  >
    {{ company.name }}
  </RouterLink>

  <!-- With query -->
  <RouterLink
    :to="{
      name: '/search',
      query: { category: 'tech' }
    }"
  >
    Tech Companies
  </RouterLink>

  <!-- Active class handling -->
  <RouterLink
    :to="{ name: '/dashboard' }"
    active-class="bg-primary text-primary-content"
  >
    Dashboard
  </RouterLink>
</template>
```

---

## Accessing Route Information

```vue
<script setup lang="ts">
import { useRoute } from 'vue-router'

// Pass the route name for stricter types on params
const route = useRoute('/companies/[companyId]')

// Route params (typed from route name)
const companyId = computed(() => Number(route.params.companyId))

// Query params
const searchQuery = computed(() => route.query.q as string)
const page = computed(() => Number(route.query.page) || 1)

// Route name
const currentRoute = computed(() => route.name)

// Full path
const fullPath = computed(() => route.fullPath)
</script>
```

---

## Route Guards

### Navigation Guards in Pages
```vue
<script setup lang="ts">
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'

// Before leaving this route
onBeforeRouteLeave((to, from) => {
  if (hasUnsavedChanges.value) {
    const answer = window.confirm('You have unsaved changes. Leave anyway?')
    if (!answer) return false
  }
})

// When route params change (same component)
onBeforeRouteUpdate((to, from) => {
  // Fetch new data when id changes
  if (to.params.id !== from.params.id) {
    loadCompany(Number(to.params.id))
  }
})
</script>
```

---

## Best Practices

### File Naming
- **AVOID** files named `index.vue`, use groups: `(home).vue` instead
- **ALWAYS** use explicit param names: `[companyId].vue` not `[id].vue`
- Use descriptive names that match the route purpose

### Route Names
- Refer to `typed-router.d.ts` for route names and parameters
- Prefer named route locations over path strings
- Use TypeScript for type-safe navigation

### Typed Routes
```typescript
// Generated types in typed-router.d.ts
router.push({ name: '/companies/[id]', params: { id: 123 } })
//                                              ^ TypeScript knows this is required

// TypeScript error if param missing
router.push({ name: '/companies/[id]' })
//          ^ Error: params.id is required
```

### Page Component Structure
```vue
<template>
  <div class="flex flex-col gap-6">
    <!-- Page header -->
    <header class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ pageTitle }}</h1>
      <Button v-if="canCreate" label="Create" @click="handleCreate" />
    </header>

    <!-- Page content -->
    <main>
      <!-- Content here -->
    </main>
  </div>
</template>

<route lang="yaml">
meta:
  requiresAuth: true
  permissions:
    - company.view
  title: 'Companies'
</route>

<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'

const { canCreate } = useCompanyPermissions()

const pageTitle = 'Companies'
</script>
```

### Loading States During Navigation
```vue
<script setup lang="ts">
import { useRoute } from 'vue-router'
import { watch } from 'vue'

const route = useRoute()
const isLoading = ref(false)

// Show loading when route changes
watch(
  () => route.params.id,
  async (newId) => {
    isLoading.value = true
    try {
      await loadData(Number(newId))
    } finally {
      isLoading.value = false
    }
  },
  { immediate: true }
)
</script>
```
