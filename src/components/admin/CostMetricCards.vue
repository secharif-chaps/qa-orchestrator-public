<template>
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="bg-base-100 rounded-lg border border-primary-stroke p-4">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-primary-light-content">Total Cost</p>
          <p class="text-2xl font-bold text-primary-light-content">
            {{ formatCurrency(data?.global_summary.total_cost || 0) }}
          </p>
        </div>
        <i class="fa fa-dollar-sign text-2xl text-primary-light-content"></i>
      </div>
    </div>

    <div class="bg-base-100 rounded-lg border border-primary-stroke p-4">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-primary-light-content">Total Tasks</p>
          <p class="text-2xl font-bold">
            {{ formatNumber(data?.global_summary.total_tasks || 0) }}
          </p>
        </div>
        <i class="fa fa-tasks text-2xl text-info"></i>
      </div>
    </div>

    <div class="bg-base-100 rounded-lg border border-primary-stroke p-4">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-primary-light-content">Total Companies</p>
          <p class="text-2xl font-bold">
            {{ formatNumber(data?.global_summary.total_companies || 0) }}
          </p>
        </div>
        <i class="fa fa-building text-2xl text-success"></i>
      </div>
    </div>

    <div class="bg-base-100 rounded-lg border border-primary-stroke p-4">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-primary-light-content">Avg Cost / Company</p>
          <p class="text-2xl font-bold text-warning">
            {{ formatCurrency(data?.global_summary.avg_cost_per_company || 0) }}
          </p>
        </div>
        <i class="fa fa-chart-line text-2xl text-warning"></i>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { GlobalCostResponse } from '@/api/cost-analysis'

interface Props {
  data?: GlobalCostResponse
}

defineProps<Props>()

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
</script>
