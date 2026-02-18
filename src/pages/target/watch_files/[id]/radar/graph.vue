<template>
  <AnalysisChart
    :watch-file-id="watchFileId"
    :events-graph-data="eventsGraph"
    :is-loading="isLoadingEventsGraph"
  />
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { getEventsGraphQuery } from '~/api/queries/events'
import AnalysisChart from '~/components/analysis/chart/AnalysisChart.vue'
import { useWatchFileAnalysisStore } from '~/stores/watchFileAnalysis'

const route = useRoute()
const watchFileAnalysisStore = useWatchFileAnalysisStore()

const watchFileId = computed(() => route.params.id as string)
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
