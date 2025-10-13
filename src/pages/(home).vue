<template>
  <div class="min-h-screen">
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

      <div
        class="bg-base-300 p-6 rounded-card border-2 border-primary-stroke flex items-center gap-8"
      >
        <img
          src="@/assets/chapse/head.svg"
          alt="Chapse head character"
          class="h-20 w-auto object-contain"
          loading="lazy"
          style="image-rendering: -webkit-optimize-contrast; image-rendering: smooth"
        />
        <div class="flex flex-col gap-2">
          <h2 class="text-xl font-semibold">{{ $t('home.assistant.greeting') }}</h2>
          <div class="flex gap-2">
            <Button variant="secondary" size="sm" icon="fa fa-file-pdf">{{
              $t('home.assistant.actions.generatePdf')
            }}</Button>
            <Button variant="secondary" size="sm" icon="fa fa-search">{{
              $t('home.assistant.actions.newSearch')
            }}</Button>
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
import { workspaceActivitiesQuery, currentWorkspaceQuery } from '@/queries/workspace'
import Button from '@/components/ui/Button.vue'
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

// Fetch current workspace to get workspace ID
const { data: currentWorkspace } = useQuery(currentWorkspaceQuery, () => ({}))

// Fetch recent companies (5 most recent with folder info)
const {
  data: recentCompaniesData,
  isLoading: isRecentCompaniesLoading,
  error: recentCompaniesError,
} = useQuery(recentCompaniesQuery, () => ({
  limit: 5,
}))

// Fetch workspace activities (only if workspace ID is available)
const {
  data: workspaceActivitiesData,
  isLoading: isActivitiesLoading,
  error: activitiesError,
} = useQuery(
  workspaceActivitiesQuery,
  () => ({ workspaceId: currentWorkspace.value?.id ?? 0, limit: 5 }),
  {
    enabled: () => !!currentWorkspace.value?.id,
  },
)

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
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
    const diffDays = Math.floor(diffHours / 24)

    let timeAgo = ''
    if (diffDays > 0) {
      timeAgo = diffDays === 1 ? t('common.time.day') : `${diffDays} ${t('common.time.days')}`
    } else if (diffHours > 0) {
      timeAgo = t('common.time.hours', { count: diffHours })
    } else {
      timeAgo = t('common.time.fewMinutes')
    }

    return {
      id: company.id,
      name: company.name,
      folderName: company.folder_name || t('home.recentProjects.noFolder'),
      folderId: company.folder_id,
      timeAgo: t('home.recentProjects.timeAgo', { time: timeAgo }),
      badge: { variant: 'info' as const, label: t('home.recentProjects.badge.collaborative') },
    }
  })
})

// Transform workspace activities for display
const recentActivities = computed(() => {
  if (!workspaceActivitiesData.value) return []

  // Defensive check - ensure it's an array
  const activities = Array.isArray(workspaceActivitiesData.value)
    ? workspaceActivitiesData.value.slice(0, 7)
    : []

  if (activities.length === 0) return []

  return activities.map((activity, index) => {
    const username = activity.owner_username || 'Unknown'

    return {
      id: activity.id || index,
      icon: activity.type === 'company' ? 'fa fa-building' : 'fa fa-folder',
      target: activity.name,
      username,
      time: formatRelativeTime(activity.created_at),
    }
  })
})
</script>
