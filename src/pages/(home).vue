<template>
  <div class="py-8 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold">Welcome back, {{ userDisplayName }}! 👋</h1>
            <p class="text-secondary mt-2">{{ greetingMessage }}</p>
          </div>
          <div class="text-right text-sm text-secondary">
            <p class="font-medium">{{ currentDate }}</p>
            <p>{{ currentTime }}</p>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="mb-8">
        <QuickActions />
      </div>

      <!-- Statistics Overview -->
      <!-- <div class="mb-8">
        <StatisticsOverview :stats="companiesStats" :loading="status === 'pending'" />
      </div> -->

      <!-- Recent Companies Section -->
      <div class="bg-bg1 border border-border-2 rounded-lg mb-8">
        <div class="px-6 py-4 border-b border-border-2">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold">Recent Companies</h2>
            <RouterLink
              to="/companies"
              class="text-primary hover:text-primary/80 text-sm font-medium flex items-center transition-colors"
            >
              View all
              <i class="fas fa-arrow-right ml-1"></i>
            </RouterLink>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="status === 'pending'" class="px-6 py-8">
          <div class="flex justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
          </div>
        </div>

        <!-- Error State -->
        <div v-else-if="status === 'error'" class="px-6 py-8">
          <div class="text-center">
            <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-2"></i>
            <p class="text-secondary">Unable to load recent companies</p>
            <button
              @click="refreshCompanies()"
              class="mt-2 text-primary hover:text-primary/80 text-sm font-medium transition-colors"
            >
              Try again
            </button>
          </div>
        </div>

        <!-- Companies Grid -->
        <div v-else-if="recentCompanies && recentCompanies.length > 0" class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div
              v-for="company in recentCompanies"
              :key="company.id"
              class="border border-border-2 rounded-lg p-4 ring-offset-2 ring-offset-bg2 hover:ring-4 hover:ring-primary/70 transition-all cursor-pointer group"
              @click="$router.push(`/companies/${company.id}`)"
            >
              <div class="flex items-start justify-between mb-2">
                <h3 class="font-medium truncate group-hover:text-primary transition-colors">
                  {{ company.name }}
                </h3>
                <span class="text-xs text-secondary ml-2 flex-shrink-0">
                  {{ formatRelativeTime(company.created_at) }}
                </span>
              </div>
              <p class="text-sm text-secondary truncate mb-2">{{ company.website }}</p>
              <div class="flex items-center justify-between">
                <div class="flex items-center text-xs text-secondary">
                  <i class="fas fa-tasks mr-1"></i>
                  {{ company.tasks?.length || 0 }} tasks
                </div>
                <div class="flex items-center">
                  <span
                    class="w-2 h-2 rounded-full mr-1"
                    :class="getCompanyStatusColor(company)"
                  ></span>
                  <span class="text-xs text-secondary">{{ getCompanyStatus(company) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="px-6 py-8 text-center">
          <i class="fas fa-building text-secondary text-3xl mb-4"></i>
          <h3 class="text-lg font-medium mb-2">No companies yet</h3>
          <p class="text-secondary mb-4">Start by adding your first company to the database</p>
          <OButton type="primary" class="mx-auto" @click="$router.push('/search')">
            <i class="fas fa-plus"></i>
            Add Company
          </OButton>
        </div>
      </div>

      <!-- Quick Tips -->
      <!-- <div
        class="bg-gradient-to-r from-primary to-purple-600 dark:from-primary dark:to-almond-200 dark:text-sage-900 rounded-lg shadow-md border border-slate-200 dark:border-none p-6 text-white"
      >
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <i class="fas fa-lightbulb text-2xl text-white/80"></i>
          </div>
          <div class="ml-4">
            <h3 class="text-lg font-medium text-white dark:text-bg2">Pro Tip</h3>
            <p class="text-white/90 dark:text-bg2 mt-1">
              Use the search feature to quickly find and analyze companies. You can also view
              detailed profiles and track company activities through the task system.
            </p>
          </div>
        </div>
      </div> -->
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { companiesQuery } from '@/queries/companies'
import { OButton } from '@owlint/feathers-vue'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import type { Company } from '@/types/company'
import QuickActions from '@/components/dashboard/QuickActions.vue'
import StatisticsOverview from '@/components/dashboard/StatisticsOverview.vue'
import { useQuery } from '@pinia/colada'

// Only access auth on client side
const { user } = useAuth()

// Reactive data
const currentTime = ref('')
const currentDate = ref('')

// Computed properties
const userDisplayName = computed(() => {
  if (!user) return 'User'
  return user.profile.given_name || user.profile.preferred_username || user.profile.name || 'User'
})

const greetingMessage = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning! Ready to harvest some mint!'
  if (hour < 17) return "Good afternoon! Let's harvest some mint!"
  return "Good evening! Let's harvest some mint!"
})

// Cached pagination data using useAsyncData
const {
  data,
  status,
  refresh: refreshCompanies,
} = useQuery(companiesQuery, () => ({
  filters: {
    page: 1,
    size: 10,
    name: '',
  },
}))

const recentCompanies = computed(() => data.value?.data)
const companiesMeta = computed(() => data.value?.meta)

const companiesStats = computed(() => {
  // Use actual total count , not just recent companies length
  const total = companiesMeta.value?.total || 'N/A'

  // Calculate active tasks from recent companies (this is an approximation for display)
  const activeTasks = 0

  // Calculate recent updates from recent companies (this is an approximation)
  const recentUpdates = 0

  return { total, activeTasks, recentUpdates }
})

const formatRelativeTime = (dateString: string) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)

  if (diffInSeconds < 60) return 'Just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  return `${Math.floor(diffInSeconds / 86400)}d ago`
}

const getCompanyStatus = (company: Company) => {
  if (!company.tasks || company.tasks.length === 0) return 'New'

  const hasRunningTasks = company.tasks.some(
    (task) => task.status === 'running' || task.status === 'pending',
  )
  if (hasRunningTasks) return 'Processing'

  const hasFailedTasks = company.tasks.some((task) => task.status === 'error')
  if (hasFailedTasks) return 'Issues'

  return 'Complete'
}

const getCompanyStatusColor = (company: Company) => {
  const status = getCompanyStatus(company)
  switch (status) {
    case 'New':
      return 'bg-gray-400'
    case 'Processing':
      return 'bg-yellow-400'
    case 'Issues':
      return 'bg-red-400'
    case 'Complete':
      return 'bg-green-400'
    default:
      return 'bg-gray-400'
  }
}

const updateTime = () => {
  const now = new Date()
  currentTime.value = now.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  })
  currentDate.value = now.toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

const timeInterval = ref<any>(null)
// Lifecycle
onMounted(() => {
  updateTime()

  // Update time every minute
  timeInterval.value = setInterval(updateTime, 60000)
})

onUnmounted(() => {
  clearInterval(timeInterval.value)
})
</script>
