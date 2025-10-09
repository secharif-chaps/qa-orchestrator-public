<template>
  <div>
    <div class="space-y-6">
      <Button
        variant="tertiary"
        icon="fa fa-arrow-left"
        :label="$t('admin.dashboard.back', 'Back to Admin')"
        @click="$router.push('/admin')"
      />
      <!-- Header with Time Range Selector -->
      <div class="mb-8">
        <CostTimeRangeSelector v-model="dateFilters" />
      </div>

      <!-- Cost Overview Cards -->
      <div class="mb-8">
        <CostMetricCards :data="globalData" />
      </div>

      <!-- Main Dashboard Content -->
      <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
        <!-- Left Column (40% width on XL screens) -->
        <div class="xl:col-span-4 space-y-6 grid grid-cols-2 gap-6">
          <!-- Cost Trends Chart -->
          <CostTrendsChart :data="trendsData" :loading="trendsLoading" :error="!!trendsError" />

          <!-- Workspace Cost Distribution -->
          <WorkspaceCostChart
            :data="workspaceData"
            :loading="workspaceLoading"
            :error="!!workspaceError"
          />
        </div>

        <!-- Middle Column (35% width on XL screens) -->
        <div class="xl:col-span-4 space-y-6">
          <!-- Workspace Breakdown Table -->

          <CostInsightsPanel
            :global-data="globalData"
            :workspace-data="workspaceData"
            :task-type-data="taskTypeData"
            :cost-trends="trendsData"
            :loading="anyLoading"
            :error="anyError"
          />
        </div>
      </div>

      <!-- Task Type Efficiency Table (Full Width) -->
      <div class="mt-6 space-y-6">
        <TaskTypeEfficiencyTable
          :data="taskTypeData"
          :loading="taskTypeLoading"
          :error="!!taskTypeError"
        />
        <WorkspaceBreakdownTable
          :data="workspaceData"
          :loading="workspaceLoading"
          :error="!!workspaceError"
        />
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.costs
</route>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useQuery } from '@pinia/colada'
import type { CostAnalysisFilters } from '@/api/cost-analysis'
import {
  globalCostQuery,
  workspaceCostQuery,
  taskTypeCostQuery,
  costTrendsQuery,
} from '@/queries/cost-analysis'
import Button from '@/components/ui/Button.vue'
import CostTimeRangeSelector from '@/components/admin/CostTimeRangeSelector.vue'
import CostMetricCards from '@/components/admin/CostMetricCards.vue'
import CostTrendsChart from '@/components/admin/CostTrendsChart.vue'
import WorkspaceCostChart from '@/components/admin/WorkspaceCostChart.vue'
import WorkspaceBreakdownTable from '@/components/admin/WorkspaceBreakdownTable.vue'
import TaskTypeEfficiencyTable from '@/components/admin/TaskTypeEfficiencyTable.vue'
import CostInsightsPanel from '@/components/admin/CostInsightsPanel.vue'

const router = useRouter()

// Date filters (reactive)
const dateFilters = ref<CostAnalysisFilters>({})

// Determine granularity based on date range
const granularity = computed(() => {
  if (!dateFilters.value.start_date || !dateFilters.value.end_date) {
    return 'daily' // Default for current month or all-time
  }

  const start = new Date(dateFilters.value.start_date)
  const end = new Date(dateFilters.value.end_date)
  const daysDiff = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24))

  if (daysDiff <= 31) return 'daily'
  if (daysDiff <= 90) return 'weekly'
  return 'monthly'
})

// Queries
const {
  data: globalData,
  isLoading: globalLoading,
  error: globalError,
} = useQuery(globalCostQuery, () => dateFilters.value)

const {
  data: workspaceData,
  isLoading: workspaceLoading,
  error: workspaceError,
} = useQuery(workspaceCostQuery, () => dateFilters.value)

const {
  data: taskTypeData,
  isLoading: taskTypeLoading,
  error: taskTypeError,
} = useQuery(taskTypeCostQuery, () => dateFilters.value)

const {
  data: trendsData,
  isLoading: trendsLoading,
  error: trendsError,
} = useQuery(costTrendsQuery, () => ({ ...dateFilters.value, granularity: granularity.value }))

// Combined loading states
const anyLoading = computed(
  () =>
    globalLoading.value || workspaceLoading.value || taskTypeLoading.value || trendsLoading.value,
)

const anyError = computed(
  () =>
    !!globalError.value || !!workspaceError.value || !!taskTypeError.value || !!trendsError.value,
)
</script>
