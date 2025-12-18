<template>
  <div class="bg-base-100 rounded-lg border border-primary-stroke">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h3 class="text-lg font-semibold">Task Type Cost Efficiency</h3>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-spinner animate-spin text-2xl text-secondary mb-2"></i>
        <p class="text-sm text-secondary">Loading task type data...</p>
      </div>
    </div>

    <div v-else-if="error" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-exclamation-triangle text-2xl text-error mb-2"></i>
        <p class="text-sm text-error">Failed to load task type data</p>
      </div>
    </div>

    <div v-else-if="!data?.task_types.length" class="text-center py-12">
      <i class="fa fa-tasks text-4xl text-secondary mb-4"></i>
      <p class="text-lg font-medium text-secondary">No task type data available</p>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-base-200">
          <tr>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('task_type')"
            >
              <div class="flex items-center gap-1">
                Task Type
                <i class="fa text-xs" :class="getSortIcon('task_type')"></i>
              </div>
            </th>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('total_cost')"
            >
              <div class="flex items-center gap-1">
                Total Cost
                <i class="fa text-xs" :class="getSortIcon('total_cost')"></i>
              </div>
            </th>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('task_count')"
            >
              <div class="flex items-center gap-1">
                Task Count
                <i class="fa text-xs" :class="getSortIcon('task_count')"></i>
              </div>
            </th>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('avg_cost_per_task')"
            >
              <div class="flex items-center gap-1">
                Avg Cost/Task
                <i class="fa text-xs" :class="getSortIcon('avg_cost_per_task')"></i>
              </div>
            </th>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('avg_input_tokens')"
            >
              <div class="flex items-center gap-1">
                Avg Input Tokens
                <i class="fa text-xs" :class="getSortIcon('avg_input_tokens')"></i>
              </div>
            </th>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('avg_output_tokens')"
            >
              <div class="flex items-center gap-1">
                Avg Output Tokens
                <i class="fa text-xs" :class="getSortIcon('avg_output_tokens')"></i>
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-primary-stroke">
          <tr
            v-for="taskType in sortedTaskTypes"
            :key="taskType.task_type"
            class="hover:bg-base-200 transition-colors"
          >
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <Tag
                  :variant="getTaskTypeVariant(taskType.task_type)"
                  :label="taskType.task_type"
                />
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-semibold text-secondary">
                {{ formatCurrency(taskType.total_cost) }}
              </div>
              <div class="text-xs text-secondary">
                {{ getPercentage(taskType.total_cost) }}% of total
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium">{{ formatNumber(taskType.task_count) }}</div>
              <div class="text-xs text-secondary">
                {{ getTaskCountPercentage(taskType.task_count) }}% of all tasks
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div
                class="text-sm font-medium"
                :class="getEfficiencyColor(taskType.avg_cost_per_task)"
              >
                {{ formatCurrency(taskType.avg_cost_per_task) }}
              </div>
              <div class="text-xs text-secondary">
                {{ getEfficiencyLabel(taskType.avg_cost_per_task) }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-secondary">
                {{ formatNumber(taskType.avg_input_tokens) }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-secondary">
                {{ formatNumber(taskType.avg_output_tokens) }}
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Summary Info -->
      <div v-if="data?.summary" class="px-6 py-4 border-t border-primary-stroke bg-base-200">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
          <div>
            <span class="font-medium text-secondary">Most Expensive:</span>
            <Tag
              :variant="getTaskTypeVariant(data.summary.most_expensive_type)"
              :label="data.summary.most_expensive_type"
              class="ml-2"
            />
          </div>
          <div>
            <span class="font-medium text-secondary">Most Frequent:</span>
            <Tag
              :variant="getTaskTypeVariant(data.summary.most_frequent_type)"
              :label="data.summary.most_frequent_type"
              class="ml-2"
            />
          </div>
          <div>
            <span class="font-medium text-secondary">Total Task Types:</span>
            <span class="ml-2 font-semibold">{{ data.summary.total_task_types }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Tag from '@/components/ui/Tag.vue'
import type { TaskTypeCostResponse, TaskTypeCostData } from '@/api/cost-analysis'

interface Props {
  data?: TaskTypeCostResponse
  loading?: boolean
  error?: boolean
}

const props = defineProps<Props>()

const sortBy = ref<keyof TaskTypeCostData>('total_cost')
const sortOrder = ref<'asc' | 'desc'>('desc')

const sort = (field: keyof TaskTypeCostData) => {
  if (sortBy.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = field
    sortOrder.value = field === 'task_type' ? 'asc' : 'desc'
  }
}

const getSortIcon = (field: keyof TaskTypeCostData) => {
  if (sortBy.value !== field) {
    return 'fa-sort text-border-2'
  }
  return sortOrder.value === 'asc'
    ? 'fa-sort-up text-sage-content'
    : 'fa-sort-down text-sage-content'
}

const sortedTaskTypes = computed(() => {
  if (!props.data?.task_types) return []

  return [...props.data.task_types].sort((a, b) => {
    let aVal = a[sortBy.value]
    let bVal = b[sortBy.value]

    if (typeof aVal === 'string') {
      aVal = aVal.toLowerCase()
      bVal = (bVal as string).toLowerCase()
    }

    if (aVal < bVal) return sortOrder.value === 'asc' ? -1 : 1
    if (aVal > bVal) return sortOrder.value === 'asc' ? 1 : -1
    return 0
  })
})

const formatNumber = (num: number) => {
  return new Intl.NumberFormat().format(Math.round(num))
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
  }).format(amount)
}

const getPercentage = (amount: number) => {
  const total = props.data?.summary.total_cost || 0
  return total > 0 ? ((amount / total) * 100).toFixed(1) : '0'
}

const getTaskCountPercentage = (count: number) => {
  const total = props.data?.summary.total_tasks || 0
  return total > 0 ? ((count / total) * 100).toFixed(1) : '0'
}

const getTaskTypeVariant = (taskType: string) => {
  const variants: Record<string, string> = {
    profile: 'primary',
    digital: 'info',
    timeline: 'success',
    products: 'warning',
    jobs: 'error',
    csr: 'slate',
    press: 'slate',
    team: 'slate',
  }
  return variants[taskType] || 'slate'
}

const getEfficiencyColor = (avgCost: number) => {
  if (!props.data?.task_types) return 'text-secondary'

  const allCosts = props.data.task_types.map((t) => t.avg_cost_per_task)
  const avgOfAll = allCosts.reduce((sum, cost) => sum + cost, 0) / allCosts.length

  if (avgCost < avgOfAll * 0.8) return 'text-success' // Very efficient
  if (avgCost < avgOfAll * 1.2) return 'text-info' // Average
  return 'text-warning' // Expensive
}

const getEfficiencyLabel = (avgCost: number) => {
  if (!props.data?.task_types) return ''

  const allCosts = props.data.task_types.map((t) => t.avg_cost_per_task)
  const avgOfAll = allCosts.reduce((sum, cost) => sum + cost, 0) / allCosts.length

  if (avgCost < avgOfAll * 0.8) return 'Very efficient'
  if (avgCost < avgOfAll * 1.2) return 'Average'
  return 'Expensive'
}
</script>
