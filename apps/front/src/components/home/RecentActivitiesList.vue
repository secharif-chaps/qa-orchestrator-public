<template>
  <div
    class="shadow-shadow-2 border-contextual-edge bg-neutral-white flex flex-1 flex-col gap-4 self-stretch rounded-[20px] border p-6"
  >
    <h3 class="leading-lg text-neutral-black-font text-lg font-bold dark:text-white">
      {{ $t('dashboard.home.recentActivities.title') }}
    </h3>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-8">
      <Icon icon="fa-spinner" class="fa-spin text-primary text-2xl" />
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="py-6">
      <Alert
        variant="danger"
        :title="$t('dashboard.home.recentActivities.error.title')"
        :description="$t('dashboard.home.recentActivities.error.description')"
        icon="fa-exclamation-triangle"
      />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="activities.length === 0"
      class="flex flex-col items-center gap-4 py-8 text-center"
    >
      <div
        class="bg-base-200 dark:bg-base-300 flex h-16 w-16 items-center justify-center rounded-full"
      >
        <Icon icon="fa-clock-rotate-left" class="text-primary-font text-2xl" />
      </div>
      <p class="text-primary-font text-sm">
        {{
          hasUserProjects
            ? $t('dashboard.home.recentActivities.noTeamActivities')
            : $t('dashboard.home.recentActivities.startCreating')
        }}
      </p>
    </div>

    <!-- Recent Activities -->
    <div v-else class="flex flex-col gap-2">
      <RecentActivityItem v-for="activity in activities" :key="activity.id" :activity="activity" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Alert, Icon } from '@owlint/feathers-vue'
import RecentActivityItem from './RecentActivityItem.vue'
import type { Activity } from '@/types/organization'

interface Props {
  activities: Activity[]
  isLoading?: boolean
  error?: Error | null
  hasUserProjects?: boolean
}

const { hasUserProjects = false } = defineProps<Props>()
</script>
