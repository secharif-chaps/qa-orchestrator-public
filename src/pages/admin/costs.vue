<template>
  <div class="min-h-screen bg-bg3">
    <div class="container mx-auto px-4 py-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-3xl font-bold">
              {{ $t('admin.costs.title', 'Cost Analysis') }}
            </h1>
            <p class="text-secondary mt-2">
              {{ $t('admin.costs.description', 'Monitor token usage and costs for MINT screening workflows') }}
            </p>
          </div>

          <!-- Back to Admin Dashboard -->
          <Button
            variant="tertiary"
            icon="fa fa-arrow-left"
            :label="$t('admin.dashboard.back', 'Back to Admin')"
            @click="$router.push('/admin')"
          />
        </div>

        <!-- Cost Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-bg1 rounded-lg border border-border-2 p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-secondary">Total Screenings</p>
                <p class="text-2xl font-bold">{{ totalScreenings }}</p>
              </div>
              <i class="fa fa-search text-2xl text-primary"></i>
            </div>
          </div>
          
          <div class="bg-bg1 rounded-lg border border-border-2 p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-secondary">Total Input Tokens</p>
                <p class="text-2xl font-bold">{{ formatNumber(totalInputTokens) }}</p>
              </div>
              <i class="fa fa-arrow-down text-2xl text-success"></i>
            </div>
          </div>
          
          <div class="bg-bg1 rounded-lg border border-border-2 p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-secondary">Total Output Tokens</p>
                <p class="text-2xl font-bold">{{ formatNumber(totalOutputTokens) }}</p>
              </div>
              <i class="fa fa-arrow-up text-2xl text-info"></i>
            </div>
          </div>
          
          <div class="bg-bg1 rounded-lg border border-border-2 p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-secondary">Total Cost</p>
                <p class="text-2xl font-bold text-error">${{ totalCost.toFixed(4) }}</p>
              </div>
              <i class="fa fa-dollar-sign text-2xl text-error"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center py-16">
        <div class="text-center">
          <i class="fa fa-spinner animate-spin text-4xl text-primary mb-4"></i>
          <p class="text-secondary">{{ $t('admin.costs.loading', 'Loading cost data...') }}</p>
        </div>
      </div>

      <!-- Error State -->
      <Alert
        v-else-if="error"
        variant="error"
        :title="$t('admin.costs.error.title', 'Failed to Load Cost Data')"
        :message="error"
        icon="fa fa-exclamation-triangle"
        class="mb-6"
      />

      <!-- Cost Data Table -->
      <div v-else class="bg-bg1 rounded-lg border border-border-2 overflow-hidden">
        <div class="px-6 py-4 border-b border-border-2">
          <h2 class="text-lg font-semibold">{{ $t('admin.costs.breakdown.title', 'Cost Breakdown by Task Type') }}</h2>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-bg2">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">
                  {{ $t('admin.costs.table.taskType', 'Task Type') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">
                  {{ $t('admin.costs.table.count', 'Count') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">
                  {{ $t('admin.costs.table.inputTokens', 'Input Tokens') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">
                  {{ $t('admin.costs.table.outputTokens', 'Output Tokens') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">
                  {{ $t('admin.costs.table.totalCost', 'Total Cost') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">
                  {{ $t('admin.costs.table.avgCost', 'Avg Cost/Task') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border-2">
              <tr v-for="breakdown in costBreakdown" :key="breakdown.taskType" class="hover:bg-bg2">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <Badge :variant="getTaskTypeVariant(breakdown.taskType)" :label="breakdown.taskType" />
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  {{ breakdown.count }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary">
                  {{ formatNumber(breakdown.inputTokens) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary">
                  {{ formatNumber(breakdown.outputTokens) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-error">
                  ${{ breakdown.totalCost.toFixed(4) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary">
                  ${{ breakdown.averageCost.toFixed(4) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Empty State -->
        <div v-if="costBreakdown.length === 0" class="text-center py-12">
          <i class="fa fa-chart-line text-4xl text-secondary mb-4"></i>
          <p class="text-lg font-medium text-secondary">{{ $t('admin.costs.empty', 'No cost data available yet') }}</p>
          <p class="text-sm text-secondary mt-2">
            {{ $t('admin.costs.emptyDescription', 'Token usage will appear here once workflows with token tracking are completed') }}
          </p>
        </div>
      </div>

      <!-- Pricing Information -->
      <div class="mt-8 bg-bg1 rounded-lg border border-border-2 p-6">
        <h3 class="text-lg font-semibold mb-4">{{ $t('admin.costs.pricing.title', 'Claude Sonnet 4 Pricing') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex items-center justify-between p-3 bg-bg2 rounded-lg">
            <span class="text-sm font-medium">{{ $t('admin.costs.pricing.inputCost', 'Input Cost (per 1M tokens)') }}</span>
            <span class="font-bold text-success">$3.15</span>
          </div>
          <div class="flex items-center justify-between p-3 bg-bg2 rounded-lg">
            <span class="text-sm font-medium">{{ $t('admin.costs.pricing.outputCost', 'Output Cost (per 1M tokens)') }}</span>
            <span class="font-bold text-info">$15.75</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workflow
</route>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { companiesApi } from '@/api/companies'
import type { Company } from '@/types/company'
import type { TaskResponse, TaskType } from '@/types/task'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import Badge from '@/components/ui/Badge.vue'

const router = useRouter()

// Reactive state
const companies = ref<Company[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

// Load companies on mount
onMounted(async () => {
  await loadCompanies()
})

// Load all companies with their tasks
const loadCompanies = async () => {
  try {
    loading.value = true
    error.value = null
    const response = await companiesApi.getCompanies()
    companies.value = response.data
  } catch (err) {
    console.error('Failed to load companies:', err)
    error.value = err instanceof Error ? err.message : 'An unexpected error occurred'
  } finally {
    loading.value = false
  }
}

// Get all tasks with token data
const allTasksWithTokens = computed(() => {
  return companies.value.flatMap(company => 
    company.tasks.filter(task => 
      task.input_tokens !== null || task.output_tokens !== null || task.total_cost !== null
    )
  )
})

// Calculate totals
const totalScreenings = computed(() => allTasksWithTokens.value.length)
const totalInputTokens = computed(() => 
  allTasksWithTokens.value.reduce((sum, task) => sum + (task.input_tokens || 0), 0)
)
const totalOutputTokens = computed(() => 
  allTasksWithTokens.value.reduce((sum, task) => sum + (task.output_tokens || 0), 0)
)
const totalCost = computed(() => 
  allTasksWithTokens.value.reduce((sum, task) => sum + (task.total_cost || 0), 0)
)

// Cost breakdown by task type
const costBreakdown = computed(() => {
  const breakdown: Record<TaskType, {
    count: number
    inputTokens: number
    outputTokens: number
    totalCost: number
  }> = {} as any

  allTasksWithTokens.value.forEach(task => {
    if (!breakdown[task.type]) {
      breakdown[task.type] = {
        count: 0,
        inputTokens: 0,
        outputTokens: 0,
        totalCost: 0
      }
    }
    
    breakdown[task.type].count++
    breakdown[task.type].inputTokens += task.input_tokens || 0
    breakdown[task.type].outputTokens += task.output_tokens || 0
    breakdown[task.type].totalCost += task.total_cost || 0
  })

  return Object.entries(breakdown).map(([taskType, data]) => ({
    taskType: taskType as TaskType,
    ...data,
    averageCost: data.count > 0 ? data.totalCost / data.count : 0
  }))
})

// Utility functions
const formatNumber = (num: number) => {
  return new Intl.NumberFormat().format(num)
}

const getTaskTypeVariant = (taskType: TaskType) => {
  const variants: Record<TaskType, string> = {
    profile: 'primary',
    digital: 'info',
    timeline: 'success',
    products: 'warning',
    jobs: 'error',
    csr: 'slate',
    press: 'purple',
    team: 'teal'
  }
  return variants[taskType] || 'slate'
}
</script>