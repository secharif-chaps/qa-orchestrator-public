<template>
  <Card>
    <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">
      {{ $t('dashboard.home.recentActivities.title') }}
    </h3>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-8">
      <i class="fa fa-spinner fa-spin text-sage-500 text-2xl"></i>
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
    <div v-else-if="activities.length === 0" class="py-8 text-center">
      <div
        class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"
      >
        <Icon icon="fa-clock-rotate-left" class="text-2xl text-gray-400" />
      </div>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{
          hasUserProjects
            ? $t('dashboard.home.recentActivities.noTeamActivities')
            : $t('dashboard.home.recentActivities.startCreating')
        }}
      </p>
    </div>

    <!-- Recent Activities -->
    <div v-else class="space-y-3">
      <RecentActivityItem v-for="activity in activities" :key="activity.id" :activity="activity" />
    </div>
  </Card>
</template>

<script setup lang="ts">
import { Alert, Icon } from '@owlint/feathers-vue'
import Card from '@/components/ui/Card.vue'
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
