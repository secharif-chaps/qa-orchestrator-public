<template>
  <div class="flex flex-col gap-8">
    <!-- Header Section -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-3xl font-bold">{{ $t('admin.usage.title', 'Usage Dashboard') }}</h1>
        <p class="text-secondary mt-1">
          {{
            $t('admin.usage.description', 'View application usage metrics across all organizations')
          }}
        </p>
      </div>
      <UsageTimeRangeToggle v-model="selectedRange" @update:dates="handleDatesUpdate" />
    </div>

    <!-- Error State -->
    <Alert
      v-if="error"
      variant="danger"
      :title="$t('admin.usage.error.title', 'Error Loading Data')"
      :description="errorMessage"
      icon="fa-exclamation-triangle"
      :action="$t('admin.usage.error.retry', 'Retry')"
      @click="refetch"
    />

    <!-- Main Content -->
    <template v-else>
      <!-- KPI Grid Section -->
      <UsageKpiGrid :data="data" :loading="isLoading" />

      <!-- Charts Section -->
      <div class="flex flex-col gap-6">
        <UsageLineChart
          :data="data?.companies_over_time ?? []"
          :loading="isLoading"
          :start-date="chartStartDate"
          :end-date="chartEndDate"
        />
        <UsageStackedBarChart
          :time-series-data="data?.companies_over_time ?? []"
          :organization-data="data?.companies_by_organization ?? []"
          :loading="isLoading"
          :start-date="chartStartDate"
          :end-date="chartEndDate"
        />
      </div>

      <!-- Organization Table Section -->
      <OrganizationUsageTable :data="data?.companies_by_organization ?? []" :loading="isLoading" />
    </template>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.organizations
  title: 'Usage Dashboard'
</route>

<script setup lang="ts">
/**
 * Usage Dashboard admin page.
 *
 * Displays application usage metrics including:
 * - KPI cards (companies created, task success rate, active users)
 * - Line chart showing companies over time
 * - Stacked bar chart showing companies by organization
 * - Organization breakdown table
 *
 * Data is filtered by a configurable time range (7D, 30D, 90D, All time).
 */
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuery } from '@pinia/colada'
import { Alert } from '@owlint/feathers-vue'
import { usageStatsQuery } from '@/queries/admin-usage'
import UsageTimeRangeToggle from '@/components/admin/usage/UsageTimeRangeToggle.vue'
import UsageKpiGrid from '@/components/admin/usage/UsageKpiGrid.vue'
import UsageLineChart from '@/components/admin/usage/UsageLineChart.vue'
import UsageStackedBarChart from '@/components/admin/usage/UsageStackedBarChart.vue'
import OrganizationUsageTable from '@/components/admin/usage/OrganizationUsageTable.vue'

const { t } = useI18n()

/** Type for time range key */
type TimeRangeKey = '7d' | '30d' | '90d' | 'all'

/** Currently selected time range - default to 7 days */
const selectedRange = ref<TimeRangeKey>('7d')

/** Current date range for API query */
const dateRange = ref({
  startDate: getInitialStartDate(),
  endDate: getInitialEndDate(),
})

/**
 * Calculate initial start date (7 days ago).
 * Sets time to beginning of day in UTC.
 */
function getInitialStartDate(): string {
  const date = new Date()
  date.setDate(date.getDate() - 7)
  date.setUTCHours(0, 0, 0, 0)
  return date.toISOString()
}

/**
 * Calculate initial end date (today at end of day).
 * Sets time to end of day in UTC.
 */
function getInitialEndDate(): string {
  const date = new Date()
  date.setUTCHours(23, 59, 59, 999)
  return date.toISOString()
}

/**
 * Handle date updates from the time range toggle component.
 * Updates the date range which triggers query refetch.
 */
const handleDatesUpdate = (dates: { startDate: string | null; endDate: string }) => {
  dateRange.value = {
    // Use a very old date for 'all time' when startDate is null
    startDate: dates.startDate ?? '2020-01-01',
    endDate: dates.endDate,
  }
}

/**
 * Start date for charts (date-only format YYYY-MM-DD).
 * Returns null for "all time" to indicate no date filling should occur.
 */
const chartStartDate = computed((): string | null => {
  // For "all time" mode, return null so charts show only actual data
  if (selectedRange.value === 'all') return null
  // Extract date-only portion if it contains time
  return dateRange.value.startDate.split('T')[0]
})

/**
 * End date for charts (date-only format YYYY-MM-DD).
 */
const chartEndDate = computed((): string => {
  // Extract date-only portion if it contains time
  return dateRange.value.endDate.split('T')[0]
})

// Query usage statistics with reactive date range
const { data, isLoading, error, refetch } = useQuery(() =>
  usageStatsQuery({
    startDate: dateRange.value.startDate,
    endDate: dateRange.value.endDate,
  }),
)

/**
 * Extract error message from the error object.
 * Handles various error types safely.
 */
const errorMessage = computed((): string => {
  const defaultMessage = t('admin.usage.error.message', 'Failed to load usage statistics')
  if (!error.value) return defaultMessage
  if (error.value instanceof Error) return error.value.message
  if (typeof error.value === 'object' && 'message' in error.value) {
    return String((error.value as { message: unknown }).message)
  }
  return defaultMessage
})
</script>
