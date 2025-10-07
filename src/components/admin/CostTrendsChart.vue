<template>
  <div class="bg-base-100 rounded-lg border border-primary-stroke p-6 h-full">
    <h3 class="text-lg font-semibold mb-4">Cost Trends Over Time</h3>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-spinner animate-spin text-2xl text-primary-light-content mb-2"></i>
        <p class="text-sm text-primary-light-content">Loading chart data...</p>
      </div>
    </div>

    <div v-else-if="error" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-exclamation-triangle text-2xl text-error mb-2"></i>
        <p class="text-sm text-error">Failed to load chart data</p>
      </div>
    </div>

    <div v-else-if="!data?.trends.length" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-chart-line text-2xl text-primary-light-content mb-2"></i>
        <p class="text-sm text-primary-light-content">No trend data available</p>
      </div>
    </div>

    <div v-else class="h-80">
      <Line :data="chartData" :options="chartOptions" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'
import { Line } from 'vue-chartjs'
import type { CostTrendResponse } from '@/api/cost-analysis'

// Register ChartJS components
ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
)

interface Props {
  data?: CostTrendResponse
  loading?: boolean
  error?: boolean
}

const props = defineProps<Props>()

const chartData = computed(() => {
  if (!props.data?.trends) {
    return { labels: [], datasets: [] }
  }

  const labels = props.data.trends.map((trend) => {
    const date = new Date(trend.period)
    return props.data!.granularity === 'daily'
      ? date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
      : props.data!.granularity === 'weekly'
        ? `Week of ${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`
        : date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
  })

  return {
    labels,
    datasets: [
      {
        label: 'Daily Cost',
        data: props.data.trends.map((trend) => trend.total_cost),
        borderColor: 'rgb(99, 102, 241)', // primary color
        backgroundColor: 'rgba(99, 102, 241, 0.1)',
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBackgroundColor: 'rgb(99, 102, 241)',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
      },
    ],
  }
})

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleColor: '#fff',
      bodyColor: '#fff',
      borderColor: 'rgba(99, 102, 241, 0.5)',
      borderWidth: 1,
      callbacks: {
        label: (context: any) => {
          return `Cost: $${context.parsed.y.toFixed(4)}`
        },
      },
    },
  },
  scales: {
    x: {
      grid: {
        display: false,
      },
      ticks: {
        color: 'rgb(107, 114, 126)', // secondary text color
        maxTicksLimit: 10,
      },
    },
    y: {
      beginAtZero: true,
      grid: {
        color: 'rgba(107, 114, 126, 0.1)',
      },
      ticks: {
        color: 'rgb(107, 114, 126)',
        callback: (value: any) => `$${value.toFixed(2)}`,
      },
    },
  },
  elements: {
    point: {
      hoverBorderWidth: 3,
    },
  },
}))
</script>
