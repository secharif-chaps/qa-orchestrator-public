<template>
  <Card>
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-gray-900 dark:text-white">
        {{ $t('home.recentProjects.title', 'Recent Projects') }}
      </h3>
      <Button
        variant="tertiary"
        size="sm"
        :label="$t('home.recentProjects.viewAll', 'View All')"
        @click="router.push('/folders')"
      />
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-8">
      <i class="fa fa-spinner fa-spin text-2xl text-sage-500"></i>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="py-6">
      <Alert
        variant="danger"
        :title="$t('home.recentProjects.error.title', 'Error')"
        :description="$t('home.recentProjects.error.description', 'Failed to load projects')"
        icon="fa-exclamation-triangle"
      />
    </div>

    <!-- Empty State -->
    <div v-else-if="projects.length === 0" class="py-8 text-center">
      <div
        class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center"
      >
        <i class="fa fa-folder-open text-2xl text-gray-400"></i>
      </div>
      <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('home.recentProjects.noRecentProjects', 'No recent projects yet') }}</p>
    </div>

    <!-- Projects List -->
    <div v-else class="space-y-3">
      <RecentProjectItem
        v-for="project in projects"
        :key="project.id"
        :id="project.id"
        :name="project.name"
        :folder-name="project.folderName"
        :folder-id="project.folderId"
        :time-ago="project.timeAgo"
        :badge="project.badge"
        @click="handleProjectClick"
      />
    </div>
  </Card>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { Alert, Button } from '@owlint/feathers-vue'
import Card from '@/components/ui/Card.vue'
import RecentProjectItem from './RecentProjectItem.vue'

interface Project {
  id: number
  name: string
  folderName: string
  folderId?: string | null
  timeAgo: string
  badge?: {
    intent: 'neutral' | 'accent' | 'success' | 'warning' | 'danger' | 'info'
    label: string
  }
}

interface Props {
  projects: Project[]
  isLoading?: boolean
  error?: Error | null
}

defineProps<Props>()

const router = useRouter()

function handleProjectClick({ id, folderId }: { id: number; folderId: string | null | undefined }) {
  if (folderId) {
    router.push(`/folders/${folderId}/companies/${id}`)
  }
}
</script>
