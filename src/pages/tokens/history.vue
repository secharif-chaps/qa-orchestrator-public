<template>
  <div class="min-h-screen bg-base-100 p-6">
    <!-- Header -->
    <div class="max-w-5xl mx-auto mb-6">
      <div class="flex items-center gap-4 mb-2">
        <h1 class="text-headline-3xl font-bold">
          {{ $t('tokens.history.title', 'Token History') }}
        </h1>
      </div>
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('tokens.history.subtitle', 'View all token usage for your organization') }}
      </p>
    </div>

    <!-- Total Credits Card -->
    <div class="max-w-5xl mx-auto mb-6">
      <div
        class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6"
      >
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase mb-1">
              {{ $t('tokens.history.totalCredits', 'Total Available Credits') }}
            </h2>
            <p class="text-3xl font-bold text-sage-600 dark:text-sage-400">
              {{ totalTokens }}
            </p>
          </div>
          <Tag
            variant="success"
            :label="$t('tokens.history.active', 'Active')"
            size="lg"
            rounded
            icon="fa fa-check-circle"
          />
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-16">
      <i class="fa fa-spinner fa-spin text-4xl text-sage-500"></i>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="max-w-5xl mx-auto">
      <Alert
        variant="error"
        title="Unable to load token history"
        message="There was a problem loading the token history. Please try again later."
        icon="fa fa-exclamation-triangle"
      />
    </div>

    <!-- Token History List -->
    <div v-else class="max-w-5xl mx-auto">
      <!-- Empty State -->
      <div
        v-if="!companiesData || companiesData.length === 0"
        class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center"
      >
        <div
          class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center"
        >
          <i class="fa fa-coins text-4xl text-gray-400 dark:text-gray-500"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
          {{ $t('tokens.history.noHistory', 'No token history yet') }}
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 max-w-md mx-auto">
          {{
            $t(
              'tokens.history.noHistoryDesc',
              'When you create company cards, your token usage history will appear here.',
            )
          }}
        </p>
      </div>

      <!-- History Cards -->
      <div v-else class="space-y-6">
        <!-- Date Groups -->
        <div v-for="(group, dateKey) in groupedHistory" :key="dateKey">
          <h3
            class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3 ml-2"
          >
            {{ dateKey }}
          </h3>

          <div class="space-y-3">
            <div
              v-for="company in group"
              :key="company.id"
              class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:shadow-shadow-2 transition-all"
            >
              <div class="flex items-start gap-4">
                <!-- Icon -->
                <div
                  class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0 bg-purple-500/10 dark:bg-purple-500/20"
                >
                  <i class="fa fa-building text-purple-600 dark:text-purple-400 text-lg"></i>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-4 mb-2">
                    <div class="flex-1 min-w-0">
                      <h4 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                        {{ company.name }}
                      </h4>
                      <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $t('tokens.history.createdBy', 'Created by') }}
                        {{ company.owner_username }}
                      </p>
                    </div>
                    <div class="flex items-center gap-3">
                      <span
                        class="text-sm font-semibold px-3 py-1 rounded-full bg-red-500/10 text-red-600 dark:bg-red-500/20 dark:text-red-400"
                      >
                        -1 {{ $t('tokens.history.token', 'token') }}
                      </span>
                    </div>
                  </div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ formatDateTime(company.created_at) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div v-if="companiesData && companiesData.length > 0" class="mt-8 text-center">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          {{ $t('tokens.history.totalEntries', { count: companiesData.length }) }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@pinia/colada'
import { organizationModulesQuery } from '@/queries/tokens'
import { currentOrganizationQuery } from '@/queries/organization'
import { recentCompaniesQuery } from '@/queries/companies'
import Tag from '@/components/ui/Tag.vue'
import Alert from '@/components/ui/Alert.vue'
import type { Company } from '@/types/company'

// Get current organization
const { data: currentOrganization } = useQuery(currentOrganizationQuery, () => ({}))

// Fetch organization modules to get total tokens
const { data: modulesData } = useQuery(
  organizationModulesQuery,
  () => ({ organizationId: currentOrganization.value!.id }),
)

// Fetch recent companies using the same query as the sidebar
// This query uses the /companies/recent endpoint which works correctly
const {
  data: companiesData,
  isLoading,
  error,
} = useQuery(recentCompaniesQuery, () => ({
  limit: 100, // Get more for the history page
}))

// Calculate total tokens across all modules
const totalTokens = computed(() => {
  if (!modulesData.value?.modules) return 0
  return modulesData.value.modules.reduce((sum, module) => sum + module.token_count, 0)
})

// Helper function to format relative date
const formatRelativeDate = (dateString: string | null | undefined) => {
  if (!dateString) {
    return 'Date inconnue'
  }

  const date = new Date(dateString)
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(yesterday.getDate() - 1)

  // Reset time to compare dates only
  date.setHours(0, 0, 0, 0)
  today.setHours(0, 0, 0, 0)
  yesterday.setHours(0, 0, 0, 0)

  if (date.getTime() === today.getTime()) {
    return "Aujourd'hui"
  } else if (date.getTime() === yesterday.getTime()) {
    return 'Hier'
  } else {
    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
  }
}

// Helper function to format full date and time
const formatDateTime = (dateString: string | null | undefined) => {
  if (!dateString) {
    return 'Date inconnue'
  }

  const date = new Date(dateString)
  return date.toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Group companies by date sections
const groupedHistory = computed(() => {
  if (!companiesData.value || companiesData.value.length === 0) return {}

  const groups: Record<string, Company[]> = {}

  companiesData.value.forEach((company) => {
    const dateKey = formatRelativeDate(company.created_at)

    if (!groups[dateKey]) {
      groups[dateKey] = []
    }
    groups[dateKey].push(company)
  })

  return groups
})
</script>

<route lang="yaml">
meta:
  requiresAuth: true
  title: 'Token History'
</route>
