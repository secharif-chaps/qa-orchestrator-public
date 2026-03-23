<template>
  <div class="flex h-[calc(100vh-140px)] flex-col px-6">
    <!-- Header with Total Credits -->
    <SidebarHeader :title="$t('sidebar.tokens.title')">
      <Tag
        color="almond"
        :label="`${totalTokens} ${$t('sidebar.tokens.credits', 'credits')}`"
        icon="fa-coins"
        size="md"
      />
    </SidebarHeader>

    <!-- Token History List -->
    <div class="flex-1 overflow-y-auto px-4 py-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex items-center justify-center py-8">
        <i class="fa fa-spinner fa-spin text-sage-400"></i>
      </div>

      <!-- Token History -->
      <div v-else class="flex flex-col gap-6">
        <!-- Empty State -->
        <div v-if="hasNoHistory" class="flex flex-col items-center justify-center gap-3 py-8">
          <Tag color="sage" icon="fa-coins" />
          <div class="text-center">
            <h3 class="text-sage-900 mb-1 text-sm font-semibold dark:text-white">
              {{ $t('sidebar.tokens.noHistory', 'No usage history') }}
            </h3>
            <p class="text-sage-700 dark:text-sage-300 px-4 text-xs">
              {{
                $t(
                  'sidebar.tokens.noHistoryDesc',
                  'Token usage will appear here when you create company cards.',
                )
              }}
            </p>
          </div>
        </div>

        <!-- Today Section -->
        <div v-if="groupedHistory.today.length > 0" class="flex flex-col gap-3">
          <h3 class="text-sage-400 text-xs font-semibold tracking-wider uppercase">
            {{ $t('sidebar.tokens.today', "Aujourd'hui") }}
          </h3>
          <div class="flex flex-col gap-2">
            <TokenHistoryItem
              v-for="company in groupedHistory.today"
              :key="company.id"
              :name="company.name"
              :owner-username="company.owner_username"
            />
          </div>
        </div>

        <!-- Yesterday Section -->
        <div v-if="groupedHistory.yesterday.length > 0" class="flex flex-col gap-3">
          <h3 class="text-sage-400 text-xs font-semibold tracking-wider uppercase">
            {{ $t('sidebar.tokens.yesterday', 'Hier') }}
          </h3>
          <div class="flex flex-col gap-2">
            <TokenHistoryItem
              v-for="company in groupedHistory.yesterday"
              :key="company.id"
              :name="company.name"
              :owner-username="company.owner_username"
            />
          </div>
        </div>

        <!-- Other Date Sections -->
        <div
          v-for="(companies, dateKey) in groupedHistory.dates"
          :key="dateKey"
          class="flex flex-col gap-3"
        >
          <h3 class="text-sage-400 text-xs font-semibold tracking-wider uppercase">
            {{ dateKey }}
          </h3>
          <div class="flex flex-col gap-2">
            <TokenHistoryItem
              v-for="company in companies"
              :key="company.id"
              :name="company.name"
              :owner-username="company.owner_username"
            />
          </div>
        </div>

        <!-- See All History Link -->
        <div v-if="!isOnHistoryPage && !hasNoHistory" class="pt-2">
          <Button
            variant="tertiary"
            size="sm"
            :label="$t('sidebar.tokens.viewHistory', 'View all history')"
            icon-right="fa fa-arrow-right"
            @click="$router.push('/tokens/history')"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { recentCompaniesQuery } from '@/queries/companies'
import { currentOrganizationQuery } from '@/queries/organization'
import { organizationBalanceQuery } from '@/queries/tokens'
import type { Company } from '@/types/company'
import { Button, Tag } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import SidebarHeader from './SidebarHeader.vue'
import TokenHistoryItem from './TokenHistoryItem.vue'

const route = useRoute()

// Check if we're on the token history page
const isOnHistoryPage = computed(() => route.path === '/tokens/history')

// Fetch current organization
const { data: currentOrganization, isLoading: isLoadingOrg } = useQuery(() =>
  currentOrganizationQuery(),
)

// Fetch global token balance (new global system) using the spread pattern
const { data: balanceData, isLoading: isLoadingBalance } = useQuery({
  ...organizationBalanceQuery({ organizationId: currentOrganization.value?.id ?? '' }),
  enabled: () => !!currentOrganization.value?.id,
})

// Fetch recent companies for token history (10 most recent)
const { data: recentCompanies, isLoading: isLoadingCompanies } = useQuery(
  recentCompaniesQuery({ limit: 10 }),
)

// Get total tokens from global balance (no longer summing modules)
const totalTokens = computed(() => balanceData.value?.balance ?? 0)

// Helper function to format relative date
function formatRelativeDate(dateString: string | null | undefined) {
  if (!dateString) return 'unknown'

  const date = new Date(dateString)
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(yesterday.getDate() - 1)

  // Reset time to compare dates only
  date.setHours(0, 0, 0, 0)
  today.setHours(0, 0, 0, 0)
  yesterday.setHours(0, 0, 0, 0)

  if (date.getTime() === today.getTime()) {
    return 'today'
  } else if (date.getTime() === yesterday.getTime()) {
    return 'yesterday'
  } else {
    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })
  }
}

// Group companies by date sections
const groupedHistory = computed(() => {
  if (!recentCompanies.value) return { today: [], yesterday: [], dates: {} }

  const groups: {
    today: Company[]
    yesterday: Company[]
    dates: Record<string, Company[]>
  } = {
    today: [],
    yesterday: [],
    dates: {},
  }

  recentCompanies.value.forEach((company) => {
    const dateKey = formatRelativeDate(company.created_at)

    if (dateKey === 'today') {
      groups.today.push(company)
    } else if (dateKey === 'yesterday') {
      groups.yesterday.push(company)
    } else {
      if (!groups.dates[dateKey]) {
        groups.dates[dateKey] = []
      }
      groups.dates[dateKey].push(company)
    }
  })

  return groups
})

// Combined loading state
const isLoading = computed(
  () => isLoadingOrg.value || isLoadingBalance.value || isLoadingCompanies.value,
)

// Check if there's no history to display
const hasNoHistory = computed(() => {
  const groups = groupedHistory.value
  return (
    groups.today.length === 0 &&
    groups.yesterday.length === 0 &&
    Object.keys(groups.dates).length === 0
  )
})
</script>
