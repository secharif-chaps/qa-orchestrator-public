import { refDebounced } from '@vueuse/core'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export type FolderSortField = 'name' | 'created_at' | 'updated_at'
export type FolderSortOrder = 'asc' | 'desc'

export const useFoldersStore = defineStore('folders', () => {
  const page = ref(1)
  const size = ref(12) // grid-friendly default (multiple of 3)

  const filterName = ref('')
  const debouncedName = refDebounced(filterName, 500)

  // Filters surfaced through the new drawer (TAR-1448).
  const favoritesOnly = ref(false)
  // Default: hide archived. Toggle off to include them.
  // TODO(TAR-1446): when the API supports "include archived alongside",
  // map this to a dedicated include_archived flag instead of reusing the
  // existing archived-only filter.
  const hideArchived = ref(true)
  const includeAll = ref(false) // managers — show all org folders

  const sortBy = ref<FolderSortField>('updated_at')
  const sortOrder = ref<FolderSortOrder>('desc')

  const activeFiltersCount = computed(() => {
    let count = 0
    if (favoritesOnly.value) count++
    if (!hideArchived.value) count++
    if (includeAll.value) count++
    return count
  })

  const resetFilters = () => {
    favoritesOnly.value = false
    hideArchived.value = true
    includeAll.value = false
  }

  return {
    page,
    size,
    filterName,
    debouncedName,
    favoritesOnly,
    hideArchived,
    includeAll,
    sortBy,
    sortOrder,
    activeFiltersCount,
    resetFilters,
  }
})
