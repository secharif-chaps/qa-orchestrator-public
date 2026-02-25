---
name: pinia-colada
description: Data fetching with Pinia Colada queries and mutations. Use when fetching API data, creating/updating resources, handling loading states, or managing server cache. Never call API functions directly in components.
allowed-tools: Read, Write, Edit, Glob, Grep
---

# Data Fetching with Pinia Colada

**CRITICAL**: Never call API functions directly in components. Always use queries/mutations.

## Architecture

```
API Functions (src/api/)
        ↓
Query Definitions (src/queries/)  ←→  Mutations (src/mutations/)
        ↓
    Components (useQuery / useMutation)
```

## Query Definition

```typescript
// src/queries/companies.ts
import { defineQueryOptions } from '@pinia/colada'
import { getCompanyById } from '@/api/companies'

export const COMPANY_QUERY_KEYS = {
  root: ['companies'] as const,
  byId: (id: number) => [...COMPANY_QUERY_KEYS.root, id] as const,
}

export const companyByIdQuery = defineQueryOptions(({ id }: { id: number }) => ({
  key: COMPANY_QUERY_KEYS.byId(id),
  query: () => getCompanyById(id),
}))
```

## Using in Components

```vue
<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'

const props = defineProps<{ companyId: number }>()

const { data: company, isLoading, error } = useQuery(
  companyByIdQuery,
  () => ({ id: props.companyId })
)
</script>

<template>
  <!-- ALWAYS handle all states -->
  <div v-if="isLoading">Loading...</div>
  <Alert v-else-if="error" variant="danger" :title="error.message" />
  <div v-else-if="company">{{ company.name }}</div>
  <div v-else>No data found</div>
</template>
```

## Mutations

```typescript
// src/mutations/companies.ts
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'

export const useCreateCompany = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: (data: CompanyCreate) => createCompany(data),
    onSuccess() {
      queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.root })
    },
  })

  return { ...mutation, createCompany: mutate }
})
```

## Required State Handling

Always handle: Loading → Error → Empty → Data

## Documentation

- [data-fetching.md](data-fetching.md) - Architecture and patterns
- [examples.md](examples.md) - Pagination, optimistic UI, cache utilities
