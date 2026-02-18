import { computed, ref, toRef, type Ref } from 'vue'
import type { SortOrder } from '@owlint/feathers-vue'
import { defineStore } from 'pinia'
import type { CollectionParams, DocumentDateType, FilterParams } from '~/types/document'
import type { Filter } from '~/types/filter'
import { useWatchFileFiltersStore, type DocumentsFilterState } from './watchFileFilters'

export const useWatchFileDocumentsStore = defineStore('watchFileDocuments', () => {
  const filtersStore = useWatchFileFiltersStore()
  const type = 'documents' as const

  const searchQuery = ref('')
  const currentPage = ref(1)
  const itemsPerPage = ref(25)
  const sortBy = ref<DocumentDateType>('datePublish')
  const sortOrder = ref<SortOrder>('')

  // Access to the shared filter state
  const statesRef = toRef(filtersStore, 'states')
  const state = computed(() => statesRef.value[type])

  // Expose state as refs for compatibility with storeToRefs
  const formFilters = computed({
    get: () => state.value.formFilters as Filter,
    set: (value) => {
      state.value.formFilters = value as DocumentsFilterState['formFilters']
    },
  }) as Ref<Filter>

  const actors = computed({
    get: () => state.value.actors,
    set: (value) => {
      state.value.actors = value
    },
  })

  const sources = computed({
    get: () => state.value.sources,
    set: (value) => {
      state.value.sources = value
    },
  })

  const datesPicker = computed({
    get: () => state.value.datesPicker,
    set: (value) => {
      state.value.datesPicker = value
    },
  })

  const selectedPeriod = computed({
    get: () => state.value.selectedPeriod,
    set: (value) => {
      state.value.selectedPeriod = value
    },
  })

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

  const status = computed({
    get: () => (state.value as DocumentsFilterState).status,
    set: (value) => {
      ;(state.value as DocumentsFilterState).status = value
    },
  })

  const selectedDateType = computed({
    get: () => (state.value as DocumentsFilterState).selectedDateType,
    set: (value) => {
      ;(state.value as DocumentsFilterState).selectedDateType = value
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

  const resetPagination = () => {
    currentPage.value = 1
  }

  const syncFormFilter = () => {
    filtersStore.syncFormFilter(type)
    resetPagination()
  }

  const removeFilterItem = <T extends { id: string }>(
    id: string,
    filterArray: T[],
    targetRef: Ref<T[]>,
  ) => {
    // Determine if it's actors or sources based on the targetRef
    if (targetRef === actors.value) {
      filtersStore.removeActor(type, id)
    } else if (targetRef === sources.value) {
      filtersStore.removeSource(type, id)
    }
    resetPagination()
  }

  const removeValidation = (id: string) => {
    filtersStore.removeValidation(type, id)
    resetPagination()
  }

  const resetFormDatesFilter = () => {
    filtersStore.resetFormDatesFilter(type)
  }

  const resetDatesFilter = () => {
    filtersStore.resetDatesFilter(type)
  }

  const resetFilters = () => {
    filtersStore.resetFilters(type)
  }

  const $reset = () => {
    resetFilters()
    searchQuery.value = ''
    currentPage.value = 1
    itemsPerPage.value = 25
    displayFiltersPanel.value = false
  }

  return {
    currentPage,
    itemsPerPage,

    formFilters,
    filtersCounts,
    datesFilterCount,

    searchQuery,
    displayFiltersPanel,
    actors,
    sources,
    status,

    datesPicker,
    selectedDateType,
    selectedPeriod,

    filterQuery,
    queryParams,

    syncFormFilter,
    isUrlSync,

    removeValidation,
    removeFilterItem,

    sortBy,
    sortOrder,

    resetFormDatesFilter,
    resetDatesFilter,
    resetFilters,
    resetPagination,
    $reset,
  }
})
