# Migration Guide: From Pinia Stores to useQuery

This guide explains how to migrate our data-fetching logic from the old pattern of using Pinia stores to the new, recommended approach using the `pwa/api/` directory structure and Pinia Colada's `useQuery` hook.

The primary goal is to separate **Client State** from **Server Cache**. Pinia stores will continue to manage client-side state (like UI state, filters, pagination), while Pinia Colada will handle server-side state (caching, fetching, and synchronization).

This guide uses the recent migration of the `SourcesList.vue` component as a real-world example.

---

## Migration Steps for Queries

Let's walk through migrating the sources list query from the `useSourcesStore` to the `useQuery` hook.

### Before: Fetching Logic Inside a Pinia Store

Previously, `useSourcesStore` was responsible for fetching the list of sources and managing the data, loading, and error states manually.

**`pwa/stores/source.ts` (Old Way)**

```typescript
export const useSourcesStore = defineStore('sources', () => {
  const sources = ref<Source[]>([])
  const totalItems = ref(0)
  const error = ref<string | null>(null)
  const page = ref(1)
  const itemsPerPage = ref(10)
  const isLoading = ref(false)

  async function fetchSources(watchFileId: string, newPage?: number, newItemsPerPage?: number) {
    isLoading.value = true
    error.value = null
    // ...
    try {
      const response = await api.get(/* ... */)
      sources.value = response.data.member
      totalItems.value = response.data.totalItems
    } catch (e) {
      error.value = e
    } finally {
      isLoading.value = false
    }
  }

  return {
    sources,
    totalItems,
    isLoading,
    error,
    page,
    itemsPerPage,
    fetchSources,
  }
})
```

The `SourcesList.vue` component would call the `fetchSources` action and use `storeToRefs` to get the data.

**`pwa/components/watchFiles/SourcesList.vue` (Old Way)**

```vue
<script setup lang="ts">
import { useSourcesStore } from '@/stores/source'
import { storeToRefs } from 'pinia'
import { onMounted, watch } from 'vue'

const sourcesStore = useSourcesStore()
const { sources, isLoading, error, totalItems, itemsPerPage, page } = storeToRefs(sourcesStore)

const fetchSources = () => {
  sourcesStore.fetchSources(props.watchFileId, page.value, itemsPerPage.value)
}

onMounted(() => {
  fetchSources()
})

watch(page, () => {
  fetchSources()
})
</script>

<template>
  <SectionListHeader @refresh="fetchSources" />
  <!-- ... -->
</template>
```

### After: Migrating to `useQuery`

Here is the step-by-step refactoring based on your changes.

#### Step 1: Create the API Function

The API call was extracted from the store into a dedicated function in `pwa/api/sources.ts`.

**`pwa/api/sources.ts` (New Way)**

```typescript
import { useApi } from '@/composables/useApi'
import type { JsonLdCollection } from '~/types/jsonld'
import type { Source } from '~/types/source'

export const getCollectionSource = async (
  watchFileId: string,
  params: { page?: number; itemsPerPage?: number },
) => {
  const response = await useApi().get<JsonLdCollection<Source>>(
    `/watch_files/${watchFileId}/sources`,
    { query: params },
  )

  return {
    items: response.data.member,
    totalItems: response.data.totalItems,
  }
}
```

#### Step 2: Define the Query Options

A reusable query definition was created in `pwa/api/queries/sources.ts`.

**`pwa/api/queries/sources.ts` (New Way)**

```typescript
import { getCollectionSource } from '@/api/sources'
import { defineQueryOptions } from '@pinia/colada'

export const SOURCES_QUERY_KEYS = {
  root: ['sources'] as const,
  withFilters: (filters: Record<string, any>) => [...SOURCES_QUERY_KEYS.root, { filters }] as const,
}

export const getCollectionSourceQuery = defineQueryOptions(
  (filters: { watchFileId: string; page?: number; itemsPerPage?: number }) => ({
    key: SOURCES_QUERY_KEYS.withFilters(filters),
    query: () =>
      getCollectionSource(filters.watchFileId, {
        page: filters.page,
        itemsPerPage: filters.itemsPerPage,
      }),
    enabled: !!filters.watchFileId,
  }),
)
```

#### Step 3: Refactor the Vue Component

The component now uses the `useQuery` hook, which greatly simplifies the script. The `onMounted` and `watch` hooks for fetching are no longer needed.

**`pwa/components/watchFiles/SourcesList.vue` (New Way)**

```vue
<script setup lang="ts">
import { useSourcesStore } from '@/stores/source'
import { useQuery } from '@pinia/colada'
import { storeToRefs } from 'pinia'
import { computed } from 'vue'
import { getCollectionSourceQuery } from '~/api/queries/sources'

const props = defineProps<{ watchFileId: string }>()
const sourcesStore = useSourcesStore()

// The store is still used for UI state (pagination)
const { itemsPerPage, page } = storeToRefs(sourcesStore)

const { data, isLoading, refetch, error } = useQuery(getCollectionSourceQuery, () => ({
  watchFileId: props.watchFileId,
  page: page.value,
  itemsPerPage: itemsPerPage.value,
}))

// Data is now derived from the query result
const sources = computed(() => data.value?.items ?? [])
const totalItems = computed(() => data.value?.totalItems ?? 0)
</script>

<template>
  <!-- The refresh event now calls the refetch function from useQuery -->
  <SectionListHeader @refresh="refetch()" />
  <!-- ... -->
</template>
```

#### Step 4: Clean Up the Pinia Store

Finally, the `useSourcesStore` was cleaned up. The state and actions related to data fetching were removed, but it continues to manage UI state like pagination (`page`, `itemsPerPage`).

**`pwa/stores/source.ts` (After Cleanup)**

```typescript
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useSourcesStore = defineStore('sources', () => {
  // State related to the server cache is removed.
  // State for UI controls remains.
  const page = ref(1)
  const itemsPerPage = ref(10)

  function reset() {
    page.value = 1
    itemsPerPage.value = 10
  }

  return {
    // state
    page,
    itemsPerPage,
    // methods
    reset,
  }
})
```
