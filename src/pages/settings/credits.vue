<template>
  <div class="flex flex-col gap-6">
    <!-- Balance Banner -->
    <CreditBalanceBanner
      v-if="creditStats"
      :balance="creditStats.balance"
    />

    <!-- Loading state for balance -->
    <div
      v-else-if="statsLoading"
      class="bg-success-light rounded-lg p-6 border border-success-stroke animate-pulse"
    >
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-lg bg-success/20"></div>
        <div class="flex-1">
          <div class="h-4 bg-success/20 rounded w-24 mb-2"></div>
          <div class="h-8 bg-success/20 rounded w-40"></div>
        </div>
      </div>
    </div>

    <!-- Usage Donut Chart and Module Forecasts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Donut Chart -->
      <CreditUsageDonutChart
        :usage-data="creditStats?.usageByModule || []"
        :loading="statsLoading"
      />

      <!-- Module Forecasts -->
      <ModuleForecastGrid
        :forecasts="creditStats?.remainingCapacity || []"
        :loading="statsLoading"
      />
    </div>

    <!-- Top Credit Users -->
    <TopCreditUsersCard
      v-model:module="topUsersModule"
      v-model:period="topUsersPeriod"
      v-model:search="topUsersSearch"
      v-model:page="topUsersPage"
      :users="topUsersData?.items || []"
      :loading="topUsersLoading"
      :pagination-meta="topUsersPaginationMeta"
      @update-per-page="topUsersSize = $event"
    />

    <!-- Daily Usage Chart -->
    <DailyCreditUsageCard
      v-model:module="dailyUsageModule"
      v-model:period="dailyUsagePeriod"
      :daily-usage="dailyUsageData?.dailyUsage || []"
      :loading="dailyUsageLoading"
    />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - organization.manage
  requiresAuth: true
  title: 'Credits'
</route>

<script setup lang="ts">
/**
 * Credits management page for organization managers.
 *
 * Displays:
 * - Current credit balance
 * - Usage breakdown by module (donut chart)
 * - Remaining capacity forecast per module
 * - Top credit-consuming users (with filters and pagination)
 * - Daily credit usage trend (bar chart)
 */
import { ref, computed, watch } from 'vue'
import { useQuery } from '@pinia/colada'
import { creditStatsQuery, topCreditUsersQuery, dailyCreditUsageQuery } from '@/queries/credits'
import { currentOrganizationQuery } from '@/queries/organization'
import type { PaginationMeta } from '@/types/pagination'

// Components
import CreditBalanceBanner from '@/components/credits/CreditBalanceBanner.vue'
import CreditUsageDonutChart from '@/components/credits/CreditUsageDonutChart.vue'
import ModuleForecastGrid from '@/components/credits/ModuleForecastGrid.vue'
import TopCreditUsersCard from '@/components/credits/TopCreditUsersCard.vue'
import DailyCreditUsageCard from '@/components/credits/DailyCreditUsageCard.vue'

// Get current organization
const { data: organization } = useQuery(currentOrganizationQuery, () => ({}))

const organizationId = computed(() => organization.value?.id || '')

// ============================================================================
// Credit Stats Query
// ============================================================================

const { data: creditStats, isLoading: statsLoading } = useQuery(() => ({
  ...creditStatsQuery({ orgId: organizationId.value }),
  enabled: !!organizationId.value,
}))

// ============================================================================
// Top Credit Users Query
// ============================================================================

const topUsersModule = ref('all')
const topUsersPeriod = ref('30d')
const topUsersSearch = ref('')
const topUsersPage = ref(1)
const topUsersSize = ref(10)

// Reset page when filters change
watch([topUsersModule, topUsersPeriod, topUsersSearch], () => {
  topUsersPage.value = 1
})

const topUsersFilters = computed(() => ({
  module: topUsersModule.value,
  period: topUsersPeriod.value,
  search: topUsersSearch.value || undefined,
  page: topUsersPage.value,
  size: topUsersSize.value,
}))

const { data: topUsersData, isLoading: topUsersLoading } = useQuery(() => ({
  ...topCreditUsersQuery({ orgId: organizationId.value, filters: topUsersFilters.value }),
  enabled: !!organizationId.value,
}))

// Convert API pagination to UI pagination meta
const topUsersPaginationMeta = computed<PaginationMeta | null>(() => {
  if (!topUsersData.value) return null

  const total = topUsersData.value.total
  const perPage = topUsersData.value.size
  const currentPage = topUsersData.value.page
  const lastPage = Math.ceil(total / perPage) || 1

  return {
    total,
    per_page: perPage,
    current_page: currentPage,
    last_page: lastPage,
    from: (currentPage - 1) * perPage + 1,
    to: Math.min(currentPage * perPage, total),
  }
})

// ============================================================================
// Daily Credit Usage Query
// ============================================================================

const dailyUsageModule = ref('all')
const dailyUsagePeriod = ref('30d')

const dailyUsageFilters = computed(() => ({
  module: dailyUsageModule.value,
  period: dailyUsagePeriod.value,
}))

const { data: dailyUsageData, isLoading: dailyUsageLoading } = useQuery(() => ({
  ...dailyCreditUsageQuery({ orgId: organizationId.value, filters: dailyUsageFilters.value }),
  enabled: !!organizationId.value,
}))
</script>
