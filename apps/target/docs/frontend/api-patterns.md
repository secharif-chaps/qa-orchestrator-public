# API Patterns Guide

This guide explains how to create and use API calls, queries, and mutations following our established patterns using Pinia Colada.

## Table of Contents

1. [Directory Structure](#directory-structure)
2. [The `useApi` Composable](#the-useapi-composable)
3. [Creating API Functions](#creating-api-functions)
4. [Creating Queries](#creating-queries)
5. [Creating Mutations](#creating-mutations)
6. [Using in Vue Components](#using-in-vue-components)
7. [Best Practices](#best-practices)

## Directory Structure

Our API-related code is structured as follows:

```
pwa/
└── api/
    ├── mutations/     # Mutation definitions using defineMutation
    ├── queries/       # Query definitions using defineQueryOptions
    ├── api.ts         # The central useApi composable
    └── {resource}.ts  # API functions that make HTTP calls
```

- **`pwa/api/api.ts`**: Exports the `useApi` composable, a centralized client for making HTTP requests.
- **`pwa/api/{resource}.ts`**: Contains functions that directly interact with the API for a specific resource (e.g., `watchFile.ts`).
- **`pwa/api/queries/{resource}.ts`**: Defines Pinia Colada queries related to a specific resource.
- **`pwa/api/mutations/{resource}.ts`**: Defines Pinia Colada mutations related to a specific resource.

## The `useApi` Composable

The `useApi` composable, located in `pwa/api/api.ts`, is a wrapper around `useFetch` that provides a consistent way to interact with our API. It automatically handles:

- **Authentication**: Adds the necessary authentication headers to each request.
- **Content-Type**: Sets the appropriate `Content-Type` header for JSON-LD and PATCH requests.
- **Mercure Subscriptions**: Discovers and manages Mercure real-time updates.
- **Error Handling**: Throws appropriate errors on failed requests.

## API Naming Conventions

To maintain consistency and clarity across our API interactions, we follow specific naming conventions for common CRUD (Create, Read, Update, Delete) operations. These conventions apply to the functions defined in `pwa/api/{resource}.ts` files.

- **`getItem{ResourceName}`**: Used for fetching a single resource by its ID.
  - Example: `getItemWatchFile(id: string)`
- **`getCollection{ResourceName}`**: Used for fetching a collection of resources, often with filtering, sorting, and pagination.
  - Example: `getCollectionWatchFile(params: object)`
- **`create{ResourceName}`**: Used for creating a new resource.
  - Example: `createWatchFile(data: Partial<Resource>)`
- **`update{ResourceName}`**: Used for updating an existing resource by its ID.
  - Example: `updateWatchFile(id: string, data: Partial<Resource>)`
- **`delete{ResourceName}`**: Used for deleting a resource by its ID.
  - Example: `deleteWatchFile(id: string)`

## Creating API Functions

API functions are responsible for making HTTP calls. They should be placed in a file named after the resource they handle (e.g., `pwa/api/watchFile.ts`).

### Example: `pwa/api/watchFile.ts`

```typescript
import { useApi } from '@/api/api'
import type { Conversation } from '@/types/conversation'
import type { JsonLdCollection } from '@/types/jsonld'
import type { WatchFile } from '@/types/watchFile'
import type { SortOrder } from '@owlint/feathers-vue'

export const getItemWatchFile = async (id: string) => {
  const response = await useApi().get<WatchFile>(`/watch_files/${id}`)
  return response.data
}

export const getCollectionWatchFile = async ({
  sortBy,
  sortOrder,
  ...params
}: {
  sortBy: string
  sortOrder: SortOrder
  page?: number
  itemsPerPage?: number
}) => {
  const response = await useApi().get<JsonLdCollection<WatchFile>>('/watch_files', {
    query: {
      [`sort[${sortBy}]`]: sortOrder.toLowerCase(),
      ...params,
    },
  })

  return {
    items: response.data.member,
    totalItems: response.data.totalItems,
  }
}

export const createWatchFile = async (data: Partial<WatchFile>) => {
  const response = await useApi().post<WatchFile>('/watch_files', {
    ...data,
    '@type': 'WatchFile',
  })
  return response.data
}

export const updateWatchFile = async (id: string, data: Partial<WatchFile>) => {
  const response = await useApi().patch<WatchFile>(`/watch_files/${id}`, data)
  return response.data
}

export const deleteWatchFile = async (id: string) => {
  await useApi().delete(`/watch_files/${id}`)
}
```

### Key Points:

- Import `useApi` from `@/api/api`.
- Functions should be lean and focused on a single API endpoint.
- Return the `data` property from the response.
- Handle any data transformation needed for the application (e.g., extracting `member` and `totalItems` from a JSON-LD collection).

## Creating Queries

Queries are for fetching data. They use `defineQueryOptions` from Pinia Colada and are placed in the `pwa/api/queries/` directory.

### Example: `pwa/api/queries/watchFile.ts`

```typescript
import {
  getLastWatchFileConversation,
  getItemWatchFile,
  getCollectionWatchFile,
} from '@/api/watchFile'
import type { SortOrder } from '@owlint/feathers-vue'
import { defineQueryOptions } from '@pinia/colada'

export const WATCH_FILE_QUERY_KEYS = {
  root: ['watchFiles'] as const,
  byId: (id: string) => [...WATCH_FILE_QUERY_KEYS.root, id] as const,
  withFilters: (filters: Record<string, any>) =>
    [...WATCH_FILE_QUERY_KEYS.root, { filters }] as const,
}

export const getItemWatchFileQuery = defineQueryOptions(({ id }: { id: string }) => ({
  key: WATCH_FILE_QUERY_KEYS.byId(id),
  query: () => getItemWatchFile(id),
}))

export const getCollectionWatchFileQuery = defineQueryOptions(
  (filters: { sortBy: string; sortOrder: SortOrder; page?: number; itemsPerPage?: number }) => ({
    key: WATCH_FILE_QUERY_KEYS.withFilters(filters),
    query: () => getCollectionWatchFile(filters),
    enabled: !!filters.sortBy,
  }),
)
```

### Query Key Patterns:

- Use a consistent naming convention: `RESOURCE_QUERY_KEYS`.
- Structure keys hierarchically for easy invalidation.
- Include parameters in keys to ensure proper caching.

## Creating Mutations

Mutations are for creating, updating, or deleting data. They use `defineMutation` from Pinia Colada and are placed in the `pwa/api/mutations/` directory.

### Example: `pwa/api/mutations/watchFile.ts`

```typescript
import { updateWatchFile, toggleWatchFileFavorite } from '@/api/watchFile'
import type { WatchFile } from '@/types/watchFile'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { WATCH_FILE_QUERY_KEYS } from '~/api/queries/watchFile'

export const useUpdateWatchFile = defineMutation(() => {
  const toast = useToast()
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const watchFileStore = useWatchFileStore()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ id, data }: { id: string; data: Partial<WatchFile> }) => updateWatchFile(id, data),
    onError() {
      const errorMessage = watchFileStore.getError('name') || t('watch_files.title.error.generic')
      toast.error(t('watch_files.title.error.toast_title'), errorMessage)
    },
    onSuccess({ id }) {
      toast.success(t('watch_files.title.success.updated'))
      queryCache.invalidateQueries({
        key: WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters),
      })
      queryCache.invalidateQueries({
        key: WATCH_FILE_QUERY_KEYS.byId(id),
      })
    },
  })
  return { ...mutation, updateTask: mutate }
})

export const useToggleWatchFileFavorite = () => {
  const toast = useToast()
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const watchFileStore = useWatchFileStore()

  const { mutate, ...mutation } = useMutation({
    mutation: (watchFile: WatchFile) =>
      toggleWatchFileFavorite(watchFile.id, !!watchFile.isFavorite),
    onMutate: ({ id }) => {
      // Optimistic update logic here
    },
    onError: () => {
      // Revert on error
    },
    onSettled() {
      queryCache.invalidateQueries({
        key: WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters),
      })
    },
  })
  return {
    ...mutation,
    toggleFavorite: mutate,
  }
}
```

### Mutation Patterns:

- Mutations are the place for side effects like toast notifications and query invalidation.
- Use `onSuccess`, `onError`, and `onSettled` to handle the mutation lifecycle.
- Implement optimistic updates with `onMutate` for a better user experience.

## Using in Vue Components

The way you use queries and mutations in your Vue components remains the same.

### Using Queries

```vue
<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { getCollectionWatchFileQuery } from '@/api/queries/watchFile'
import { ref } from 'vue'

const page = ref(1)
const {
  data: watchFiles,
  isLoading,
  error,
} = useQuery(getCollectionWatchFileQuery, () => ({
  sortBy: 'createdAt',
  sortOrder: 'desc',
  page: page.value,
  itemsPerPage: 10,
}))
</script>
```

### Using Mutations

```vue
<script setup lang="ts">
import { useUpdateWatchFile } from '@/api/mutations/watchFile'

const { updateTask, isLoading, error } = useUpdateWatchFile()

const handleUpdate = async () => {
  await updateTask({ id: 'some-id', data: { name: 'New Name' } })
}
</script>
```

## Best Practices

- **Separation of Concerns**: Keep API functions, queries, and mutations in their respective files.
- **Type Safety**: Use TypeScript to type all API responses, query parameters, and mutation payloads.
- **Error Handling**: Let Pinia Colada handle errors in queries and mutations. Implement user-facing error handling (e.g., toasts) in the mutation definitions.
- **Query Invalidation**: Invalidate queries in the `onSuccess` or `onSettled` hooks of your mutations to ensure data is always fresh.
- **Optimistic Updates**: Use `onMutate` to provide a snappy UI, but always have a solid `onError` handler to revert changes if the API call fails.
