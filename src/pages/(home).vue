<template>
  <div>
    <div class="flex flex-col gap-6">
      <div class="">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold">
              {{ $t('home.welcome.title', { name: userDisplayName }) }} 👋
            </h1>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Projects -->
        <RecentProjectsList
          :projects="recentProjects"
          :is-loading="isRecentCompaniesLoading"
          :error="recentCompaniesError"
        />

        <!-- Recent Activities -->
        <RecentActivitiesList
          :activities="recentActivities"
          :is-loading="isActivitiesLoading"
          :error="activitiesError"
        />
      </div>

      <!-- Modules Showcase -->
      <div class="">
        <ModulesShowcase />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { recentCompaniesQuery } from '@/queries/companies'
import { organizationActivitiesQuery, currentOrganizationQuery } from '@/queries/organization'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import ModulesShowcase from '@/components/home/ModulesShowcase.vue'
import RecentProjectsList from '@/components/home/RecentProjectsList.vue'
import RecentActivitiesList from '@/components/home/RecentActivitiesList.vue'
import { useQuery } from '@pinia/colada'
import { formatRelativeTime } from '@/utils/time'
import { useI18n } from 'vue-i18n'

// Only access auth on client side
const { user } = useAuth()
const { t } = useI18n()

// Reactive data
const currentTime = ref('')
const currentDate = ref('')

// Computed properties
const userDisplayName = computed(() => {
  if (!user) return 'User'
  return user.profile.given_name || user.profile.preferred_username || user.profile.name || 'User'
})

// Fetch recent companies (5 most recent with folder info)
const {
  data: recentCompaniesData,
  isLoading: isRecentCompaniesLoading,
  error: recentCompaniesError,
} = useQuery(recentCompaniesQuery, () => ({
  limit: 5,
}))

// Fetch organization activities (no organization ID needed - from JWT)
const {
  data: organizationActivitiesData,
  isLoading: isActivitiesLoading,
  error: activitiesError,
} = useQuery(organizationActivitiesQuery, () => ({}))

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

// Transform recent companies data for display
const recentProjects = computed(() => {
  if (!recentCompaniesData.value) return []

  return recentCompaniesData.value.map((company) => {
    // Calculate time ago
    const createdDate = new Date(company.created_at)
    const now = new Date()
    const diffMs = now.getTime() - createdDate.getTime()
    const diffMinutes = Math.floor(diffMs / (1000 * 60))
    const diffHours = Math.floor(diffMinutes / 60)
    const diffDays = Math.floor(diffHours / 24)

    let timeAgo = ''
    if (diffDays > 0) {
      timeAgo = t('common.time.daysAgo', { count: diffDays })
    } else if (diffHours > 0) {
      timeAgo = t('common.time.hoursAgo', { count: diffHours })
    } else if (diffMinutes > 0) {
      timeAgo = t('common.time.minutesAgo', { count: diffMinutes })
    } else {
      timeAgo = t('common.time.justNow')
    }

    return {
      id: company.id,
      name: company.name,
      folderName: company.folder_name || t('home.recentProjects.noFolder'),
      folderId: company.folder_id,
      timeAgo,
      badge: { intent: 'info' as const, label: t('home.recentProjects.badge.collaborative') },
    }
  })
})

// Transform organization activities for display
const recentActivities = computed(() => {
  if (!organizationActivitiesData.value) return []

  // Defensive check - ensure it's an array
  const activities = Array.isArray(organizationActivitiesData.value)
    ? organizationActivitiesData.value.slice(0, 7)
    : []

  if (activities.length === 0) return []

  return activities.map((activity, index) => {
    const username = activity.owner || 'Unknown'

    return {
      id: index, // No ID in new API, use index
      icon: activity.type === 'company' ? 'fa fa-building' : 'fa fa-folder',
      target: activity.name,
      username,
      time: formatRelativeTime(activity.created_at),
    }
  })
})
</script>
