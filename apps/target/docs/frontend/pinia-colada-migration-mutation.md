# Migration Guide: From Pinia Actions to useMutation

This guide explains how to migrate our data-updating logic from the old pattern of using Pinia store actions to the new, recommended approach using the `pwa/api/` directory structure and Pinia Colada's `useMutation` hook.

The primary goal is to centralize side effects. Mutations defined with Pinia Colada become the single source of truth for creating, updating, or deleting data. They handle the API call, optimistic updates, caching, and user feedback (like toast notifications), while Pinia stores remain responsible for global client-side state.

This guide uses the `updateWatchFile` functionality as a real-world example.

---

## Migration Steps for Mutations

Let's walk through migrating a resource update action from a Pinia store to a `useMutation` hook.

### Before: Mutation Logic Inside a Pinia Store Action

Previously, a Pinia store might have been responsible for handling the update logic, including managing loading and error states directly within an action.

**`pwa/stores/watchFile.ts` (Old Way)**

```typescript
export const useWatchFileStore = defineStore('watch-file', () => {
    const isLoading = ref(false)
    const error = ref<any>(null)

    async function updateWatchFile(id: string, data: Partial<WatchFile>) {
        isLoading.value = true
        error.value = null
        try {
            const updatedFile = await useApi().patch(`/watch_files/${id}`, data)
            // Logic to update the file in the local state
            // Manually show a success toast
            useToast().success('File updated!')
            return updatedFile
        } catch (e) {
            error.value = e
            // Manually show an error toast
            useToast().error('Update failed!')
        } finally {
            isLoading.value = false
        }
    }

    return { isLoading, error, updateWatchFile }
})
```

The component would call the `updateWatchFile` action and handle the loading state.

**`pwa/components/some/Component.vue` (Old Way)**

```vue
<script setup lang="ts">
import { useWatchFileStore } from '@/stores/watchFile'
import { storeToRefs } from 'pinia'

const watchFileStore = useWatchFileStore()
const { isLoading, error } = storeToRefs(watchFileStore)

const handleUpdate = async () => {
    await watchFileStore.updateWatchFile('some-id', { name: 'New Name' })
}
</script>

<template>
    <button @click="handleUpdate" :disabled="isLoading">Update</button>
    <p v-if="error">An error occurred: {{ error.message }}</p>
</template>
```

### After: Migrating to `useMutation`

Here is the step-by-step refactoring to the new pattern.

#### Step 1: Create the API Function

First, ensure a dedicated API function exists for the operation. This function should be lean and only concern itself with making the HTTP call.

**`pwa/api/watchFile.ts` (New Way)**

```typescript
import { useApi } from '@/api/api'
import type { WatchFile } from '@/types/watchFile'

export const updateWatchFile = async (id: string, data: Partial<WatchFile>) => {
    const response = await useApi().patch<WatchFile>(`/watch_files/${id}`, data)
    return response.data
}
```

#### Step 2: Define the Mutation

Create a reusable mutation definition in `pwa/api/mutations/watchFile.ts`. This is where all side effects related to the mutation are handled.

**`pwa/api/mutations/watchFile.ts` (New Way)**

```typescript
import { updateWatchFile } from '@/api/watchFile'
import { WATCH_FILE_QUERY_KEYS } from '@/api/queries/watchFile'
import type { WatchFile } from '@/types/watchFile'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'

export const useUpdateWatchFile = defineMutation(() => {
    const toast = useToast()
    const { t } = useI18n()
    const queryCache = useQueryCache()
    const watchFileStore = useWatchFileStore() // For accessing filters

    const { mutate, ...mutation } = useMutation({
        mutation: ({ id, data }: { id: string; data: Partial<WatchFile> }) =>
            updateWatchFile(id, data),

        // Handle side-effects here
        onError() {
            toast.error(
                t('watch_files.title.error.toast_title'),
                t('watch_files.title.error.generic'),
            )
        },
        onSuccess({ id }) {
            toast.success(t('watch_files.title.success.updated'))
            // Invalidate queries to refetch stale data
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
```

#### Step 3: Refactor the Vue Component

The component now uses the `useUpdateWatchFile` hook, which simplifies the script significantly. The component is no longer responsible for triggering side effects or managing loading/error states directly.

**`pwa/components/some/Component.vue` (New Way)**

```vue
<script setup lang="ts">
import { useUpdateWatchFile } from '@/api/mutations/watchFile'

// The hook provides everything needed: the function, loading state, and error state.
const { updateTask, isLoading, error } = useUpdateWatchFile()

const handleUpdate = async () => {
    await updateTask({ id: 'some-id', data: { name: 'New Name' } })
}
</script>

<template>
    <button @click="handleUpdate" :disabled="isLoading">Update</button>
    <p v-if="error">An error occurred: {{ error.message }}</p>
</template>
```

#### Step 4: Clean Up the Pinia Store

The `useWatchFileStore` is now leaner. The action, loading state, and error state related to the mutation are removed. The store should only manage client-side state that is shared across components (e.g., filters, UI state).

**`pwa/stores/watchFile.ts` (After Cleanup)**

```typescript
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useWatchFileStore = defineStore('watch-file', () => {
    // State related to server mutations is removed.
    // State for UI controls or filters remains.
    const filters = ref({
        // ... filter properties
    })

    return {
        filters,
    }
})
```
