<template>
  <FiltersPanel
    v-if="facets"
    v-model="displayDrawer"
    :facets="facets"
    :is-loading="isLoading"
    :error="error"
    :accordion-filters="accordionFilters"
    :filters-counts="filtersCounts"
    :display-filters-panel="displayFiltersPanel"
    :css-variable-name="cssVariableName"
    :on-confirm-filters="confirmFilters"
    :on-reset-filters="resetFilters"
    :on-sync-form-filters="syncFormFilters"
    :on-update-display-filters-panel="handleUpdateDisplayFiltersPanel"
  >
    <template #chips="{ openEditFilter }">
      <FilterPanelValidationsChips
        v-if="'validationStatuses' in facets && status.length"
        :status="status"
        @open-edit-filter="openEditFilter"
        @remove="handleRemoveValidation"
      />
      <FilterPanelDatesChips
        v-if="datesFilterCount"
        :dates-picker="datesPickerValue"
        :selected-period="selectedPeriod"
        :selected-date-type="selectedDateType"
        :dates-filter-count="datesFilterCount"
        :show-date-type="'validationStatuses' in facets"
        @open-edit-filter="openEditFilter"
        @reset="handleResetDatesFilter"
      />
      <FilterPanelActorsChips
        v-if="'actors' in facets && actors.length"
        :actors="actors"
        @open-edit-filter="openEditFilter"
        @remove="handleRemoveActor"
      />
      <FilterPanelSourcesChips
        v-if="'sources' in facets && sources.length"
        :sources="sources"
        @open-edit-filter="openEditFilter"
        @remove="handleRemoveSource"
      />
      <FilterPanelEventTypesChips
        v-if="'eventTypes' in facets && eventTypes.length"
        :event-types="eventTypes"
        @open-edit-filter="openEditFilter"
        @remove="handleRemoveEventType"
      />
    </template>
    <template #validations>
      <FilterValidations
        v-if="'validationStatuses' in facets"
        v-model="formFiltersStatus"
        :statuses="facets.validationStatuses"
      />
    </template>
    <template #dates>
      <FilterDatesComponent
        v-model:dates-picker="formFiltersDatesPicker"
        v-model:selected-period="formFilters.selectedPeriod"
        v-model:selected-date-type="formFiltersSelectedDateType"
        :dates-filter-count="datesFilterCount"
        :show-date-type="'validationStatuses' in facets"
        @reset="handleResetFormDatesFilter"
      />
    </template>
    <template #actors>
      <FilterActors v-if="'actors' in facets" v-model="formFilters.actors" :actors="facetActors" />
    </template>
    <template #sources>
      <FilterSources
        v-if="'sources' in facets"
        v-model="formFilters.sources"
        :sources="facetSources"
      />
    </template>
    <template #eventTypes>
      <FilterEventTypes
        v-if="'eventTypes' in facets && 'eventTypes' in formFilters"
        v-model="formFilters.eventTypes"
        :event-types="facetEventTypes"
      />
    </template>
  </FiltersPanel>
</template>

<script lang="ts" setup>
import { storeToRefs } from 'pinia'
import type { DateRange } from 'reka-ui'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FilterActors from '~/components/filters/FilterActors.vue'
import FilterDatesComponent from '~/components/filters/FilterDates.vue'
import FilterPanelActorsChips from '~/components/filters/FilterPanelActorsChips.vue'
import FilterPanelDatesChips from '~/components/filters/FilterPanelDatesChips.vue'
import FilterPanelSourcesChips from '~/components/filters/FilterPanelSourcesChips.vue'
import FilterPanelValidationsChips from '~/components/filters/FilterPanelValidationsChips.vue'
import FilterSources from '~/components/filters/FilterSources.vue'
import FiltersPanel from '~/components/filters/FiltersPanel.vue'
import FilterValidations from '~/components/filters/FilterValidations.vue'
import type { FilterType } from '~/stores/watchFileFilters'
import { useWatchFileFiltersStore } from '~/stores/watchFileFilters'
import type { DocumentFacets } from '~/types/document'
import type { ActorFacet, AnalysisFacets, FilterCategory } from '~/types/facet'
import type { DocumentFilter } from '~/types/filter'
import type { WatchFileEventType } from '~/types/watchFile'
import FilterEventTypes from './FilterEventTypes.vue'
import FilterPanelEventTypesChips from './FilterPanelEventTypesChips.vue'

const { t } = useI18n()

const displayDrawer = defineModel<boolean>()

interface Props {
  filterType: FilterType
  facets?: DocumentFacets | AnalysisFacets
  isLoading?: boolean
  error?: Error | null
  cssVariableName: string
  availableFilters: FilterCategory[]
}

const { filterType, facets = undefined, isLoading = false, error = null } = defineProps<Props>()

const filtersStore = useWatchFileFiltersStore()
const { states } = storeToRefs(filtersStore)

// Access state based on filter type - use a getter function to access the ref directly
const getState = () => states.value[filterType]

// Expose state as refs for compatibility
const formFilters = computed({
  get: () => getState().formFilters,
  set: (value) => {
    getState().formFilters = value
  },
})

const actors = computed({
  get: () => getState().actors,
  set: (value) => {
    getState().actors = value
  },
})

const sources = computed({
  get: () => getState().sources,
  set: (value) => {
    getState().sources = value
  },
})

const datesPicker = computed({
  get: () => getState().datesPicker,
  set: (value) => {
    getState().datesPicker = value
  },
})

const datesPickerValue = computed<DateRange | undefined>(() => {
  return datesPicker.value as DateRange | undefined
})

const formFiltersDatesPicker = computed({
  get: (): DateRange => {
    return formFilters.value.datesPicker as DateRange
  },
  set: (value: DateRange) => {
    formFilters.value.datesPicker = value
  },
})

const selectedPeriod = computed({
  get: () => getState().selectedPeriod,
  set: (value) => {
    getState().selectedPeriod = value
  },
})

const displayFiltersPanel = ref(states.value[filterType].displayFiltersPanel)

const handleUpdateDisplayFiltersPanel = (value: boolean) => {
  displayFiltersPanel.value = value
}

watch(displayFiltersPanel, (newValue) => {
  states.value[filterType].displayFiltersPanel = newValue
})

const eventTypes = computed(() => {
  if (!facets || !('eventTypes' in facets)) return []
  const state = getState()

  return 'eventTypes' in state ? state.eventTypes : []
})

const status = computed(() => {
  if (!facets || !('validationStatuses' in facets)) return []
  const state = getState()
  return 'status' in state ? state.status : []
})

const selectedDateType = computed(() => {
  const state = getState()
  return 'selectedDateType' in state ? state.selectedDateType : undefined
})

const formFiltersSelectedDateType = computed({
  get: () => {
    const filters = formFilters.value
    return 'selectedDateType' in filters ? filters.selectedDateType : undefined
  },
  set: (value) => {
    const filters = formFilters.value
    if ('selectedDateType' in filters) {
      filters.selectedDateType = value
    }
  },
})

const formFiltersStatus = computed({
  get: () => {
    const filters = formFilters.value
    return 'status' in filters ? filters.status : []
  },
  set: (value: string[]) => {
    const filters = formFilters.value
    if ('status' in filters) {
      filters.status = value
    }
  },
})

const datesFilterCount = computed(() => filtersStore.datesFilterCount(filterType))
const filtersCounts = computed(() => filtersStore.filtersCounts(filterType))

const previousCount = ref(0)

watch(
  filtersCounts,
  (count) => {
    if (previousCount.value === 0 && count > 0) {
      displayFiltersPanel.value = true
    }
    if (count === 0) {
      displayFiltersPanel.value = false
    }
    previousCount.value = count
  },
  { immediate: true },
)

const accordionFilters = computed<DocumentFilter[]>(() => {
  const filters: DocumentFilter[] = []

  if (!facets) {
    return filters
  }

  if ('validationStatuses' in facets) {
    filters.push({
      title: t('watch_files.filters.type.validations'),
      value: 'validations',
      icon: 'fa-file-lines',
      count: status.value.length,
      empty: !facets.validationStatuses?.length,
    })
  }

  filters.push({
    title: t('watch_files.filters.type.dates'),
    value: 'dates',
    icon: 'fa-calendar',
    count: datesFilterCount.value,
    empty: false,
  })

  if ('actors' in facets) {
    filters.push({
      title: t('watch_files.filters.type.actors'),
      value: 'actors',
      icon: 'fa-user',
      count: actors.value.length,
      empty: !facets.actors?.length,
    })
  }

  if ('sources' in facets) {
    filters.push({
      title: t('watch_files.filters.type.sources'),
      value: 'sources',
      icon: 'fa-link',
      count: sources.value.length,
      empty: !facets.sources?.length,
    })
  }

  if ('eventTypes' in facets) {
    filters.push({
      title: t('watch_files.filters.type.event_types'),
      value: 'eventTypes',
      icon: 'fa-bullhorn',
      count: eventTypes.value.length,
      empty: !facets.eventTypes?.length,
    })
  }

  return filters.filter((filter) => !filter.empty)
})

const facetActors = computed<ActorFacet[]>(() => {
  if (!facets || !('actors' in facets) || !facets.actors?.length) {
    return actors.value.map((actor) => ({ actor, count: 0 }))
  }

  return facets.actors.map((actorFacet) => {
    // Temporary fix because the analysis actor facets are not in regular format
    if ('actor' in actorFacet) {
      // This is the general format here
      return {
        actor: {
          ...actorFacet.actor,
          primaryDomain: actorFacet.actor.primaryDomain ?? '',
        },
        count: actorFacet.count,
      }
    }

    // For analysis actors, we need to normalize to ActorFacet
    return {
      actor: {
        id: actorFacet.id,
        label: actorFacet.name,
        primaryDomain: '',
      },
      count: actorFacet.count,
    }
  })
})

const facetSources = computed(() => {
  if (!facets || !('sources' in facets) || !facets.sources?.length) {
    return sources.value.map((source) => ({ source, count: 0 }))
  }

  return facets.sources
})

const facetEventTypes = computed(() => {
  if (!facets || !('eventTypes' in facets) || !facets.eventTypes?.length) {
    return eventTypes.value.map((eventType) => ({ type: eventType, count: 0 }))
  }

  return facets.eventTypes
})

const handleRemoveActor = (id: string) => {
  filtersStore.removeActor(filterType, id)
}

const handleRemoveSource = (id: string) => {
  filtersStore.removeSource(filterType, id)
}

const handleRemoveEventType = (eventType: WatchFileEventType) => {
  filtersStore.removeEventType(filterType, eventType)
}

const handleRemoveValidation = (id: string) => {
  filtersStore.removeValidation(filterType, id)
}

const confirmFilters = () => {
  filtersStore.syncFormFilter(filterType)
}

const resetFilters = () => {
  filtersStore.resetFilters(filterType)
}

const syncFormFilters = () => {
  formFilters.value.actors = [...actors.value]
  formFilters.value.sources = [...sources.value]
  formFilters.value.datesPicker = datesPicker.value
  formFilters.value.selectedPeriod = selectedPeriod.value

  const filters = formFilters.value
  if ('status' in filters) {
    filters.status = [...status.value]
  }
  if ('selectedDateType' in filters) {
    filters.selectedDateType = selectedDateType.value
  }
}

const handleResetDatesFilter = () => {
  filtersStore.resetDatesFilter(filterType)
}

const handleResetFormDatesFilter = () => {
  filtersStore.resetFormDatesFilter(filterType)
}
</script>
