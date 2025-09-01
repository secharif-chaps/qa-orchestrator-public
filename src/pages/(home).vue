<template>
  <div class="py-8 min-h-screen">
    <div>
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

      <!-- Modules Showcase -->
      <div class="mb-8">
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
      <div class="bg-bg1 border border-border-2 rounded-lg mb-8">
        <div class="px-6 py-4 border-b border-border-2">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold">Favorite Folders</h2>
            <RouterLink
              to="/folders"
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
            <p class="text-secondary">Unable to load favorite folders</p>
            <button
              @click="refreshFavorites()"
              class="mt-2 text-primary hover:text-primary/80 text-sm font-medium transition-colors"
            >
              Try again
            </button>
          </div>
        </div>

        <!-- Favorite Folders Grid -->
        <div v-else-if="favoriteFolders && favoriteFolders.length > 0" class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <FolderItem
              v-for="folder in favoriteFolders"
              :key="folder.id"
              :folder="folder"
              @view-folder="viewFolder"
              @favorite-toggled="handleFavoriteToggled"
            />
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="px-6 py-8 text-center">
          <i class="fas fa-star text-secondary text-3xl mb-4"></i>
          <h3 class="text-lg font-medium mb-2">No favorite folders yet</h3>
          <p class="text-secondary mb-4">
            Mark folders as favorites to see them here for quick access
          </p>
          <Button
            variant="primary"
            icon="fa fa-folder-plus"
            label="Create Folder"
            @click="$router.push('/folders/create')"
          />
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
import { favoriteFoldersQuery } from '@/queries/folders'
import Button from '@/components/ui/Button.vue'
import FolderItem from '@/components/folders/FolderItem.vue'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import type { Folder } from '@/types/folder'
import ModulesShowcase from '@/components/home/ModulesShowcase.vue'
import { useQuery } from '@pinia/colada'
import { useRouter } from 'vue-router'

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
</script>
