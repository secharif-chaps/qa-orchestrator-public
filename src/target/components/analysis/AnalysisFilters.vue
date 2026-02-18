<template>
  <Filters
    v-model="displayDrawer"
    filter-type="analysis"
    :facets="facets"
    :is-loading="isLoading"
    :error="error"
    css-variable-name="--panel-analysis-filters-width"
    :available-filters="[FILTER_CATEGORY_DATES, FILTER_CATEGORY_ACTORS, FILTER_CATEGORY_EVENTS]"
  />
</template>

<script lang="ts" setup>
import Filters from '~/components/filters/Filters.vue'
import type { AnalysisFacets } from '~/types/facet'
import {
  FILTER_CATEGORY_DATES,
  FILTER_CATEGORY_ACTORS,
  FILTER_CATEGORY_EVENTS,
} from '~/types/facet'

const displayDrawer = defineModel<boolean>()

interface Props {
  facets?: AnalysisFacets
  isLoading?: boolean
  error?: Error | null
}

defineProps<Props>()
</script>
