<template>
  <Card padding="p-6" class="flex flex-col h-full">
    <h3 class="text-lg font-semibold">
      {{ $t('credits.usage.title', 'Répartition des crédits') }}
    </h3>

    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center py-12 flex-1">
      <div class="text-center">
        <i class="fa fa-spinner animate-spin text-2xl text-secondary mb-2"></i>
        <p class="text-sm text-secondary">{{ $t('common.loading', 'Chargement...') }}</p>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!hasData" class="flex justify-center py-12 flex-1">
      <div class="text-center">
        <i class="fa fa-chart-pie text-2xl text-secondary mb-2"></i>
        <p class="text-sm text-secondary">
          {{ $t('credits.usage.noData', 'Aucune consommation pour cette période') }}
        </p>
      </div>
    </div>

    <!-- Chart -->
    <template v-else>
      <!-- Chart and Legend -->
      <div class="flex items-center gap-6 flex-1">
        <!-- Donut Chart - takes available space -->
        <div class="flex-1 aspect-square max-w-[280px]">
          <Doughnut :data="chartData" :options="chartOptions" />
        </div>

        <!-- Legend -->
        <div class="flex flex-col gap-3">
          <CreditUsageLegendItem
            v-for="item in usageData"
            :key="item.module"
            :color="getModuleColor(item.module)"
            :label="item.label"
            :value="item.creditsConsumed"
            :percentage="item.percentage"
          />
        </div>
      </div>

      <!-- Total - at bottom -->
      <div class="pt-4 mt-auto border-t border-primary-stroke flex justify-between items-center">
        <span class="text-sm text-secondary">
          {{ $t('credits.usage.total', 'Total consommé') }}
        </span>
        <span class="font-semibold">
          {{ formattedTotal }}
          <span class="text-sm text-secondary ml-1">{{ $t('credits.unit', 'crédits') }}</span>
        </span>
      </div>
    </template>
  </Card>
</template>

<script setup lang="ts">
/**
 * Donut chart showing credit usage breakdown by module.
 */
import { computed } from 'vue'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
  type TooltipItem,
} from 'chart.js'
import { Doughnut } from 'vue-chartjs'
import type { ModuleUsage, ModuleName } from '@/types/credits'
import { MODULE_CHART_COLORS } from '@/types/credits'
import Card from '@/components/ui/Card.vue'
import CreditUsageLegendItem from './CreditUsageLegendItem.vue'

// Register Chart.js components
ChartJS.register(ArcElement, Tooltip, Legend)

interface Props {
  usageData: ModuleUsage[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const hasData = computed(() => {
  return props.usageData.some(item => item.creditsConsumed > 0)
})

const formattedTotal = computed(() => {
  const total = props.usageData.reduce((sum, item) => sum + item.creditsConsumed, 0)
  return total.toLocaleString()
})

const getModuleColor = (module: string): string => {
  return MODULE_CHART_COLORS[module as ModuleName] || '#888888'
}

const chartData = computed(() => {
  const labels = props.usageData.map(item => item.label)
  const data = props.usageData.map(item => item.creditsConsumed)
  const backgroundColor = props.usageData.map(item => getModuleColor(item.module))

  return {
    labels,
    datasets: [{
      data,
      backgroundColor,
      borderColor: 'transparent',
      borderWidth: 0,
      hoverOffset: 4,
    }],
  }
})

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: true,
  cutout: '65%',
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
        label: (context: TooltipItem<'doughnut'>) => {
          const value = context.parsed
          const percentage = props.usageData[context.dataIndex]?.percentage || 0
          return `${value.toLocaleString()} crédits (${percentage.toFixed(1)}%)`
        },
      },
    },
  },
}))
</script>
