import type { SortOrder } from '@owlint/feathers-vue'
import type { WatchFileFilters } from '@target/types/watchFile'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export const useWatchFileStore = defineStore('watchFile', () => {
  // UI state management only - no server state or API calls

  const currentPage = ref(1)
  const itemsPerPage = ref(10)
  const sortBy = ref<string>('sortBy')
  const sortOrder = ref<SortOrder>('ASC')
  const searchQuery = ref<string>('')
  const showFavorites = ref<boolean>(false)
  const hideArchived = ref<boolean>(true)
  const isUserEditable = ref<boolean>(false)

  // Current watchfile ID - used for silent navigation after creation
  const currentWatchFileId = ref<string | null>(null)

  const filters = computed(
    (): WatchFileFilters => ({
      sortBy: sortBy.value,
      sortOrder: sortOrder.value,
      page: currentPage.value,
      itemsPerPage: itemsPerPage.value,
      ...(searchQuery.value && { name: searchQuery.value }),
      ...(showFavorites.value && { onlyFavorites: true }),
      ...(!hideArchived.value && { includeArchived: true }),
    }),
  )

  const resetPagination = () => {
    currentPage.value = 1
  }

  const resetFilters = () => {
    sortBy.value = 'sortBy'
    sortOrder.value = 'ASC'
    searchQuery.value = ''
    showFavorites.value = false
    hideArchived.value = true
    isUserEditable.value = false
  }

  const $reset = () => {
    resetFilters()
    resetPagination()
    itemsPerPage.value = 10
    currentWatchFileId.value = null
  }

  return {
    currentPage,
    itemsPerPage,
    sortBy,
    sortOrder,
    searchQuery,
    showFavorites,
    hideArchived,
    filters,
    isUserEditable,
    currentWatchFileId,
    isLoading: false,

    // UI state management methods
    resetPagination,
    resetFilters,
    $reset,
  }
})
