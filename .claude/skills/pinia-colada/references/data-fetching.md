# Data Fetching with Pinia Colada

## Architecture Overview

```
API Functions (src/api/)
        ↓
Query Definitions (src/queries/)  ←→  Mutations (src/mutations/)
        ↓
    Components (useQuery / useMutation)
```

**Key Principle**: Never call API functions directly in components. Always use queries/mutations.

---

## API Functions (`src/api/`)

Pure functions that make HTTP calls:

```typescript
// src/api/companies.ts
import { apiClient } from './client'
import type { Company, CompanyCreate, PaginatedResponse } from '@/types'

// GET single resource
export async function getCompanyById(id: number): Promise<Company> {
  return apiClient.get<Company>(`/companies/${id}`)
}

// GET list with filters
export async function getCompanies(filters: {
  page: number
  size: number
  name?: string
}): Promise<PaginatedResponse<Company>> {
  const params = new URLSearchParams({
    page: filters.page.toString(),
    size: filters.size.toString(),
  })
  if (filters.name) {
    params.set('name', filters.name)
  }
  return apiClient.get<PaginatedResponse<Company>>(`/companies?${params}`)
}

// POST create
export async function createCompany(data: CompanyCreate): Promise<Company> {
  return apiClient.post<Company>('/companies', data)
}

// PUT full update
export async function updateCompany(id: number, data: Company): Promise<Company> {
  return apiClient.put<Company>(`/companies/${id}`, data)
}

// PATCH partial update
export async function patchCompany(
  id: number,
  data: Partial<Company>
): Promise<Company> {
  return apiClient.patch<Company>(`/companies/${id}`, data)
}

// DELETE
export async function deleteCompany(id: number): Promise<void> {
  await apiClient.delete(`/companies/${id}`)
}
```

**Rules**:
- Always return typed responses
- Use URLSearchParams for query parameters
- Keep functions pure and focused
- Let Pinia Colada handle errors (don't wrap in try/catch)

---

## Query Definitions (`src/queries/`)

Define queries using `defineQueryOptions`:

```typescript
// src/queries/companies.ts
import { defineQueryOptions } from '@pinia/colada'
import { getCompanies, getCompanyById } from '@/api/companies'

// Query keys for cache management
export const COMPANY_QUERY_KEYS = {
  root: ['companies'] as const,
  byId: (id: number) => [...COMPANY_QUERY_KEYS.root, id] as const,
  list: (filters: { page: number; size: number }) =>
    [...COMPANY_QUERY_KEYS.root, 'list', { filters }] as const,
}

// Single company query
export const companyByIdQuery = defineQueryOptions(({ id }: { id: number }) => ({
  key: COMPANY_QUERY_KEYS.byId(id),
  query: () => getCompanyById(id),
}))

// Paginated list query
export const companiesQuery = defineQueryOptions(
  ({ page, size }: { page: number; size: number }) => ({
    key: COMPANY_QUERY_KEYS.list({ page, size }),
    query: () => getCompanies({ page, size }),
  })
)
```

**Query Key Pattern**:
- Hierarchical structure for cache invalidation
- Include parameters that affect the data
- Use `as const` for type safety

---

## Using Queries in Components

### Basic Query Usage
```vue
<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { Alert } from '@owlint/feathers-vue'

const props = defineProps<{ companyId: number }>()

const {
  data: company,
  isLoading,
  error,
  refetch,
} = useQuery(() => companyByIdQuery({ id: props.companyId }))
</script>

<template>
  <!-- ALWAYS handle all states -->
  <div v-if="isLoading" class="flex items-center gap-2">
    <i class="fa fa-spinner fa-spin"></i>
    Loading...
  </div>

  <Alert
    v-else-if="error"
    variant="danger"
    :title="error.message"
    icon="fa-exclamation-circle"
  />

  <div v-else-if="company">
    <h1>{{ company.name }}</h1>
    <p>{{ company.website }}</p>
  </div>

  <div v-else class="text-center py-8">
    <p>No company found</p>
  </div>
</template>
```

### Query with Reactive Parameters
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { useQuery } from '@pinia/colada'
import { companiesQuery } from '@/queries/companies'

const page = ref(1)
const size = ref(10)

// Single getter — Vue tracks page.value and size.value, refetches automatically
const { data: companies, isLoading } = useQuery(
  () => companiesQuery({ page: page.value, size: size.value })
)

function nextPage() {
  page.value++
}
</script>
```

### Query with Route Parameters
```vue
<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'

const route = useRoute()

const { data: company, isLoading } = useQuery(
  () => companyByIdQuery({ id: Number(route.params.id) })
)
</script>
```

### Conditional Query with `enabled`

Spread the query result and add `enabled` **inside the same getter** to keep it reactive:

```vue
<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { tasksByCompanyQuery } from '@/queries/tasks'

const props = defineProps<{ companyId: number | null }>()

// Only fetch when companyId is provided
const { data: company } = useQuery(() => ({
  ...companyByIdQuery({ id: props.companyId! }),
  enabled: props.companyId !== null,
}))

// Dependent query — only runs after company is loaded
const { data: tasks } = useQuery(() => ({
  ...tasksByCompanyQuery({ companyId: props.companyId! }),
  enabled: !!company.value,
}))
</script>
```

**Rule**: `enabled` and all other options live **inside the getter** — they are re-evaluated reactively on every render. Never extract them outside the getter.

---

## Mutations (`src/mutations/`)

Define mutations using `defineMutation`:

```typescript
// src/mutations/companies.ts
import { ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { createCompany, deleteCompany } from '@/api/companies'
import { COMPANY_QUERY_KEYS } from '@/queries/companies'
import type { CompanyCreate } from '@/types'

// Create company mutation
export const useCreateCompany = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: (data: CompanyCreate) => createCompany(data),
    onSuccess() {
      // Invalidate company list queries
      queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.root })
    },
  })

  return {
    ...mutation,
    createCompany: mutate,
  }
})

// Delete company mutation
export const useDeleteCompany = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: (id: number) => deleteCompany(id),
    onSuccess() {
      queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.root })
    },
  })

  return {
    ...mutation,
    deleteCompany: mutate,
  }
})
```

---

## Using Mutations in Components

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Button, Input, Alert } from '@owlint/feathers-vue'
import { useCreateCompany } from '@/mutations/companies'

const companyName = ref('')
const companyWebsite = ref('')

const { createCompany, isPending, error, isSuccess } = useCreateCompany()

async function handleSubmit() {
  await createCompany({
    name: companyName.value,
    website: companyWebsite.value,
  })

  if (isSuccess.value) {
    // Reset form or navigate
    companyName.value = ''
    companyWebsite.value = ''
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
    <Input
      id="name"
      v-model="companyName"
      label="Company Name"
      placeholder="Enter company name"
      required
    />

    <Input
      id="website"
      v-model="companyWebsite"
      label="Website"
      placeholder="https://example.com"
      required
    />

    <Alert
      v-if="error"
      variant="danger"
      :title="error.message"
      icon="fa-exclamation-circle"
    />

    <Button
      type="submit"
      label="Create Company"
      variant="primary"
      :loading="isPending"
      :disabled="isPending"
    />
  </form>
</template>
```

---

## Loading, Error, and Empty States

**ALWAYS handle all states**:

```vue
<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-8">
      <i class="fa fa-spinner fa-spin text-2xl"></i>
    </div>

    <!-- Error State -->
    <Alert
      v-else-if="error"
      variant="danger"
      title="Error loading data"
      :description="error.message"
      icon="fa-exclamation-circle"
      action="Retry"
      @click="refetch"
    />

    <!-- Empty State -->
    <div v-else-if="!data || data.length === 0" class="text-center py-12">
      <i class="fa fa-inbox text-4xl text-gray-400 mb-4"></i>
      <h3 class="text-lg font-semibold mb-2">No items found</h3>
      <p class="text-sm text-gray-600 mb-6">
        Get started by creating your first item
      </p>
      <Button
        v-if="canCreate"
        variant="primary"
        icon="fa-plus"
        label="Create Item"
        @click="handleCreate"
      />
    </div>

    <!-- Data State -->
    <div v-else>
      <!-- Render your data -->
    </div>
  </div>
</template>
```

---

## Best Practices

### Query Keys
- Use hierarchical structure
- Include all parameters that affect data
- Define in a central `QUERY_KEYS` object

### Cache Invalidation
- Invalidate on successful mutations
- Use broad invalidation (`root` key) when unsure
- Consider optimistic updates for better UX

### Error Handling
- Let Pinia Colada handle errors
- Display user-friendly error messages
- Provide retry actions

### Loading States
- Always show loading indicators
- Disable buttons during mutations
- Consider skeleton loaders for better UX

### Type Safety
- Type all API responses
- Use TypeScript generics
- Define interfaces for query parameters
