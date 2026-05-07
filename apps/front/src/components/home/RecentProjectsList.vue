<template>
  <div
    class="border-neutral bg-neutral-white shadow-shadow-2 flex flex-col gap-4 rounded-xl border p-6"
  >
    <!-- Header -->
    <div class="flex items-center justify-between">
      <p class="leading-lg text-neutral-black-font text-lg font-bold dark:text-white">
        {{ $t('dashboard.home.recentProjects.title') }}
      </p>
      <Button
        variant="tertiary"
        size="sm"
        :label="$t('dashboard.home.recentProjects.viewAll')"
        icon-right="fa-arrow-right"
        lib-right="far"
        @click="router.push('/folders')"
      />
    </div>

    <!-- Loading State -->
    <RecentProjectsListSkeleton v-if="isLoading" />

    <!-- Error State -->
    <div v-else-if="error" class="py-6">
      <Alert
        variant="danger"
        :title="$t('dashboard.home.recentProjects.error.title')"
        :description="$t('dashboard.home.recentProjects.error.description')"
        icon="fa-exclamation-triangle"
      />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="projects.length === 0"
      class="flex flex-col items-center gap-4 py-8 text-center"
    >
      <div
        class="bg-base-200 dark:bg-base-300 flex h-16 w-16 items-center justify-center rounded-full"
      >
        <Icon icon="fa-folder-open" class="text-primary-font text-2xl" />
      </div>
      <p class="text-primary-font text-sm">
        {{ $t('dashboard.home.recentProjects.noRecentProjects') }}
      </p>
    </div>

    <!-- Projects List -->
    <div v-else class="flex flex-col gap-2">
      <RecentProjectItem
        v-for="project in projects"
        :key="project.id"
        :id="project.id"
        :name="project.name"
        :folder-name="project.folderName"
        :folder-id="project.folderId"
        :time-ago="project.timeAgo"
        :is-shared="project.isShared"
        :type="project.type"
        @click="handleProjectClick"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { Alert, Button, Icon } from '@owlint/feathers-vue'
import RecentProjectItem from './RecentProjectItem.vue'
import RecentProjectsListSkeleton from './RecentProjectsListSkeleton.vue'
import type { ProjectType } from '@/types/module'

interface Project {
  id: number
  name: string
  folderName: string
  folderId?: string | null
  timeAgo: string
  isShared?: boolean
  type?: ProjectType
}

interface Props {
  projects: Project[]
  isLoading?: boolean
  error?: Error | null
}

defineProps<Props>()

const router = useRouter()

const handleProjectClick = ({
  id,
  folderId,
}: {
  id: number
  folderId: string | null | undefined
}) => {
  if (folderId) {
    router.push(`/folders/${folderId}/companies/${id}`)
  }
}
</script>
