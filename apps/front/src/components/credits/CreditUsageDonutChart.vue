<template>
  <Card padding="p-6" class="flex h-full flex-col">
    <h3 class="text-lg font-semibold">
      {{ $t('settings.credits.usage.title') }}
    </h3>

    <!-- Loading state -->
    <div v-if="loading" class="flex flex-1 justify-center py-12">
      <div class="text-center">
        <i class="fa fa-spinner text-neutral-black-font mb-2 animate-spin text-2xl"></i>
        <p class="text-neutral-black-font text-sm">{{ $t('common.loading') }}</p>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!hasData" class="flex flex-1 justify-center py-12">
      <div class="text-center">
        <i class="fa fa-chart-pie text-neutral-black-font mb-2 text-2xl"></i>
        <p class="text-neutral-black-font text-sm">
          {{ $t('settings.credits.usage.noData') }}
        </p>
      </div>
    </div>

    <!-- Chart -->
    <template v-else>
      <!-- Chart with positioned labels -->
      <div class="flex flex-1 items-center justify-center py-4">
        <div class="relative">
          <!-- Donut Chart -->
          <div class="h-[200px] w-[200px]">
            <Doughnut :data="chartData" :options="chartOptions" />
          </div>

          <!-- Label positions around the chart -->
          <!-- Top right -->
          <div
            v-if="usageData[2] && usageData[2].percentage > 0"
            class="absolute -top-2 -right-4 translate-x-full transform"
          >
            <span
              class="inline-block rounded-sm border px-2 py-1 text-xs font-medium whitespace-nowrap"
              :style="getLabelStyle(usageData[2].module)"
            >
              {{ usageData[2].percentage.toFixed(0) }}% de {{ usageData[2].label }}
            </span>
          </div>

          <!-- Right middle -->
          <div
            v-if="usageData[0] && usageData[0].percentage > 0"
            class="absolute top-1/2 -right-4 translate-x-full -translate-y-1/2 transform"
          >
            <span
              class="inline-block rounded-sm border px-2 py-1 text-xs font-medium whitespace-nowrap"
              :style="getLabelStyle(usageData[0].module)"
            >
              {{ usageData[0].percentage.toFixed(0) }}% de {{ usageData[0].label }}
            </span>
          </div>

          <!-- Bottom -->
          <div
            v-if="usageData[1] && usageData[1].percentage > 0"
            class="absolute -bottom-2 left-1/2 -translate-x-1/2 translate-y-full transform"
          >
            <span
              class="inline-block rounded-sm border px-2 py-1 text-xs font-medium whitespace-nowrap"
              :style="getLabelStyle(usageData[1].module)"
            >
              {{ usageData[1].percentage.toFixed(0) }}% de {{ usageData[1].label }}
            </span>
          </div>
        </div>
      </div>

      <!-- Total - at bottom -->
      <div
        class="border-primary-lighter-stroke mt-auto flex items-center justify-between border-t pt-4"
      >
        <span class="text-neutral-black-font text-sm">
          {{ $t('settings.credits.usage.total') }}
        </span>
        <span class="font-semibold">
          {{ formattedTotal }}
          <span class="text-neutral-black-font ml-1 text-sm">{{
            $t('settings.credits.unit')
          }}</span>
        </span>
      </div>
    </template>
  </Card>
</template>

<script setup lang="ts">
/**
 * Donut chart showing credit usage breakdown by module.
 */
import Card from '@/components/ui/Card.vue'
import type { ModuleName, ModuleUsage } from '@/types/credits'
import { MODULE_CHART_COLORS } from '@/types/credits'
import { ArcElement, Chart as ChartJS, Legend, Tooltip, type TooltipItem } from 'chart.js'
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'

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
  return props.usageData.some((item) => item.creditsConsumed > 0)
})

const formattedTotal = computed(() => {
  const total = props.usageData.reduce((sum, item) => sum + item.creditsConsumed, 0)
  return total.toLocaleString()
})

const getModuleColor = (module: string): string => {
  return MODULE_CHART_COLORS[module as ModuleName] || '#888888'
}

const getLabelStyle = (module: string) => {
  const color = getModuleColor(module)
  return {
    backgroundColor: color + '20',
    borderColor: color,
    color: color,
  }
}

const chartData = computed(() => {
  const labels = props.usageData.map((item) => item.label)
  const data = props.usageData.map((item) => item.creditsConsumed)
  const backgroundColor = props.usageData.map((item) => getModuleColor(item.module))

  return {
    labels,
    datasets: [
      {
        data,
        backgroundColor,
        borderColor: 'transparent',
        borderWidth: 0,
        hoverOffset: 4,
      },
    ],
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
