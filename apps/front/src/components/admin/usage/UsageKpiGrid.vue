<template>
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <!-- Companies Created KPI -->
    <StatCard
      :title="t('admin.usage.kpi.companiesCreated', 'Companies Created')"
      :value="formattedCompaniesCount"
      icon="building"
      color="indigo"
      :loading="loading"
    />

    <!-- Task Success Rate KPI -->
    <StatCard
      :title="t('admin.usage.kpi.taskSuccessRate', 'Task Success Rate')"
      :value="formattedSuccessRate"
      icon="check-circle"
      color="green"
      :loading="loading"
    />

    <!-- Active Users KPI -->
    <StatCard
      :title="t('admin.usage.kpi.activeUsers', 'Active Users')"
      :value="formattedActiveUsers"
      icon="users"
      color="purple"
      :loading="loading"
    />

    <!-- Azure Cost placeholder KPI -->
    <div class="opacity-50">
      <StatCard
        :title="t('admin.usage.kpi.azureCost', 'Azure Cost')"
        :value="t('common.comingSoon', 'Coming soon')"
        :subtitle="t('admin.usage.kpi.phase2Feature', 'Phase 2 feature')"
        icon="dollar-sign"
        color="orange"
        :loading="loading"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * KPI grid component for the usage dashboard.
 *
 * Displays 4 key performance indicators:
 * - Companies Created: Total companies in selected period
 * - Task Success Rate: Percentage of successful tasks
 * - Active Users: Unique users who created companies
 * - Azure Cost: Placeholder for Phase 2 Azure integration
 *
 * Uses the existing StatCard component for consistent styling.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import StatCard from '@/components/dashboard/StatCard.vue'
import type { UsageStats } from '@/types/usage'

const { t } = useI18n()

interface Props {
  /** Usage statistics data from the API */
  data: UsageStats | undefined
  /** Whether data is currently loading */
  loading: boolean
}

const props = defineProps<Props>()

/**
 * Format companies count with locale-aware number formatting.
 * Returns 0 when data is undefined.
 */
const formattedCompaniesCount = computed((): number | string => {
  if (!props.data) return 0
  return props.data.companies_count
})

/**
 * Format task success rate as percentage with one decimal.
 * Handles null/zero cases by displaying "N/A" or "0%".
 */
const formattedSuccessRate = computed((): string => {
  if (!props.data) return '0%'
  if (props.data.task_success_rate === null) return 'N/A'
  if (props.data.task_success_rate === 0) return '0%'
  return `${props.data.task_success_rate.toFixed(1)}%`
})

/**
 * Format active users count with locale-aware number formatting.
 * Returns 0 when data is undefined.
 */
const formattedActiveUsers = computed((): number | string => {
  if (!props.data) return 0
  return props.data.active_users_count
})
</script>
