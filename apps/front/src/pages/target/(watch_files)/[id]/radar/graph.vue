<template>
  <AnalysisChart
    :watch-file-id="watchFileId"
    :events-graph-data="eventsGraph"
    :is-loading="isLoadingEventsGraph"
  />
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { getEventsGraphQuery } from '@target/api/queries/events'
import AnalysisChart from '@target/components/analysis/chart/AnalysisChart.vue'
import { useWatchFileAnalysisStore } from '@target/stores/watchFileAnalysis'
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute('/target/(watch_files)/[id]/radar/graph')
const watchFileAnalysisStore = useWatchFileAnalysisStore()

const watchFileId = computed(() => route.params.id)
const filterQuery = computed(() => watchFileAnalysisStore.filterQuery)

const { data: eventsGraphData, isLoading: isLoadingEventsGraph } = useQuery(
  getEventsGraphQuery,
  () => ({
    watchFileId: watchFileId.value,
    filters: filterQuery.value,
  }),
)

const eventsGraph = computed(() => {
  return {
    items: eventsGraphData.value?.items ?? [],
    totalItems: eventsGraphData.value?.totalItems ?? 0,
  }
})
</script>
