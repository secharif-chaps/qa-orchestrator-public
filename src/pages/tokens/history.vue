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
        {{ $t('tokens.history.subtitle', 'View all token usage for your workspace') }}
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
        v-if="!companiesData?.data || companiesData.data.length === 0"
        class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center"
      >
        <div
          class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center"
        >
          <i class="fa fa-coins text-4xl text-gray-400"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
          {{ $t('tokens.history.noHistory', 'No token history yet') }}
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
          {{ $t('tokens.history.noHistoryDesc', 'Token usage will appear here') }}
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
                        {{ $t('tokens.history.createdBy', 'Created by') }} {{ company.owner_username }}
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

      <!-- Pagination -->
      <div
        v-if="companiesData && companiesData.total > 0"
        class="mt-8 flex items-center justify-between"
      >
        <p class="text-sm text-gray-600 dark:text-gray-400">
          {{ $t('tokens.history.showing', 'Showing') }}
          {{ (page - 1) * pageSize + 1 }} {{ $t('tokens.history.to', 'to') }}
          {{ Math.min(page * pageSize, companiesData.total) }}
          {{ $t('tokens.history.of', 'of') }} {{ companiesData.total }}
          {{ $t('tokens.history.entries', 'entries') }}
        </p>

        <div class="flex items-center gap-2">
          <button
            @click="page--"
            :disabled="page === 1"
            class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
          >
            {{ $t('tokens.history.previous', 'Previous') }}
          </button>

          <div class="flex items-center gap-1">
            <button
              v-for="pageNum in visiblePages"
              :key="pageNum"
              @click="page = pageNum"
              :class="[
                'w-10 h-10 rounded-lg text-sm font-medium transition-colors',
                page === pageNum
                  ? 'bg-sage-600 text-white'
                  : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300',
              ]"
            >
              {{ pageNum }}
            </button>
          </div>

          <button
            @click="page++"
            :disabled="page >= totalPages"
            class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
          >
            {{ $t('tokens.history.next', 'Next') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useQuery } from '@pinia/colada'
import { workspaceModulesQuery } from '@/queries/tokens'
import { companiesQuery } from '@/queries/companies'
import { useAuthStore } from '@/stores/auth'
import Tag from '@/components/ui/Tag.vue'
import Alert from '@/components/ui/Alert.vue'
import type { Company } from '@/types/company'

const authStore = useAuthStore()

// Pagination
const page = ref(1)
const pageSize = ref(20)

// Get workspace ID from current workspace
const workspaceId = computed(() => authStore.currentWorkspace?.id || 1)

// Fetch workspace modules to get total tokens
const { data: modulesData } = useQuery(
  workspaceModulesQuery,
  () => ({ workspaceId: workspaceId.value }),
  { enabled: () => !!workspaceId.value },
)

// Fetch companies with pagination and sorting
const {
  data: companiesData,
  isLoading,
  error,
} = useQuery(companiesQuery, () => ({
  filters: {
    page: page.value,
    size: pageSize.value,
    name: '',
    sort: 'created_at',
    order: 'desc',
  },
}))

// Calculate total tokens across all modules
const totalTokens = computed(() => {
  if (!modulesData.value?.modules) return 0
  return modulesData.value.modules.reduce((sum, module) => sum + module.token_count, 0)
})

// Calculate total pages
const totalPages = computed(() => {
  if (!companiesData.value) return 0
  return Math.ceil(companiesData.value.total / pageSize.value)
})

// Calculate visible page numbers for pagination
const visiblePages = computed(() => {
  const total = totalPages.value
  const current = page.value
  const pages: number[] = []

  if (total <= 7) {
    // Show all pages if 7 or less
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Always show first page
    pages.push(1)

    if (current > 3) {
      pages.push(-1) // Ellipsis
    }

    // Show pages around current
    const start = Math.max(2, current - 1)
    const end = Math.min(total - 1, current + 1)

    for (let i = start; i <= end; i++) {
      pages.push(i)
    }

    if (current < total - 2) {
      pages.push(-1) // Ellipsis
    }

    // Always show last page
    pages.push(total)
  }

  return pages.filter((p) => p !== -1) // Remove ellipsis placeholders for now
})

// Helper function to format relative date
const formatRelativeDate = (dateString: string) => {
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
const formatDateTime = (dateString: string) => {
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
  if (!companiesData.value?.data) return {}

  const groups: Record<string, Company[]> = {}

  companiesData.value.data.forEach((company) => {
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
