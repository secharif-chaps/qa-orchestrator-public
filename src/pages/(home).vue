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

      <div class="bg-sage-100 p-6 rounded-card border-2 border-sage-200 flex items-center gap-8">
        <img :src="head" class="size-20" />
        <div class="flex flex-col gap-2">
          <h2 class="text-xl font-semibold">Est-ce que je peux vous aider ?</h2>
          <div class="flex gap-2">
            <Button variant="secondary" size="sm" icon="fa fa-file-pdf">Génère moi un PDF</Button>
            <Button variant="secondary" size="sm" icon="fa fa-search">Je souhaite faire une nouvelle recherche</Button>
            <button class="text-sm text-sage-600 hover:text-sage-800 flex items-center gap-1">
              Voir plus de Chaps-e
              <i class="fa fa-chevron-right text-xs"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column -->
        <div class="space-y-6">
          <!-- Recent Projects -->
          <div class="bg-white dark:bg-gray-800 rounded-card p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-semibold text-gray-900 dark:text-white">Projets récents</h3>
              <button class="text-xs text-sage-600 hover:text-sage-800">Voir tout</button>
            </div>
            <div class="space-y-3">
              <div
                v-for="project in mockProjects"
                :key="project.id"
                class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer"
                @click="$router.push(`/companies/${project.id}`)"
              >
                <div
                  class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                  :class="project.icon.bg"
                >
                  <i :class="[project.icon.icon, project.icon.color, 'text-sm']"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ project.name }}</h4>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ project.folder }} • il y a {{ project.time }}</p>
                </div>
                <Badge
                  v-if="project.badge"
                  :variant="project.badge.variant"
                  :label="project.badge.label"
                  size="xs"
                />
              </div>
            </div>
          </div>

          <!-- Collaborative Activity -->
          <div class="bg-white dark:bg-gray-800 rounded-card p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Activité collaborative</h3>

            <!-- Team Online -->
            <div class="mb-4">
              <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Équipe en ligne</h4>
              <div class="flex items-center gap-2">
                <div
                  v-for="member in mockOnlineTeam"
                  :key="member.id"
                  class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white"
                  :class="member.color"
                  :title="member.name"
                >
                  {{ member.initials }}
                </div>
              </div>
            </div>

            <!-- Recent Activities -->
            <div>
              <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Activités récentes</h4>
              <div class="space-y-3">
                <div
                  v-for="activity in mockActivities"
                  :key="activity.id"
                  class="flex items-start gap-3"
                >
                  <div
                    class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white flex-shrink-0"
                    :class="activity.user.color"
                  >
                    {{ activity.user.initials }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-900 dark:text-white">
                      <span class="font-medium">{{ activity.user.name }}</span>
                      {{ activity.action }}
                      <i :class="activity.icon" class="text-xs"></i>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ activity.target }}</p>
                    <div class="flex items-center gap-1 text-xs text-gray-400 mt-1">
                      <i class="fa fa-clock"></i>
                      <span>il y a {{ activity.time }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Sources & Relevance -->
        <div class="bg-white dark:bg-gray-800 rounded-card p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-semibold text-gray-900 dark:text-white">Sources et pertinence</h3>
          </div>

          <!-- Global Relevance -->
          <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 mb-6 border border-green-200 dark:border-green-800">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <i class="fa fa-shield-check text-green-600 dark:text-green-400"></i>
                <h4 class="font-semibold text-gray-900 dark:text-white">Pertinence globale</h4>
              </div>
              <div class="text-right">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">93%</div>
                <div class="text-xs text-green-700 dark:text-green-500">+2% cette semaine</div>
              </div>
            </div>
            <p class="text-xs text-gray-600 dark:text-gray-400">Toutes sources confondues</p>
          </div>

          <!-- Favorite Strategic Sources -->
          <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Sources stratégiques favorites (4)</h4>
            </div>
            <div class="space-y-2">
              <div
                v-for="source in mockSources.strategic"
                :key="source.id"
                class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
              >
                <div class="flex-1 min-w-0">
                  <h5 class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ source.name }}</h5>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ source.description }}</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                  <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ source.score }}%</span>
                  <i class="fa fa-check-circle text-green-500 text-sm"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Most Used Sources -->
          <div>
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Sources les plus utilisées</h4>
              <button class="text-xs text-sage-600 hover:text-sage-800">Voir tout le classement</button>
            </div>
            <div class="space-y-2">
              <div
                v-for="(source, index) in mockSources.mostUsed"
                :key="source.id"
                class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
              >
                <div class="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-semibold text-gray-600 dark:text-gray-300 flex-shrink-0">
                  {{ index + 1 }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-gray-900 dark:text-white truncate">{{ source.name }}</p>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">{{ source.count }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Modules Showcase -->
      <div class="">
        <ModulesShowcase />
      </div>

      <!-- Quick Actions -->
      <!-- <div class="mb-8">
        <QuickActions />
      </div> -->

      <!-- Statistics Overview -->
      <!-- <div class="mb-8">
        <StatisticsOverview :stats="companiesStats" :loading="status === 'pending'" />
      </div> -->

      <!-- Favorite Folders Section -->

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
import { favoriteFoldersQuery } from '@/queries/folders'
import { companiesQuery } from '@/queries/companies'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import type { Folder } from '@/types/folder'
import ModulesShowcase from '@/components/home/ModulesShowcase.vue'
import { useQuery } from '@pinia/colada'
import { useRouter } from 'vue-router'

import head from '@/assets/chapse/head.svg'

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

// Fetch recent companies (3 most recent)
const { data: recentCompaniesData } = useQuery(companiesQuery, () => ({
  filters: {
    page: 1,
    size: 3,
    name: '',
  },
}))

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
  if (!recentCompaniesData.value?.data) return []

  return recentCompaniesData.value.data.map((company) => {
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
      folder: 'Entreprises', // Could be enhanced to show actual folder if available
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

const mockActivities = ref([
  {
    id: 1,
    user: { name: 'Sarah Martina', initials: 'ST', color: 'bg-blue-500' },
    action: 'commenté',
    icon: 'fa fa-comment',
    target: 'Analyse Concurrentielle Q4',
    time: '15 min',
  },
  {
    id: 2,
    user: { name: 'Thomas Dubois', initials: 'TD', color: 'bg-orange-500' },
    action: 'modifié',
    icon: 'fa fa-edit',
    target: 'Veille Technologique IA',
    time: '1h',
  },
  {
    id: 3,
    user: { name: 'Marie Chena', initials: 'MC', color: 'bg-pink-500' },
    action: 'créé',
    icon: 'fa fa-plus',
    target: 'Nouveau Watchlist Blockchain',
    time: '2h',
  },
  {
    id: 4,
    user: { name: 'Sarah Martina', initials: 'ST', color: 'bg-blue-500' },
    action: 'commenté',
    icon: 'fa fa-comment',
    target: 'Analyse Concurrentielle Q4',
    time: '2h',
  },
])

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
