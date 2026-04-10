<template>
  <div class="border-primary-lighter-stroke rounded-sm border bg-white p-6">
    <h3 class="mb-4 text-lg font-semibold">{{ t('admin.costChart.title') }}</h3>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-spinner text-neutral-black-font mb-2 animate-spin text-2xl"></i>
        <p class="text-neutral-black-font text-sm">{{ t('admin.costChart.loading') }}</p>
      </div>
    </div>

    <div v-else-if="error" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-exclamation-triangle text-error mb-2 text-2xl"></i>
        <p class="text-error text-sm">{{ t('admin.costChart.error') }}</p>
      </div>
    </div>

    <div v-else-if="!data?.workspaces.length" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-chart-pie text-neutral-black-font mb-2 text-2xl"></i>
        <p class="text-neutral-black-font text-sm">{{ t('admin.costChart.noData') }}</p>
      </div>
    </div>

    <div v-else>
      <div class="flex h-80 justify-center">
        <Doughnut :data="chartData" :options="chartOptions" />
      </div>

      <!-- Legend -->
      <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
        <div
          v-for="(workspace, index) in data?.workspaces"
          :key="workspace.workspace_id"
          class="flex items-center gap-2"
        >
          <div
            class="h-3 w-3 flex-shrink-0 rounded-full"
            :style="{ backgroundColor: colors[index % colors.length] }"
          ></div>
          <span class="text-neutral-black-font truncate text-sm">{{
            workspace.workspace_name
          }}</span>
          <span class="ml-auto text-sm font-medium">${{ workspace.total_cost.toFixed(2) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { WorkspaceCostResponse } from '@/api/cost-analysis'
import { ArcElement, Chart as ChartJS, Legend, Tooltip, type TooltipItem } from 'chart.js'
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

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
        label: (context: TooltipItem<'doughnut'>) => {
          const workspace = props.data?.workspaces[context.dataIndex]
          if (!workspace) return ''

          const total = props.data?.summary.total_cost || 0
          const percentage = total > 0 ? ((workspace.total_cost / total) * 100).toFixed(1) : '0'

          return [
            `${workspace.workspace_name}`,
            `${t('admin.costChart.tooltip.cost')}: $${workspace.total_cost.toFixed(4)}`,
            `${t('admin.costChart.tooltip.percentage')}: ${percentage}%`,
            `${t('admin.costChart.tooltip.tasks')}: ${workspace.task_count}`,
            `${t('admin.costChart.tooltip.companies')}: ${workspace.company_count}`,
          ]
        },
      },
    },
  },
  cutout: '60%', // Creates donut effect
}))
</script>
