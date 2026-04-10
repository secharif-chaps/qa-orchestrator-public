<template>
  <div class="border-primary-lighter-stroke rounded-sm border bg-white p-6">
    <h3 class="mb-4 text-lg font-semibold">{{ t('admin.usage.stackedChart.title') }}</h3>

    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-spinner text-neutral-black-font mb-2 animate-spin text-2xl"></i>
        <p class="text-neutral-black-font text-sm">
          {{ t('admin.usage.stackedChart.loading') }}
        </p>
      </div>
    </div>

    <!-- Empty state - only show when no date range and no data -->
    <div
      v-else-if="!startDate && (!timeSeriesData || timeSeriesData.length === 0)"
      data-testid="empty-state"
      class="flex justify-center py-12"
    >
      <div class="text-center">
        <i class="fa fa-chart-bar text-neutral-black-font mb-2 text-2xl"></i>
        <p class="text-neutral-black-font text-sm">
          {{ t('admin.usage.stackedChart.noData') }}
        </p>
      </div>
    </div>

    <!-- Chart -->
    <template v-else>
      <div class="h-80">
        <Bar data-testid="bar-chart" :data="chartData" :options="chartOptions" />
      </div>

      <!-- Custom Legend -->
      <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-5">
        <div
          v-for="(org, index) in limitedOrganizations"
          :key="org.organization_id"
          class="flex items-center gap-2"
        >
          <div
            class="h-3 w-3 flex-shrink-0 rounded-full"
            :style="{ backgroundColor: colors[index % colors.length] }"
          ></div>
          <span class="text-neutral-black-font truncate text-sm">{{ org.organization_name }}</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
/**
 * Stacked bar chart component for displaying companies by organization over time.
 *
 * Shows a stacked bar chart with time periods on the X-axis and company counts
 * on the Y-axis, with each organization represented as a distinct color in the stack.
 *
 * Features:
 * - Stacked bars showing organization breakdown per period
 * - Custom legend showing organization names with color indicators
 * - Limits to top 10 organizations with "Other" category
 * - Loading and empty states
 */
import type { OrganizationBreakdown, TimeSeriesDataPoint } from '@/types/usage'
import {
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Legend,
  LinearScale,
  Title,
  Tooltip,
  type TooltipItem,
} from 'chart.js'
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

interface Props {
  /** Time series data points for the X-axis periods */
  timeSeriesData: TimeSeriesDataPoint[]
  /** Organization breakdown data for stacking */
  organizationData: OrganizationBreakdown[]
  /** Whether data is currently loading */
  loading: boolean
  /** Start date of the range (YYYY-MM-DD format) */
  startDate: string | null
  /** End date of the range (YYYY-MM-DD format) */
  endDate: string
}

const props = defineProps<Props>()

/** Color palette using pink accent as primary from design system */
const colors = [
  'rgb(184, 150, 187)', // rose-500 (pink accent - primary)
  'rgb(239, 201, 243)', // rose-200 (lighter pink)
  'rgb(146, 110, 155)', // rose-700 (darker pink)
  'rgb(16, 185, 129)', // emerald
  'rgb(245, 158, 11)', // amber
  'rgb(20, 184, 166)', // teal
  'rgb(59, 130, 246)', // blue
  'rgb(34, 197, 94)', // green
  'rgb(156, 163, 175)', // gray (for "Other")
]

/** Maximum number of organizations to show individually */
const MAX_ORGANIZATIONS = 10

/**
 * Limit organizations to top 10, using the "Other" category color for remainder.
 */
const limitedOrganizations = computed(() => {
  if (!props.organizationData) return []
  return props.organizationData.slice(0, MAX_ORGANIZATIONS)
})

/**
 * Format ISO date string to a readable format.
 */
const formatDate = (isoDate: string): string => {
  const date = new Date(isoDate)
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
  })
}

/**
 * Normalize a date string to YYYY-MM-DD format.
 * Handles both ISO datetime (2025-12-18T00:00:00+00:00) and date-only (2025-12-18) formats.
 */
const normalizeDateKey = (dateStr: string): string => {
  return dateStr.split('T')[0]
}

/**
 * Generate all dates in a range and fill with data or 0 for missing days.
 * This ensures the chart shows the full period even if some days have no data.
 */
const fillDateRange = (
  data: TimeSeriesDataPoint[],
  startDate: string | null,
  endDate: string,
): TimeSeriesDataPoint[] => {
  // If no start date (all time), just return the data as-is
  if (!startDate) return data

  const result: TimeSeriesDataPoint[] = []
  // Normalize API dates to YYYY-MM-DD format for consistent lookup
  const dataMap = new Map(data.map((point) => [normalizeDateKey(point.period), point.count]))

  // Parse dates and iterate through each day
  const start = new Date(startDate)
  const end = new Date(endDate)

  // Iterate through each day in the range
  const current = new Date(start)
  while (current <= end) {
    const dateStr = current.toISOString().split('T')[0]
    result.push({
      period: dateStr,
      count: dataMap.get(dateStr) ?? 0,
    })
    current.setDate(current.getDate() + 1)
  }

  return result
}

/**
 * Transform data into Chart.js stacked bar format.
 *
 * Each organization becomes a dataset with its own color.
 * The data for each dataset contains the counts per time period.
 * Since we only have total counts per org (not per period per org),
 * we distribute proportionally based on total period counts.
 * Fills in missing dates with 0 values to show the full period.
 */
const chartData = computed(() => {
  // Fill in all dates in the range
  const filledData = fillDateRange(props.timeSeriesData ?? [], props.startDate, props.endDate)

  if (filledData.length === 0) {
    return { labels: [], datasets: [] }
  }

  const labels = filledData.map((point) => formatDate(point.period))
  const totalCount = filledData.reduce((sum, point) => sum + point.count, 0)

  // Create a dataset for each organization
  const datasets = limitedOrganizations.value.map((org, index) => {
    // Calculate proportional distribution across periods
    // Each org's data is distributed based on the period's share of total
    const data = filledData.map((point) => {
      if (totalCount === 0) return 0
      // Distribute this org's companies proportionally across periods
      const periodShare = point.count / totalCount
      return Math.round(org.companies_count * periodShare * 10) / 10
    })

    // Use gray for "Other" category, otherwise use color from palette
    const isOther = org.organization_id === 'other' || org.organization_name === 'Other'
    const colorIndex = isOther ? colors.length - 1 : index % (colors.length - 1)

    return {
      label: org.organization_name,
      data,
      backgroundColor: colors[colorIndex],
      borderColor: colors[colorIndex].replace('rgb', 'rgba').replace(')', ', 0.8)'),
      borderWidth: 1,
      borderRadius: 4,
    }
  })

  return { labels, datasets }
})

/**
 * Chart.js options for stacked bar chart configuration.
 */
const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false, // Use custom legend
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleColor: '#fff',
      bodyColor: '#fff',
      borderColor: 'rgba(184, 150, 187, 0.5)',
      borderWidth: 1,
      padding: 12,
      callbacks: {
        label: (context: TooltipItem<'bar'>) => {
          const count = context.parsed.y ?? 0
          const label = context.dataset.label || 'Unknown'
          return `${label}: ${count.toFixed(1)} ${t('admin.usage.stackedChart.companies')}`
        },
      },
    },
  },
  scales: {
    x: {
      stacked: true,
      grid: {
        display: false,
      },
      ticks: {
        color: '#6b7280',
        font: {
          size: 12,
        },
      },
    },
    y: {
      stacked: true,
      beginAtZero: true,
      grid: {
        color: 'rgba(107, 114, 128, 0.1)',
      },
      ticks: {
        color: '#6b7280',
        font: {
          size: 12,
        },
        precision: 0,
      },
    },
  },
  interaction: {
    intersect: false,
    mode: 'index' as const,
  },
}))
</script>
