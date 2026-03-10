<template>
  <div class="bg-base-100 border-primary-stroke rounded-lg border p-6">
    <h3 class="mb-4 text-lg font-semibold">{{ t('admin.usage.lineChart.title') }}</h3>

    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-spinner text-secondary mb-2 animate-spin text-2xl"></i>
        <p class="text-secondary text-sm">
          {{ t('admin.usage.lineChart.loading', 'Loading chart data...') }}
        </p>
      </div>
    </div>

    <!-- Empty state - only show when no date range and no data -->
    <div
      v-else-if="!startDate && (!data || data.length === 0)"
      data-testid="empty-state"
      class="flex justify-center py-12"
    >
      <div class="text-center">
        <i class="fa fa-chart-line text-secondary mb-2 text-2xl"></i>
        <p class="text-secondary text-sm">
          {{ t('admin.usage.lineChart.noData', 'No company data for selected period') }}
        </p>
      </div>
    </div>

    <!-- Chart -->
    <div v-else class="h-80">
      <Line data-testid="line-chart" :data="chartData" :options="chartOptions" />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Line chart component for displaying companies created over time.
 *
 * Shows a line chart with time periods on the X-axis and company counts
 * on the Y-axis. Uses the primary color from the design system palette.
 *
 * Features:
 * - Responsive design that maintains aspect ratio
 * - Tooltips showing exact count on hover
 * - Loading and empty states
 * - Formatted date labels
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
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
import type { TimeSeriesDataPoint } from '@/types/usage'

const { t } = useI18n()

// Register Chart.js components (including Filler for area fill)
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
  /** Time series data points for the chart */
  data: TimeSeriesDataPoint[]
  /** Whether data is currently loading */
  loading: boolean
  /** Start date of the range (YYYY-MM-DD format) */
  startDate: string | null
  /** End date of the range (YYYY-MM-DD format) */
  endDate: string
}

const props = defineProps<Props>()

/** Pink accent color from design system palette (rose-500) */
const primaryColor = 'rgb(184, 150, 187)'
const primaryColorAlpha = 'rgba(184, 150, 187, 0.15)'

/**
 * Format ISO date string to a readable format.
 * Shows month and day for short ranges, includes year for longer ranges.
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
 * Transform time series data into Chart.js format.
 * Fills in missing dates with 0 values to show the full period.
 */
const chartData = computed(() => {
  // Fill in all dates in the range
  const filledData = fillDateRange(props.data ?? [], props.startDate, props.endDate)

  if (filledData.length === 0) {
    return { labels: [], datasets: [] }
  }

  return {
    labels: filledData.map((point) => formatDate(point.period)),
    datasets: [
      {
        label: t('admin.usage.lineChart.label'),
        data: filledData.map((point) => point.count),
        borderColor: primaryColor,
        backgroundColor: primaryColorAlpha,
        fill: true,
        tension: 0.3,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBackgroundColor: primaryColor,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
      },
    ],
  }
})

/**
 * Chart.js options for line chart configuration.
 */
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
      borderColor: 'rgba(184, 150, 187, 0.5)',
      borderWidth: 1,
      padding: 12,
      displayColors: false,
      callbacks: {
        title: (tooltipItems: { label: string }[]) => {
          return tooltipItems[0]?.label || ''
        },
        label: (context: { parsed: { y: number | null } }) => {
          const count = context.parsed.y ?? 0
          return t('admin.usage.lineChart.tooltip', { count }, count)
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
          size: 12,
        },
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
