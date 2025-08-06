# API Patterns Guide

This guide explains how to create and use API calls, queries, and mutations following our established patterns using Pinia Colada.

## Table of Contents

1. [Directory Structure](#directory-structure)
2. [Creating API Functions](#creating-api-functions)
3. [Creating Queries](#creating-queries)
4. [Creating Mutations](#creating-mutations)
5. [Using in Vue Components](#using-in-vue-components)
6. [Best Practices](#best-practices)

## Directory Structure

```
src/
├── api/           # API functions that make HTTP calls
├── queries/       # Query definitions using defineQueryOptions
├── mutations/     # Mutation definitions using defineMutation
└── types/         # TypeScript interfaces
```

## Creating API Functions

API functions are pure functions that make HTTP calls using the `apiClient`.

### Example: `/src/api/companies.ts`

```typescript
import { type Company } from '@/types/company'
import { apiClient } from './client'
import type { PaginatedResponse } from '@/types/pagination'

// GET single resource
export const getCompanyById = async (companyId: string) => {
  const response = await apiClient.get<Company>(`/companies/${companyId}`)
  return response
}

// GET list with filters
export const getCompanies = async (filters: { page: number; size: number; name: string }) => {
  const params = new URLSearchParams({
    page: filters.page.toString(),
    size: filters.size.toString(),
  })

  if (filters.name) {
    params.append('name', filters.name)
  }

  const response = await apiClient.get<PaginatedResponse<Company>>(
    `/companies?${params.toString()}`,
  )
  return response
}

// POST create resource
export const createCompany = async (company: CompanyCreate) => {
  const response = await apiClient.post<Company>('/companies', company)
  return response
}

// PUT update resource (full replacement)
export const updateCompany = async (id: string, company: CompanyUpdate) => {
  const response = await apiClient.put<Company>(`/companies/${id}`, company)
  return response
}

// PATCH update resource (partial update)
export const patchCompany = async (id: string, company: Partial<CompanyUpdate>) => {
  const response = await apiClient.patch<Company>(`/companies/${id}`, company)
  return response
}

// DELETE resource
export const deleteCompany = async (id: string) => {
  await apiClient.delete(`/companies/${id}`)
}
```

### Key Points:
- Always return typed responses
- Use URLSearchParams for query parameters
- Keep functions pure and focused
- Handle different HTTP methods appropriately
- Use PUT for full resource replacement
- Use PATCH for partial resource updates

## Creating Queries

Queries are for fetching data. They use `defineQueryOptions` from Pinia Colada.

### Example: `/src/queries/companies.ts`

```typescript
import { defineQueryOptions } from '@pinia/colada'
import { getCompanies, getCompanyById } from '@/api/companies'

// Define query keys for cache management
export const COMPANY_QUERY_KEYS = {
  root: ['companies'] as const,
  byId: (id: string) => [...COMPANY_QUERY_KEYS.root, id] as const,
  withFilters: (filters: { page: number; size: number; name: string }) =>
    [...COMPANY_QUERY_KEYS.root, { filters }] as const,
}

// Query for single company
export const companyByIdQuery = defineQueryOptions(({ id }: { id: string }) => ({
  key: COMPANY_QUERY_KEYS.byId(id),
  query: () => getCompanyById(id),
}))

// Query for company list with filters
export const companiesQuery = defineQueryOptions(
  ({ filters }: { filters: { page: number; size: number; name: string } }) => ({
    key: COMPANY_QUERY_KEYS.withFilters(filters),
    query: () => getCompanies(filters),
  }),
)

// Query without parameters
export const currentWorkspaceQuery = defineQueryOptions(() => ({
  key: ['workspace', 'current'],
  query: () => getCurrentWorkspace(),
}))
```

### Query Key Patterns:
- Use consistent naming: `RESOURCE_QUERY_KEYS`
- Structure keys hierarchically
- Include parameters in keys for proper caching

## Creating Mutations

Mutations are for creating, updating, or deleting data. They use `defineMutation` and can include reactive state.

### Example: `/src/mutations/tasks.ts`

```typescript
import { ref } from 'vue'
import { defineMutation, useMutation } from '@pinia/colada'
import { createTask, restartTask } from '@/api/tasks'
import type { TaskCreate, TaskType } from '@/types/task'

// Mutation with reactive refs
export const useCreateTask = defineMutation(() => {
  // Define reactive state
  const companyId = ref<number | null>(null)
  const taskType = ref<string>('')

  const { mutate, ...mutation } = useMutation({
    mutation: (task: TaskCreate) => createTask(task),
  })

  return {
    ...mutation,
    // Custom method that uses the refs
    createTask: () => {
      if (!companyId.value || !taskType.value) {
        throw new Error('Company ID and task type are required')
      }
      return mutate({
        company_id: companyId.value,
        type: taskType.value as TaskType,
        status: 'pending',
      })
    },
    // Expose refs for v-model binding
    companyId,
    taskType,
    // Expose original mutate for flexibility
    mutate,
  }
})

// Simple mutation without refs
export const useDeleteCompany = defineMutation(() => {
  const { mutate, ...mutation } = useMutation({
    mutation: (id: string) => deleteCompany(id),
  })

  return {
    ...mutation,
    deleteCompany: mutate,
  }
})
```

### Mutation Patterns:
- Use refs for form inputs
- Provide custom methods with validation
- Expose refs for v-model binding
- Always return the mutation state

## Using in Vue Components

### Using Queries

```vue
<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { companiesQuery, companyByIdQuery } from '@/queries/companies'
import { ref } from 'vue'

// Query with reactive parameters
const page = ref(1)
const size = ref(10)
const searchName = ref('')

const { data: companies, isLoading, error } = useQuery(
  companiesQuery,
  () => ({
    filters: {
      page: page.value,
      size: size.value,
      name: searchName.value,
    },
  }),
)

// Query with route params
import { useRoute } from 'vue-router'
const route = useRoute()

const { data: company } = useQuery(
  companyByIdQuery,
  () => ({ id: route.params.id as string }),
)

// Simple query without parameters
import { currentWorkspaceQuery } from '@/queries/workspace'

const { data: workspace } = useQuery(currentWorkspaceQuery, () => ({}))
</script>

<template>
  <div v-if="isLoading">Loading...</div>
  <div v-else-if="error">Error: {{ error.message }}</div>
  <div v-else>
    <!-- Use data -->
    <div v-for="company in companies?.data" :key="company.id">
      {{ company.name }}
    </div>
  </div>
</template>
```

### Using Mutations

```vue
<script setup lang="ts">
import { useCreateTask } from '@/mutations/tasks'
import { toast } from '@/utils/toast'

// Get mutation with refs
const { createTask, companyId, taskType, isLoading, error } = useCreateTask()

// Set initial values
companyId.value = 123

// Handle form submission
const handleSubmit = async () => {
  try {
    await createTask()
    toast.success('Task created successfully')
    // Optionally invalidate queries
  } catch (error) {
    toast.error('Failed to create task')
  }
}
</script>

<template>
  <form @submit.prevent="handleSubmit">
    <select v-model="taskType">
      <option value="profile">Profile</option>
      <option value="digital">Digital</option>
    </select>
    
    <button :disabled="isLoading" type="submit">
      {{ isLoading ? 'Creating...' : 'Create Task' }}
    </button>
    
    <div v-if="error" class="error">
      {{ error.message }}
    </div>
  </form>
</template>
```

## Best Practices

### 1. **Type Everything**
```typescript
// Always define types for API responses
export const getCompany = async (id: string): Promise<Company> => {
  return apiClient.get<Company>(`/companies/${id}`)
}
```

### 2. **Consistent Query Keys**
```typescript
// Use a consistent pattern for query keys
export const RESOURCE_QUERY_KEYS = {
  root: ['resource'] as const,
  byId: (id: string) => [...RESOURCE_QUERY_KEYS.root, id] as const,
  // Add more specific keys as needed
}
```

### 3. **Error Handling**
```typescript
// Let Pinia Colada handle errors, don't catch in API functions
export const createCompany = async (data: CompanyCreate) => {
  // Don't wrap in try/catch
  return apiClient.post<Company>('/companies', data)
}
```

### 4. **Optimistic Updates**
```typescript
// Use mutation options for optimistic updates
const { mutate } = useMutation({
  mutation: updateCompany,
  onMutate: (variables) => {
    // Optimistically update the cache
  },
  onError: (error, variables, context) => {
    // Revert on error
  },
})
```

### 5. **Query Invalidation**
```typescript
import { useQueryCache } from '@pinia/colada'

const queryCache = useQueryCache()

// After mutation success
queryCache.invalidateQueries({ 
  key: COMPANY_QUERY_KEYS.root 
})
```

### 6. **Loading States**
```vue
<template>
  <!-- Always handle loading, error, and empty states -->
  <div v-if="isLoading">
    <LoadingSpinner />
  </div>
  <div v-else-if="error">
    <ErrorMessage :error="error" />
  </div>
  <div v-else-if="!data || data.length === 0">
    <EmptyState />
  </div>
  <div v-else>
    <!-- Main content -->
  </div>
</template>
```

## Common Patterns

### Pagination
```typescript
// Query definition
export const paginatedQuery = defineQueryOptions(
  ({ page, size }: { page: number; size: number }) => ({
    key: ['items', { page, size }],
    query: () => getItems({ page, size }),
  })
)

// Usage
const page = ref(1)
const { data } = useQuery(paginatedQuery, () => ({
  page: page.value,
  size: 20,
}))
```

### Search with Debounce
```typescript
import { refDebounced } from '@vueuse/core'

const search = ref('')
const debouncedSearch = refDebounced(search, 300)

const { data } = useQuery(searchQuery, () => ({
  query: debouncedSearch.value,
}))
```

### Dependent Queries
```typescript
const userId = ref<string | null>(null)

// Only run query when userId is available
const { data: userPosts } = useQuery(
  userPostsQuery,
  () => ({ userId: userId.value! }),
  {
    enabled: computed(() => !!userId.value),
  }
)
```

## Summary

1. **API functions** handle HTTP calls and return typed data
2. **Queries** use `defineQueryOptions` for data fetching
3. **Mutations** use `defineMutation` for data modifications
4. **Components** use `useQuery` and mutation hooks
5. **Always** handle loading and error states
6. **Never** make direct API calls in components

This pattern ensures:
- Consistent data fetching
- Automatic caching and invalidation
- Type safety throughout
- Better developer experience
- Easier testing and maintenance