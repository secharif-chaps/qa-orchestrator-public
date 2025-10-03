<template>
  <div class="min-h-screen">
    <div class="flex flex-col gap-6">
      <div class="">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold">Welcome back, {{ userDisplayName }}! 👋</h1>
          </div>
        </div>
      </div>

      <div class="bg-bg3 p-6 rounded-card border-2 border-border-2 flex items-center gap-8">
        <img
          src="@/assets/chapse/head.svg"
          alt="Chapse head character"
          class="h-20 w-auto object-contain"
          loading="lazy"
          style="image-rendering: -webkit-optimize-contrast; image-rendering: smooth"
        />
        <div class="flex flex-col gap-2">
          <h2 class="text-xl font-semibold">Est-ce que je peux vous aider ?</h2>
          <div class="flex gap-2">
            <Button variant="secondary" size="sm" icon="fa fa-file-pdf">Génère moi un PDF</Button>
            <Button variant="secondary" size="sm" icon="fa fa-search"
              >Je souhaite faire une nouvelle recherche</Button
            >
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column -->

        <!-- Recent Projects -->
        <Card>
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-900 dark:text-white">Projets récents</h3>
            <button class="text-xs text-sage-600 hover:text-sage-800">Voir tout</button>
          </div>
          <div class="space-y-3">
            <div
              v-for="project in mockProjects"
              :key="project.id"
              class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer"
              @click="project.folderId && $router.push(`folders/${project.folderId}/companies/${project.id}`)"
            >
              <div
                class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                :class="project.icon.bg"
              >
                <i :class="[project.icon.icon, project.icon.color, 'text-sm']"></i>
              </div>
              <div class="flex-1 min-w-0">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">
                  {{ project.name }}
                </h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ project.folder }} • il y a {{ project.time }}
                </p>
              </div>
              <Badge
                v-if="project.badge"
                :variant="project.badge.variant"
                :label="project.badge.label"
                size="xs"
              />
            </div>
          </div>
        </Card>

        <Card>
          <h3 class="font-semibold text-gray-900 dark:text-white">Activité récentes</h3>
          <!-- Recent Activities -->
          <div class="space-y-3 max-h-[400px] overflow-y-auto  border border-border-2 rounded-card p-4">
            <div
              v-for="activity in mockActivities"
              :key="activity.id"
              class="flex items-start gap-3"
            >
              <!-- Icon with badge -->
              <div class="relative flex-shrink-0">
                <div class="w-10 h-10 rounded-full bg-sage-100 dark:bg-sage-900/30 flex items-center justify-center">
                  <i :class="activity.icon" class="text-sage-600 dark:text-sage-400 text-base"></i>
                </div>
                <div class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-sage-500 flex items-center justify-center">
                  <i class="fa fa-plus text-white text-xs"></i>
                </div>
              </div>

              <div class="flex-1 min-w-0">
                <!-- Company/Folder Name -->
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                  {{ activity.target }}
                </p>
                <!-- Meta info: user and timestamp -->
                <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  
                  <span class="flex items-center gap-1">
                    <i class="fa fa-clock"></i>
                    <span>{{ activity.time }}</span>
                  </span>
                  <span>par @{{ activity.user.name }}</span>
                </div>
              </div>
            </div>
          </div>
        </Card>
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
import { favoriteFoldersQuery } from '@/queries/folders'
import { recentCompaniesQuery } from '@/queries/companies'
import { workspaceActivitiesQuery, currentWorkspaceQuery } from '@/queries/workspace'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import type { Folder } from '@/types/folder'
import ModulesShowcase from '@/components/home/ModulesShowcase.vue'
import { useQuery } from '@pinia/colada'
import { useRouter } from 'vue-router'
import { formatRelativeTime } from '@/utils/time'


import Card from '@/components/ui/Card.vue'

// Only access auth on client side
const { user } = useAuth()
const $router = useRouter()

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

// Cached favorite folders data
const {
  data: favoriteFolders,
  status,
  refresh: refreshFavorites,
} = useQuery(favoriteFoldersQuery, () => ({}))

// Fetch current workspace to get workspace ID
const { data: currentWorkspace } = useQuery(currentWorkspaceQuery, () => ({}))

// Fetch recent companies (5 most recent with folder info)
const { data: recentCompaniesData } = useQuery(recentCompaniesQuery, () => ({
  limit: 5,
}))

// Fetch workspace activities (only if workspace ID is available)
const { data: workspaceActivitiesData, isLoading: isActivitiesLoading } = useQuery(
  workspaceActivitiesQuery,
  () => ({ workspaceId: currentWorkspace.value?.id ?? 0 }),
  {
    enabled: () => !!currentWorkspace.value?.id,
  }
)

// Navigate to folder
const viewFolder = (folderId: string) => {
  $router.push(`/folders/${folderId}`)
}

// Handle favorite toggle
const handleFavoriteToggled = async (folder: Folder) => {
  // Refresh the favorites list since a folder was removed from favorites
  if (!folder.is_favorite) {
    await refreshFavorites()
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

// Transform recent companies data for display
const mockProjects = computed(() => {
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
      timeAgo = `${diffDays} jour${diffDays > 1 ? 's' : ''}`
    } else if (diffHours > 0) {
      timeAgo = `${diffHours}h`
    } else {
      timeAgo = 'quelques minutes'
    }

    return {
      id: company.id,
      name: company.name,
      folder: company.folder_name || 'Sans dossier',
      folderId: company.folder_id,
      time: timeAgo,
      icon: { icon: 'fa fa-building', color: 'text-purple-400', bg: 'bg-purple-500/20' },
      badge: { variant: 'info', label: 'Collaboratif' },
    }
  })
})

const mockOnlineTeam = ref([
  { id: 1, name: 'Sarah Martina', initials: 'ST', color: 'bg-blue-500' },
  { id: 2, name: 'Emma Young', initials: 'EY', color: 'bg-purple-500' },
  { id: 3, name: 'Vincent Noir', initials: 'VN', color: 'bg-green-500' },
])

// Transform workspace activities for display
const recentActivities = computed(() => {
  if (!workspaceActivitiesData.value) return []

  return workspaceActivitiesData.value.map((activity, index) => {
    // Generate user initials from username
    const username = activity.owner_username
    const initials = username
      .split('_')
      .map(part => part[0])
      .join('')
      .toUpperCase()
      .substring(0, 2)

    // Assign color based on hash of username for consistency
    const colors = ['bg-blue-500', 'bg-purple-500', 'bg-green-500', 'bg-orange-500', 'bg-pink-500', 'bg-indigo-500']
    const colorIndex = username.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0) % colors.length

    // Format action based on activity type
    const action = activity.type === 'company' ? 'created a new Company Card about' : 'created the Folder'

    return {
      id: index + 1,
      user: {
        name: activity.owner_username,
        initials,
        color: colors[colorIndex]
      },
      action,
      icon: activity.type === 'company' ? 'fa fa-building' : 'fa fa-folder',
      target: activity.name,
      time: formatRelativeTime(activity.created_at),
      activityType: activity.type,
      activityId: activity.id
    }
  })
})

// Fallback to empty array when loading
const mockActivities = computed(() => {
  return isActivitiesLoading.value ? [] : recentActivities.value
})

const mockSources = ref({
  strategic: [
    { id: 1, name: 'Nom source', description: 'Description de la source', score: 95 },
    { id: 2, name: 'Nom source', description: 'Description de la source', score: 95 },
    { id: 3, name: 'Nom source', description: 'Description de la source', score: 95 },
    { id: 4, name: 'Nom source', description: 'Description de la source', score: 95 },
  ],
  mostUsed: [
    { id: 1, name: 'Nom de la source', count: 12456 },
    { id: 2, name: 'Nom de la source', count: 12365 },
    { id: 3, name: 'Nom de la source', count: 5785 },
    { id: 4, name: 'Nom de la source', count: 3214 },
    { id: 5, name: 'Nom de la source', count: 3198 },
  ],
})
</script>
