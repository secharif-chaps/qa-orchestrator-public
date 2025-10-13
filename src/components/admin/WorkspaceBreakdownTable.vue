<template>
  <div class="bg-base-100 rounded-lg border border-primary-stroke">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h3 class="text-lg font-semibold">Workspace Cost Breakdown</h3>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-spinner animate-spin text-2xl text-secondary mb-2"></i>
        <p class="text-sm text-secondary">Loading workspace data...</p>
      </div>
    </div>

    <div v-else-if="error" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-exclamation-triangle text-2xl text-error mb-2"></i>
        <p class="text-sm text-error">Failed to load workspace data</p>
      </div>
    </div>

    <div v-else-if="!data?.workspaces.length" class="text-center py-12">
      <i class="fa fa-database text-4xl text-secondary mb-4"></i>
      <p class="text-lg font-medium text-secondary">No workspace data available</p>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-base-200">
          <tr>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('workspace_name')"
            >
              <div class="flex items-center gap-1">
                Workspace
                <i class="fa text-xs" :class="getSortIcon('workspace_name')"></i>
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
                Tasks
                <i class="fa text-xs" :class="getSortIcon('task_count')"></i>
              </div>
            </th>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('company_count')"
            >
              <div class="flex items-center gap-1">
                Companies
                <i class="fa text-xs" :class="getSortIcon('company_count')"></i>
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
              @click="sort('avg_cost_per_company')"
            >
              <div class="flex items-center gap-1">
                Avg Cost/Company
                <i class="fa text-xs" :class="getSortIcon('avg_cost_per_company')"></i>
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-primary-stroke">
          <tr
            v-for="workspace in sortedWorkspaces"
            :key="workspace.workspace_id"
            class="hover:bg-base-200 transition-colors"
          >
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mr-3"></div>
                <div>
                  <div class="text-sm font-medium">{{ workspace.workspace_name }}</div>
                  <div class="text-xs text-secondary">ID: {{ workspace.workspace_id }}</div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-semibold text-secondary">
                {{ formatCurrency(workspace.total_cost) }}
              </div>
              <div class="text-xs text-secondary">
                {{ getPercentage(workspace.total_cost) }}% of total
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium">{{ formatNumber(workspace.task_count) }}</div>
              <div class="text-xs text-secondary">
                {{
                  workspace.total_input_tokens + workspace.total_output_tokens > 0
                    ? formatNumber(workspace.total_input_tokens + workspace.total_output_tokens) +
                      ' tokens'
                    : 'No tokens'
                }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium">{{ formatNumber(workspace.company_count) }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-info">
                {{ formatCurrency(workspace.avg_cost_per_task) }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-warning">
                {{ formatCurrency(workspace.avg_cost_per_company) }}
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { WorkspaceCostResponse, WorkspaceCostData } from '@/api/cost-analysis'

interface Props {
  data?: WorkspaceCostResponse
  loading?: boolean
  error?: boolean
}

const props = defineProps<Props>()

const sortBy = ref<keyof WorkspaceCostData>('total_cost')
const sortOrder = ref<'asc' | 'desc'>('desc')

const sort = (field: keyof WorkspaceCostData) => {
  if (sortBy.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = field
    sortOrder.value = field === 'workspace_name' ? 'asc' : 'desc'
  }
}

const getSortIcon = (field: keyof WorkspaceCostData) => {
  if (sortBy.value !== field) {
    return 'fa-sort text-border-2'
  }
  return sortOrder.value === 'asc'
    ? 'fa-sort-up text-sage-content'
    : 'fa-sort-down text-sage-content'
}

const sortedWorkspaces = computed(() => {
  if (!props.data?.workspaces) return []

  return [...props.data.workspaces].sort((a, b) => {
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

const getPercentage = (amount: number) => {
  const total = props.data?.summary.total_cost || 0
  return total > 0 ? ((amount / total) * 100).toFixed(1) : '0'
}
</script>
