import { useDate } from '@target/composables/useDate'
import type { DocumentFacets } from '@target/types/document'
import type { Actor, AnalysisFacets, Source } from '@target/types/facet'
import type {
  AnalysisFormFilters,
  BaseFormFilters,
  DatePicker,
  DatesPeriod,
  DocumentsFormFilters,
  FilterDates,
} from '@target/types/filter'
import type { WatchFileEventType } from '@target/types/watchFile'
import { defineStore } from 'pinia'
import type { DateRange } from 'reka-ui'
import { computed, ref } from 'vue'
import type { LocationQueryRaw } from 'vue-router'

export type FilterType = 'documents' | 'analysis'

interface BaseFilterState {
  formFilters: BaseFormFilters
  datesPicker?: DatePicker
  selectedPeriod?: DatesPeriod
  actors: Actor[]
  sources: Source[]
  isUrlSync: boolean
  displayFiltersPanel: boolean
}

export interface DocumentsFilterState extends BaseFilterState {
  formFilters: DocumentsFormFilters
  selectedDateType?: FilterDates
  status: string[]
}

export interface AnalysisFilterState extends BaseFilterState {
  formFilters: AnalysisFormFilters
  eventTypes: WatchFileEventType[]
}

type FilterState = {
  documents: DocumentsFilterState
  analysis: AnalysisFilterState
}

const getEmptyDatePicker = (): DatePicker => ({
  start: undefined,
  end: undefined,
})

export const useWatchFileFiltersStore = defineStore('watchFileFilters', () => {
  // Two separate states for documents and analysis
  const states = ref<FilterState>({
    documents: {
      formFilters: {
        datesPicker: getEmptyDatePicker(),
        selectedPeriod: undefined,
        selectedDateType: undefined,
        status: [],
        actors: [],
        sources: [],
      },
      datesPicker: getEmptyDatePicker(),
      selectedPeriod: undefined,
      selectedDateType: undefined,
      actors: [],
      sources: [],
      status: [],
      isUrlSync: false,
      displayFiltersPanel: false,
    },
    analysis: {
      formFilters: {
        datesPicker: getEmptyDatePicker(),
        selectedPeriod: undefined,
        actors: [],
        sources: [],
        eventTypes: [],
      },
      datesPicker: getEmptyDatePicker(),
      selectedPeriod: undefined,
      actors: [],
      sources: [],
      eventTypes: [],
      isUrlSync: false,
      displayFiltersPanel: false,
    },
  })

  // Generic getters
  const getState = (type: FilterType) => states.value[type]

  const datesFilterCount = (type: FilterType) => {
    const state = getState(type)
    if (type === 'documents') {
      const docState = state as DocumentsFilterState
      if (docState.selectedDateType) {
        if (docState.datesPicker && (docState.datesPicker.start || docState.datesPicker.end)) {
          return 1
        }
        if (docState.selectedPeriod) {
          return 1
        }
      }
      return 0
    } else {
      // analysis
      if (state.datesPicker && (state.datesPicker.start || state.datesPicker.end)) {
        return 1
      }
      if (state.selectedPeriod) {
        return 1
      }
      return 0
    }
  }

  const filtersCounts = (type: FilterType) => {
    const state = getState(type)
    const datesCount = datesFilterCount(type)
    if (type === 'documents') {
      const docState = state as DocumentsFilterState
      return docState.status.length + datesCount + docState.actors.length + docState.sources.length
    } else if (type === 'analysis') {
      const analysisState = state as AnalysisFilterState
      return (
        datesCount +
        analysisState.actors.length +
        analysisState.sources.length +
        analysisState.eventTypes.length
      )
    }
    return 0
  }

  const filterQuery = (type: FilterType): LocationQueryRaw => {
    const state = getState(type)

    // Always return filters from the state, never from route.query
    // This ensures documents and analysis filters are completely separate
    const query: LocationQueryRaw = {}

    if (state.actors.length > 0) {
      query.actors = state.actors.map((actor) => actor.id)
    }
    if (state.sources.length > 0) {
      query.sources = state.sources.map((source) => source.id)
    }

    if (state.selectedPeriod) {
      query.selectedPeriod = state.selectedPeriod
    }
    if (state.datesPicker?.start) {
      query.datesPickerStart = state.datesPicker.start.toString()
    }
    if (state.datesPicker?.end) {
      query.datesPickerEnd = state.datesPicker.end.toString()
    }

    if (type === 'documents') {
      const docState = state as DocumentsFilterState
      if (docState.status.length > 0) {
        query.status = docState.status
      }
      if (docState.selectedDateType) {
        query.selectedDateType = docState.selectedDateType
      }
    }
    if (type === 'analysis') {
      const analysisState = state as AnalysisFilterState
      if (analysisState.eventTypes.length > 0) {
        query.eventTypes = analysisState.eventTypes
      }
    }

    return query
  }

  // Generic methods
  const syncFormFilter = (type: FilterType) => {
    const state = getState(type)
    state.isUrlSync = true

    state.datesPicker = state.formFilters.datesPicker as DateRange
    state.selectedPeriod = state.formFilters.selectedPeriod
    state.actors = state.formFilters.actors
    state.sources = state.formFilters.sources

    if (type === 'documents') {
      const docState = state as DocumentsFilterState
      docState.selectedDateType = docState.formFilters.selectedDateType
      docState.status = docState.formFilters.status
    }
    if (type === 'analysis') {
      const analysisState = state as AnalysisFilterState
      analysisState.eventTypes = analysisState.formFilters.eventTypes
    }
  }

  const removeActor = (type: FilterType, id: string) => {
    const state = getState(type)
    const itemIndex = state.formFilters.actors.findIndex((item) => item.id === id)

    if (itemIndex !== -1) {
      state.formFilters.actors.splice(itemIndex, 1)
      const newArray = [...state.formFilters.actors]
      state.actors = newArray
      state.formFilters.actors = newArray
    }
  }

  const removeSource = (type: FilterType, id: string) => {
    const state = getState(type)
    const itemIndex = state.formFilters.sources.findIndex((item) => item.id === id)

    if (itemIndex !== -1) {
      state.formFilters.sources.splice(itemIndex, 1)
      const newArray = [...state.formFilters.sources]
      state.sources = newArray
      state.formFilters.sources = newArray
    }
  }

  const removeEventType = (type: FilterType, eventType: WatchFileEventType) => {
    if (type !== 'analysis') return
    const state = getState(type) as AnalysisFilterState
    const itemIndex = state.formFilters.eventTypes.findIndex((item) => item === eventType)
    if (itemIndex !== -1) {
      state.formFilters.eventTypes.splice(itemIndex, 1)
      state.eventTypes = [...state.formFilters.eventTypes]
    }
  }

  const removeValidation = (type: FilterType, id: string) => {
    if (type !== 'documents') return

    const state = getState(type) as DocumentsFilterState
    const statusIndex = state.formFilters.status.findIndex((status) => status === id)

    if (statusIndex !== -1) {
      state.formFilters.status.splice(statusIndex, 1)
      state.status = [...state.formFilters.status]
    }
  }

  const resetFormDatesFilter = (type: FilterType) => {
    const state = getState(type)
    state.formFilters.datesPicker = getEmptyDatePicker()
    state.formFilters.selectedPeriod = undefined

    if (type === 'documents') {
      const docState = state as DocumentsFilterState
      docState.formFilters.selectedDateType = undefined
    }
  }

  const resetDatesFilter = (type: FilterType) => {
    const state = getState(type)
    resetFormDatesFilter(type)

    state.datesPicker = getEmptyDatePicker()
    state.selectedPeriod = undefined

    if (type === 'documents') {
      const docState = state as DocumentsFilterState
      docState.selectedDateType = undefined
    }
  }

  const resetFilters = (type: FilterType) => {
    const state = getState(type)

    state.formFilters.actors = []
    state.formFilters.sources = []
    state.actors = []
    state.sources = []
    state.isUrlSync = false

    if (type === 'documents') {
      const docState = state as DocumentsFilterState
      docState.formFilters.status = []
      docState.status = []
    }

    if (type === 'analysis') {
      const analysisState = state as AnalysisFilterState
      analysisState.formFilters.eventTypes = []
      analysisState.eventTypes = []
    }

    resetDatesFilter(type)
  }

  /**
   * Initialize filter state from URL query parameters
   * @param type - The filter type ('documents' or 'analysis')
   * @param query - URL query parameters
   * @param facets - Optional facets data to resolve actors/sources from IDs
   * @param isUrlSyncGetter - Function to check if URL sync is already active
   * @returns true if initialization was performed, false otherwise
   */
  const initializeFromUrl = (
    type: FilterType,
    query: LocationQueryRaw,
    facets?: DocumentFacets | AnalysisFacets,
    isUrlSyncGetter?: () => boolean,
  ) => {
    // Only initialize if URL has query params and store is not already synced
    if (!Object.keys(query).length) {
      return
    }

    if (isUrlSyncGetter && isUrlSyncGetter()) {
      return
    }

    const state = getState(type)
    const { convertDateToDateValue } = useDate()

    // Initialize actors from URL
    if (query.actors && facets) {
      const actorIds = Array.isArray(query.actors) ? query.actors : [query.actors]

      let actorsFromFacets: Actor[] = []

      if (type === 'documents' && 'actors' in facets) {
        const docFacets = facets as DocumentFacets
        if (docFacets.actors) {
          actorsFromFacets = actorIds
            .map((id) => {
              const facet = docFacets.actors.find((f) => f.actor.id === String(id))
              return facet?.actor
            })
            .filter((actor): actor is Actor => actor !== undefined)
        }
      } else if (type === 'analysis' && 'actors' in facets) {
        const analysisFacets = facets as AnalysisFacets
        if (analysisFacets.actors) {
          actorsFromFacets = actorIds
            .map((id) => {
              const facet = analysisFacets.actors?.find((f) => {
                if ('actor' in f) {
                  return f.actor.id === String(id)
                }
                if ('id' in f) {
                  return f.id === String(id)
                }
                return false
              })
              if (facet && 'actor' in facet) {
                return facet.actor
              }
              if (facet && 'id' in facet && 'name' in facet) {
                return {
                  id: facet.id,
                  label: facet.name,
                  primaryDomain: undefined,
                } as Actor
              }
              return undefined
            })
            .filter((actor): actor is Actor => actor !== undefined)
        }
      }

      if (actorsFromFacets.length > 0) {
        state.formFilters.actors = actorsFromFacets
        state.actors = actorsFromFacets
      }
    }

    // Initialize sources from URL
    if (query.sources && facets && 'sources' in facets) {
      const sourceIds = Array.isArray(query.sources) ? query.sources : [query.sources]
      const sourcesFromFacets = sourceIds
        .map((id) => {
          const facet = facets.sources.find((f) => f.source.id === String(id))
          return facet?.source
        })
        .filter((source): source is Source => source !== undefined)
      if (sourcesFromFacets.length > 0) {
        state.formFilters.sources = sourcesFromFacets
        state.sources = sourcesFromFacets
      }
    }

    // Initialize status (documents only)
    if (type === 'documents') {
      const docState = state as DocumentsFilterState
      if (query.status) {
        const statusArray = Array.isArray(query.status) ? query.status : [query.status]
        docState.formFilters.status = statusArray.filter((s): s is string => typeof s === 'string')
        docState.status = docState.formFilters.status
      }

      // Initialize selectedDateType
      if (query.selectedDateType && typeof query.selectedDateType === 'string') {
        docState.formFilters.selectedDateType = query.selectedDateType as FilterDates
        docState.selectedDateType = docState.formFilters.selectedDateType
      }
    }

    // Initialize eventTypes (analysis only)
    if (type === 'analysis') {
      const analysisState = state as AnalysisFilterState
      if (query.eventTypes) {
        const eventTypesArray = Array.isArray(query.eventTypes)
          ? query.eventTypes
          : [query.eventTypes]
        const validEventTypes = eventTypesArray.filter(
          (et): et is WatchFileEventType =>
            typeof et === 'string' &&
            ['created', 'updated', 'deleted', 'validated', 'rejected'].includes(et),
        )
        if (validEventTypes.length > 0) {
          analysisState.formFilters.eventTypes = validEventTypes
          analysisState.eventTypes = validEventTypes
        }
      }
    }

    // Initialize datesPicker
    if (query.datesPickerStart || query.datesPickerEnd) {
      const startDate = query.datesPickerStart
        ? new Date(query.datesPickerStart as string)
        : undefined
      const endDate = query.datesPickerEnd ? new Date(query.datesPickerEnd as string) : undefined

      if (startDate && !isNaN(startDate.getTime())) {
        if (!state.formFilters.datesPicker) {
          state.formFilters.datesPicker = { start: undefined, end: undefined }
        }
        state.formFilters.datesPicker.start = convertDateToDateValue(startDate)
        state.datesPicker = {
          start: convertDateToDateValue(startDate),
          end: state.datesPicker?.end,
        }
      }
      if (endDate && !isNaN(endDate.getTime())) {
        if (!state.formFilters.datesPicker) {
          state.formFilters.datesPicker = { start: undefined, end: undefined }
        }
        state.formFilters.datesPicker.end = convertDateToDateValue(endDate)
        state.datesPicker = {
          start: state.datesPicker?.start,
          end: convertDateToDateValue(endDate),
        }
      }
    }

    // Initialize selectedPeriod
    if (query.selectedPeriod && typeof query.selectedPeriod === 'string') {
      state.formFilters.selectedPeriod = query.selectedPeriod as DatesPeriod
      state.selectedPeriod = state.formFilters.selectedPeriod
    }

    // Sync form filters to active filters
    syncFormFilter(type)
    state.isUrlSync = true
  }

  // Expose refs for each type for direct access
  const documentsState = computed(() => states.value.documents)
  const analysisState = computed(() => states.value.analysis)

  return {
    // State access
    states,
    getState,
    documentsState,
    analysisState,

    // Computed values
    datesFilterCount,
    filtersCounts,
    filterQuery,

    // Methods
    syncFormFilter,
    removeActor,
    removeSource,
    removeValidation,
    removeEventType,
    resetFormDatesFilter,
    resetDatesFilter,
    resetFilters,
    initializeFromUrl,
  }
})
