<template>
  <div class="flex h-full flex-col gap-4">
    <div class="relative flex min-h-0 flex-1">
      <div
        class="absolute top-0 bottom-0 left-0 transform transition-transform duration-300 ease-in-out"
        :class="filterPanelWidth"
      >
        <AnalysisFilters
          v-model="displayDrawer"
          :facets="analysisFacets"
          :is-loading="isLoadingEventsGraph"
        />
      </div>
      <div
        class="ml-(--panel-analysis-filters-width) flex min-w-0 flex-1 flex-col space-y-6 p-6 pt-0 pr-4 pb-4"
      >
        <div class="flex justify-between gap-12">
          <div>
            <h2 class="text-xl font-bold">
              {{ t('watch_files.analysis.title') }}
            </h2>
            <p class="text-xs">{{ t('watch_files.analysis.subTitle') }}</p>
          </div>
          <Toggle
            v-model="selectedView"
            :default-value="RouteNames.WATCH_FILES_RADAR_TIMELINE"
            :options="options"
            prevent-unselect
          />
        </div>
        <RouterView />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Toggle } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { getEventsGraphQuery } from '@target/api/queries/events'
import AnalysisFilters from '@target/components/analysis/AnalysisFilters.vue'
import { useWatchFileAnalysisStore } from '@target/stores/watchFileAnalysis'
import { useWatchFileFiltersStore } from '@target/stores/watchFileFilters'
import type { AnalysisFacets } from '@target/types/facet'
import { RouteNames } from '@target/types/route-names'
import { storeToRefs } from 'pinia'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterView, useRoute, useRouter } from 'vue-router'

const { t } = useI18n()

const route = useRoute('/target/(watch_files)/[id]/radar')
const router = useRouter()
const watchFileId = computed(() => route.params.id)

const watchFileAnalysisStore = useWatchFileAnalysisStore()
const { displayFiltersPanel, selectedView } = storeToRefs(watchFileAnalysisStore)
const displayDrawer = ref(false)

const filtersCounts = computed(() => watchFileAnalysisStore.filtersCounts)
const filterQuery = computed(() => watchFileAnalysisStore.filterQuery)

if (route.name === RouteNames.WATCH_FILES_RADAR_GRAPH) {
  selectedView.value = RouteNames.WATCH_FILES_RADAR_GRAPH
} else if (route.name === RouteNames.WATCH_FILES_RADAR_TIMELINE) {
  selectedView.value = RouteNames.WATCH_FILES_RADAR_TIMELINE
}

watch(selectedView, (newView) => {
  router.push({
    name: newView,
    params: { id: watchFileId.value },
  })
})

const { data: eventsGraphData, isLoading: isLoadingEventsGraph } = useQuery(() =>
  getEventsGraphQuery({
    watchFileId: watchFileId.value,
    filters: filterQuery.value,
  }),
)

const analysisFacets = computed<AnalysisFacets | undefined>(() => {
  return eventsGraphData.value?.facets
})

const filtersStore = useWatchFileFiltersStore()

// Initialize from URL when facets are available
watch(analysisFacets, (newFacets) => {
  if (newFacets) {
    filtersStore.initializeFromUrl(
      'analysis',
      route.query,
      newFacets,
      () => watchFileAnalysisStore.isUrlSync,
    )
  }
})

// Sync URL with filter changes
watch(
  filterQuery,
  async (newQuery) => {
    if (!watchFileAnalysisStore.isUrlSync) {
      return
    }
    await router.replace({
      query: newQuery,
    })
  },
  { immediate: true },
)

const filterPanelWidth = computed(() => {
  if (filtersCounts.value && displayFiltersPanel.value) {
    return 'w-[20%] min-w-64'
  } else if (filtersCounts.value && !displayFiltersPanel.value) {
    return 'w-auto'
  } else {
    return 'w-12'
  }
})

const options = computed(() => [
  {
    value: RouteNames.WATCH_FILES_RADAR_TIMELINE,
    label: t('watch_files.analysis.toggle.timeline'),
  },
  {
    value: RouteNames.WATCH_FILES_RADAR_GRAPH,
    label: t('watch_files.analysis.toggle.graph'),
  },
])
</script>
