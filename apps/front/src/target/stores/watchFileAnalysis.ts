import { RouteNames } from '@target/types/route-names'
import { defineStore, storeToRefs } from 'pinia'
import { computed, ref, toRef } from 'vue'
import { useWatchFileFiltersStore } from './watchFileFilters'

export const useWatchFileAnalysisStore = defineStore('watchFileAnalysis', () => {
  const filtersStore = useWatchFileFiltersStore()
  const type = 'analysis' as const

  const selectedView = ref<
    RouteNames.WATCH_FILES_RADAR_GRAPH | RouteNames.WATCH_FILES_RADAR_TIMELINE
  >(RouteNames.WATCH_FILES_RADAR_TIMELINE)

  const { states } = storeToRefs(filtersStore)

  const state = computed(() => states.value[type])

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

  const filterQuery = computed(() => filtersStore.filterQuery(type))
  const filtersCounts = computed(() => filtersStore.filtersCounts(type))
  const datesFilterCount = computed(() => filtersStore.datesFilterCount(type))

  const syncFormFilter = () => {
    filtersStore.syncFormFilter(type)
  }

  const resetFilters = () => {
    filtersStore.resetFilters(type)
  }

  const $reset = () => {
    resetFilters()
    state.value.currentPage = 1
    state.value.itemsPerPage = 25
    displayFiltersPanel.value = false
  }

  return {
    currentPage,
    itemsPerPage,

    selectedView,

    filtersCounts,
    datesFilterCount,

    displayFiltersPanel,

    filterQuery,

    syncFormFilter,
    isUrlSync,

    resetFilters,
    $reset,
  }
})
