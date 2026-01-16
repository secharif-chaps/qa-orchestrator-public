<template>
  <div class="h-64">
    <Bar :data="chartData" :options="chartOptions" />
  </div>
</template>

<script setup lang="ts">
/**
 * Bar chart showing daily credit usage over time.
 */
import { computed } from 'vue'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  type TooltipItem,
} from 'chart.js'
import { Bar } from 'vue-chartjs'
import type { DailyUsage } from '@/types/credits'

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

interface Props {
  dailyUsage: DailyUsage[]
}

const props = defineProps<Props>()

const formatDate = (dateStr: string): string => {
  const date = new Date(dateStr)
  return date.toLocaleDateString('fr-FR', {
    month: 'short',
    day: 'numeric',
  })
}

const chartData = computed(() => {
  const labels = props.dailyUsage.map(item => formatDate(item.date))
  const data = props.dailyUsage.map(item => item.creditsConsumed)

  return {
    labels,
    datasets: [{
      label: 'Crédits consommés',
      data,
      backgroundColor: 'rgba(139, 175, 156, 0.6)', // Primary color with opacity
      borderColor: 'rgb(139, 175, 156)',
      borderWidth: 1,
      borderRadius: 4,
    }],
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
      padding: 12,
      callbacks: {
        label: (context: TooltipItem<'bar'>) => {
          return `${context.parsed.y.toLocaleString()} crédits`
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
        color: '#6b7280',
        font: {
          size: 11,
        },
        maxRotation: 45,
      },
    },
    y: {
      beginAtZero: true,
      grid: {
        color: 'rgba(107, 114, 128, 0.1)',
      },
      ticks: {
        color: '#6b7280',
        font: {
          size: 11,
        },
        precision: 0,
      },
    },
  },
}))
</script>
