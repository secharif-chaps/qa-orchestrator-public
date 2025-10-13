<template>
  <div class="bg-base-100 rounded-lg border border-primary-stroke p-6">
    <h3 class="text-lg font-semibold mb-4">Workspace Cost Distribution</h3>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-spinner animate-spin text-2xl text-secondary mb-2"></i>
        <p class="text-sm text-secondary">Loading chart data...</p>
      </div>
    </div>

    <div v-else-if="error" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-exclamation-triangle text-2xl text-error mb-2"></i>
        <p class="text-sm text-error">Failed to load chart data</p>
      </div>
    </div>

    <div v-else-if="!data?.workspaces.length" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-chart-pie text-2xl text-secondary mb-2"></i>
        <p class="text-sm text-secondary">No workspace data available</p>
      </div>
    </div>

    <div v-else>
      <div class="h-80 flex justify-center">
        <Doughnut :data="chartData" :options="chartOptions" />
      </div>

      <!-- Legend -->
      <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
        <div
          v-for="(workspace, index) in data?.workspaces"
          :key="workspace.workspace_id"
          class="flex items-center gap-2"
        >
          <div
            class="w-3 h-3 rounded-full flex-shrink-0"
            :style="{ backgroundColor: colors[index % colors.length] }"
          ></div>
          <span class="text-sm text-secondary truncate">{{ workspace.workspace_name }}</span>
          <span class="text-sm font-medium ml-auto">${{ workspace.total_cost.toFixed(2) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { Doughnut } from 'vue-chartjs'
import type { WorkspaceCostResponse } from '@/api/cost-analysis'

// Register ChartJS components
ChartJS.register(ArcElement, Tooltip, Legend)

interface Props {
  data?: WorkspaceCostResponse
  loading?: boolean
  error?: boolean
}

const props = defineProps<Props>()

// Color palette for workspaces
const colors = [
  'rgb(99, 102, 241)', // primary
  'rgb(16, 185, 129)', // success
  'rgb(245, 158, 11)', // warning
  'rgb(239, 68, 68)', // error
  'rgb(20, 184, 166)', // teal
  'rgb(168, 85, 247)', // purple
  'rgb(59, 130, 246)', // blue
  'rgb(34, 197, 94)', // green
]

const chartData = computed(() => {
  if (!props.data?.workspaces) {
    return { labels: [], datasets: [] }
  }

  return {
    labels: props.data.workspaces.map((w) => w.workspace_name),
    datasets: [
      {
        data: props.data.workspaces.map((w) => w.total_cost),
        backgroundColor: colors.slice(0, props.data.workspaces.length),
        borderColor: colors
          .slice(0, props.data.workspaces.length)
          .map((color) => color.replace('rgb', 'rgba').replace(')', ', 0.8)')),
        borderWidth: 2,
        hoverBorderWidth: 3,
      },
    ],
  }
})

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false, // We'll use custom legend
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleColor: '#fff',
      bodyColor: '#fff',
      borderColor: 'rgba(99, 102, 241, 0.5)',
      borderWidth: 1,
      callbacks: {
        label: (context: any) => {
          const workspace = props.data?.workspaces[context.dataIndex]
          if (!workspace) return ''

          const total = props.data?.summary.total_cost || 0
          const percentage = total > 0 ? ((workspace.total_cost / total) * 100).toFixed(1) : '0'

          return [
            `${workspace.workspace_name}`,
            `Cost: $${workspace.total_cost.toFixed(4)}`,
            `Percentage: ${percentage}%`,
            `Tasks: ${workspace.task_count}`,
            `Companies: ${workspace.company_count}`,
          ]
        },
      },
    },
  },
  cutout: '60%', // Creates donut effect
}))
</script>
