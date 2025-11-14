<template>
  <div class="h-[calc(100vh-140px)] flex flex-col">
    <!-- Header with Total Credits -->
    <div class="flex items-center justify-between border-b-2 shadow border-sage-800 px-4 py-2">
      <h2 class="text-headline-2xl">{{ $t('sidebar.tokens.title', 'Credits') }}</h2>
      <Tag
        variant="success"
        :label="`${totalTokens} ${$t('sidebar.tokens.credits', 'credits')}`"
        icon="fa fa-coins"
        rounded
        size="md"
      />
    </div>

    <!-- Token History List -->
    <div class="flex-1 overflow-y-auto px-4 py-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex items-center justify-center py-8">
        <i class="fa fa-spinner fa-spin text-sage-400"></i>
      </div>

      <!-- Token History -->
      <div v-else class="space-y-6">
        <!-- Today Section -->
        <div v-if="groupedHistory.today.length > 0">
          <h3 class="text-xs font-semibold text-sage-400 uppercase tracking-wider mb-3">
            {{ $t('sidebar.tokens.today', "Aujourd'hui") }}
          </h3>
          <div class="space-y-2">
            <div
              v-for="company in groupedHistory.today"
              :key="company.id"
              class="bg-sage-800/50 rounded-lg p-3 hover:bg-sage-800 transition-colors"
            >
              <div class="flex items-start gap-3">
                <!-- Icon -->
                <div
                  class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 bg-purple-500/20"
                >
                  <i class="fa fa-building text-purple-400 text-sm"></i>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-2">
                    <h4 class="text-sm font-medium text-white truncate">{{ company.name }}</h4>
                    <span
                      class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0 bg-red-500/20 text-red-300"
                    >
                      -1
                    </span>
                  </div>
                  <p class="text-xs text-sage-400 mt-1">
                    {{ $t('sidebar.tokens.createdBy', 'Fiche créée par') }} {{ company.owner_username }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Yesterday Section -->
        <div v-if="groupedHistory.yesterday.length > 0">
          <h3 class="text-xs font-semibold text-sage-400 uppercase tracking-wider mb-3">
            {{ $t('sidebar.tokens.yesterday', 'Hier') }}
          </h3>
          <div class="space-y-2">
            <div
              v-for="company in groupedHistory.yesterday"
              :key="company.id"
              class="bg-sage-800/50 rounded-lg p-3 hover:bg-sage-800 transition-colors"
            >
              <div class="flex items-start gap-3">
                <!-- Icon -->
                <div
                  class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 bg-purple-500/20"
                >
                  <i class="fa fa-building text-purple-400 text-sm"></i>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-2">
                    <h4 class="text-sm font-medium text-white truncate">{{ company.name }}</h4>
                    <span
                      class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0 bg-red-500/20 text-red-300"
                    >
                      -1
                    </span>
                  </div>
                  <p class="text-xs text-sage-400 mt-1">
                    {{ $t('sidebar.tokens.createdBy', 'Fiche créée par') }} {{ company.owner_username }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Other Date Sections -->
        <div v-for="(companies, dateKey) in groupedHistory.dates" :key="dateKey">
          <h3 class="text-xs font-semibold text-sage-400 uppercase tracking-wider mb-3">
            {{ dateKey }}
          </h3>
          <div class="space-y-2">
            <div
              v-for="company in companies"
              :key="company.id"
              class="bg-sage-800/50 rounded-lg p-3 hover:bg-sage-800 transition-colors"
            >
              <div class="flex items-start gap-3">
                <!-- Icon -->
                <div
                  class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 bg-purple-500/20"
                >
                  <i class="fa fa-building text-purple-400 text-sm"></i>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-2">
                    <h4 class="text-sm font-medium text-white truncate">{{ company.name }}</h4>
                    <span
                      class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0 bg-red-500/20 text-red-300"
                    >
                      -1
                    </span>
                  </div>
                  <p class="text-xs text-sage-400 mt-1">
                    {{ $t('sidebar.tokens.createdBy', 'Fiche créée par') }} {{ company.owner_username }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- See All History Link (hidden when on history page) -->
        <div v-if="!isOnHistoryPage" class="pt-2">
          <button
            class="text-sm text-sage-300 hover:text-white transition-colors flex items-center gap-2"
            @click="$router.push('/tokens/history')"
          >
            {{ $t('sidebar.tokens.viewHistory', 'View all history') }}
            <i class="fa fa-arrow-right text-xs"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Contact Support Alert -->
    <div class="px-4 pb-4 dark">
      <Alert
        variant="info"
        :title="$t('sidebar.tokens.needMore', 'Need more Credits?')"
        :message="
          $t(
            'sidebar.tokens.contactSupport',
            'Contact ChapsVision support to request additional credits',
          )
        "
        icon="fa fa-envelope"
      >
        <template #actions>
          <Button
            variant="tertiary"
            size="sm"
            :label="$t('sidebar.tokens.contactButton', 'Contact Support')"
            icon="fa fa-envelope"
            @click="handleContact"
          />
        </template>
      </Alert>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@pinia/colada'
import { useRoute } from 'vue-router'
import { organizationModulesQuery } from '@/queries/tokens'
import { currentOrganizationQuery } from '@/queries/organization'
import { recentCompaniesQuery } from '@/queries/companies'
import { useAuthStore } from '@/stores/auth'
import Tag from '@/components/ui/Tag.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import type { Company } from '@/types/company'

const authStore = useAuthStore()
const route = useRoute()

// Check if we're on the token history page
const isOnHistoryPage = computed(() => route.path === '/tokens/history')

// Fetch current organization
const { data: currentOrganization } = useQuery(currentOrganizationQuery, () => ({}))

// Fetch organization modules to get total tokens
const { data: modulesData, isLoading: isLoadingTokens } = useQuery(
  organizationModulesQuery,
  () => ({ organizationId: currentOrganization.value?.id || '' }),
  { enabled: () => !!currentOrganization.value?.id },
)

// Fetch recent companies for token history (10 most recent)
const { data: recentCompanies, isLoading: isLoadingCompanies } = useQuery(
  recentCompaniesQuery,
  () => ({ limit: 10 }),
)

// Calculate total tokens across all modules
const totalTokens = computed(() => {
  if (!modulesData.value?.modules) return 0
  return modulesData.value.modules.reduce((sum, module) => sum + module.token_count, 0)
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
const isLoading = computed(() => isLoadingTokens.value || isLoadingCompanies.value)

// Handle contact button - opens email client
const handleContact = () => {
  window.location.href = 'mailto:support.chapsmind@chapsvision.com?subject=Token Refill Request'
}
</script>
