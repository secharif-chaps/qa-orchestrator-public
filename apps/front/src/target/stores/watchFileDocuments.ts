import type { SortOrder } from '@owlint/feathers-vue'
import type { CollectionParams, DocumentDateType, FilterParams } from '@target/types/document'
import { defineStore } from 'pinia'
import { computed, ref, toRef } from 'vue'
import { useWatchFileFiltersStore } from './watchFileFilters'

export const useWatchFileDocumentsStore = defineStore('watchFileDocuments', () => {
  const filtersStore = useWatchFileFiltersStore()
  const type = 'documents' as const

  const searchQuery = ref('')
  const sortBy = ref<DocumentDateType>('datePublish')
  const sortOrder = ref<SortOrder>('')

  // Access to the shared filter state
  const state = computed(() => filtersStore.states[type])

  const currentPage = toRef(() => state.value.currentPage)
  const itemsPerPage = toRef(() => state.value.itemsPerPage)

  const displayFiltersPanel = computed({
    get: () => state.value.displayFiltersPanel,
    set: (value) => {
      state.value.displayFiltersPanel = value
    },
  })

  const isUrlSync = computed({
    get: () => state.value.isUrlSync,
    set: (value) => {
      state.value.isUrlSync = value
    },
  })

  const filterQuery = computed<FilterParams>(() => {
    const baseQuery = filtersStore.filterQuery(type) as FilterParams
    if (searchQuery.value) {
      baseQuery.search = searchQuery.value
    }
    return baseQuery
  })

  const queryParams = computed<CollectionParams>(() => ({
    sortBy: sortBy.value,
    sortOrder: sortOrder.value,
    page: currentPage.value,
    itemsPerPage: itemsPerPage.value,
    ...filterQuery.value,
  }))

  const datesFilterCount = computed(() => filtersStore.datesFilterCount(type))
  const filtersCounts = computed(() => filtersStore.filtersCounts(type))

  const syncFormFilter = () => {
    filtersStore.syncFormFilter(type)
  }

  const resetFilters = () => {
    filtersStore.resetFilters(type)
  }

  const $reset = () => {
    resetFilters()
    searchQuery.value = ''
    state.value.currentPage = 1
    state.value.itemsPerPage = 25
    displayFiltersPanel.value = false
  }

  return {
    currentPage,
    itemsPerPage,

    filtersCounts,
    datesFilterCount,

    searchQuery,
    displayFiltersPanel,

    filterQuery,
    queryParams,

    syncFormFilter,
    isUrlSync,

    sortBy,
    sortOrder,

    resetFilters,
    $reset,
  }
})
