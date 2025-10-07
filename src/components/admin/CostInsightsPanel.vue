<template>
  <div class="grid grid-cols-2 gap-6">
    <div class="bg-base-100 rounded-lg border border-primary-stroke p-6 col-span-2">
      <h3 class="text-lg font-semibold mb-4">Key Insights</h3>

      <div v-if="loading" class="space-y-3">
        <div v-for="i in 4" :key="i" class="animate-pulse">
          <div class="h-4 bg-base-200 rounded w-3/4 mb-2"></div>
          <div class="h-3 bg-base-200 rounded w-1/2"></div>
        </div>
      </div>

      <div v-else-if="error" class="text-center py-6">
        <i class="fa fa-exclamation-triangle text-2xl text-error mb-2"></i>
        <p class="text-sm text-error">Failed to load insights</p>
      </div>

      <div v-else class="space-y-4">
        <!-- Most Expensive Workspace -->
        <div v-if="mostExpensiveWorkspace" class="p-4 bg-base-200 rounded-lg">
          <div class="flex items-center gap-2 mb-2">
            <i class="fa fa-crown text-warning"></i>
            <h4 class="font-medium">Most Expensive Workspace</h4>
          </div>
          <div class="text-sm text-primary-light-content">
            <strong>{{ mostExpensiveWorkspace.workspace_name }}</strong> has spent
            <span class="font-semibold text-primary">{{
              formatCurrency(mostExpensiveWorkspace.total_cost)
            }}</span>
            across {{ mostExpensiveWorkspace.task_count }} tasks and
            {{ mostExpensiveWorkspace.company_count }} companies
          </div>
        </div>

        <!-- Most Cost-Effective Task Type -->
        <div v-if="mostEfficientTaskType" class="p-4 bg-base-200 rounded-lg">
          <div class="flex items-center gap-2 mb-2">
            <i class="fa fa-leaf text-success"></i>
            <h4 class="font-medium">Most Cost-Effective Task Type</h4>
          </div>
          <div class="text-sm text-primary-light-content">
            <Badge
              :variant="getTaskTypeVariant(mostEfficientTaskType.task_type)"
              :label="mostEfficientTaskType.task_type"
              class="mr-2"
            />
            tasks average only
            <span class="font-semibold text-success">{{
              formatCurrency(mostEfficientTaskType.avg_cost_per_task)
            }}</span>
            per task across {{ mostEfficientTaskType.task_count }} executions
          </div>
        </div>

        <!-- Least Cost-Effective Task Type -->
        <div v-if="leastEfficientTaskType" class="p-4 bg-base-200 rounded-lg">
          <div class="flex items-center gap-2 mb-2">
            <i class="fa fa-exclamation-triangle text-warning"></i>
            <h4 class="font-medium">Least Cost-Effective Task Type</h4>
          </div>
          <div class="text-sm text-primary-light-content">
            <Badge
              :variant="getTaskTypeVariant(leastEfficientTaskType.task_type)"
              :label="leastEfficientTaskType.task_type"
              class="mr-2"
            />
            tasks cost
            <span class="font-semibold text-warning">{{
              formatCurrency(leastEfficientTaskType.avg_cost_per_task)
            }}</span>
            per task on average, totaling {{ formatCurrency(leastEfficientTaskType.total_cost) }}
          </div>
        </div>

        <!-- Highest Cost Day -->
        <div v-if="highestCostPeriod" class="p-4 bg-base-200 rounded-lg">
          <div class="flex items-center gap-2 mb-2">
            <i class="fa fa-chart-line text-info"></i>
            <h4 class="font-medium">Peak Cost Period</h4>
          </div>
          <div class="text-sm text-primary-light-content">
            <strong>{{ formatDate(highestCostPeriod.period) }}</strong> had the highest costs with
            <span class="font-semibold text-info">{{
              formatCurrency(highestCostPeriod.total_cost)
            }}</span>
            <span v-if="costTrends?.summary.avg_cost_per_period">
              ({{ getPercentageVsAvg(highestCostPeriod.total_cost) }}% above average)
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="bg-base-100 rounded-lg border border-primary-stroke p-6">
      <h3 class="text-lg font-semibold mb-4">Quick Statistics</h3>

      <div v-if="globalData?.global_summary" class="grid grid-cols-2 gap-4 text-sm">
        <div class="flex justify-between">
          <span class="text-primary-light-content">Avg Cost per Task:</span>
          <span class="font-semibold">{{
            formatCurrency(globalData.global_summary.avg_cost_per_task)
          }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-primary-light-content">Avg Cost per Company:</span>
          <span class="font-semibold">{{
            formatCurrency(globalData.global_summary.avg_cost_per_company)
          }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-primary-light-content">Total Workspaces:</span>
          <span class="font-semibold">{{ globalData.global_summary.total_workspaces }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-primary-light-content">Total Tokens:</span>
          <span class="font-semibold">{{
            formatNumber(
              globalData.global_summary.total_input_tokens +
                globalData.global_summary.total_output_tokens,
            )
          }}</span>
        </div>
      </div>
    </div>
    <div class="bg-base-100 rounded-lg border border-primary-stroke p-6">
      <h3 class="text-lg font-semibold mb-4">Claude Sonnet 4 Pricing</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex items-center justify-between p-3 bg-base-200 rounded-lg">
          <span class="text-sm font-medium">Input Cost (per 1M tokens)</span>
          <span class="font-bold text-success">$3.15</span>
        </div>
        <div class="flex items-center justify-between p-3 bg-base-200 rounded-lg">
          <span class="text-sm font-medium">Output Cost (per 1M tokens)</span>
          <span class="font-bold text-info">$15.75</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Badge, { type BadgeVariant } from '@/components/ui/Badge.vue'
import type {
  GlobalCostResponse,
  WorkspaceCostResponse,
  TaskTypeCostResponse,
  CostTrendResponse,
} from '@/api/cost-analysis'

interface Props {
  globalData?: GlobalCostResponse
  workspaceData?: WorkspaceCostResponse
  taskTypeData?: TaskTypeCostResponse
  costTrends?: CostTrendResponse
  loading?: boolean
  error?: boolean
}

const props = defineProps<Props>()

const mostExpensiveWorkspace = computed(() => {
  if (!props.workspaceData?.workspaces.length) return null

  return props.workspaceData.workspaces.reduce((max, current) =>
    current.total_cost > max.total_cost ? current : max,
  )
})

const mostEfficientTaskType = computed(() => {
  if (!props.taskTypeData?.task_types.length) return null

  return props.taskTypeData.task_types.reduce((min, current) =>
    current.avg_cost_per_task < min.avg_cost_per_task ? current : min,
  )
})

const leastEfficientTaskType = computed(() => {
  if (!props.taskTypeData?.task_types.length) return null

  return props.taskTypeData.task_types.reduce((max, current) =>
    current.avg_cost_per_task > max.avg_cost_per_task ? current : max,
  )
})

const highestCostPeriod = computed(() => {
  return props.costTrends?.summary.max_cost_period || null
})

const formatNumber = (num: number) => {
  return new Intl.NumberFormat().format(num)
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
  }).format(amount)
}

const formatDate = (dateString: string) => {
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

const getPercentageVsAvg = (amount: number) => {
  const avg = props.costTrends?.summary.avg_cost_per_period || 0
  if (avg === 0) return '0'
  return Math.round(((amount - avg) / avg) * 100)
}

const getTaskTypeVariant = (taskType: string): BadgeVariant => {
  const variants: Record<string, BadgeVariant> = {
    profile: 'primary',
    digital: 'info',
    timeline: 'success',
    products: 'warning',
    jobs: 'error',
    csr: 'slate',
    press: 'purple',
    team: 'teal',
  }
  return variants[taskType] || 'slate'
}
</script>
