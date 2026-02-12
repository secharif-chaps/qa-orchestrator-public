<template>
  <Card>
    <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">
      {{ $t('home.recentActivities.title', 'Recent Activities') }}
    </h3>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-8">
      <i class="fa fa-spinner fa-spin text-sage-500 text-2xl"></i>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="py-6">
      <Alert
        variant="danger"
        :title="$t('home.recentActivities.error.title', 'Error')"
        :description="$t('home.recentActivities.error.description', 'Failed to load activities')"
        icon="fa-exclamation-triangle"
      />
    </div>

    <!-- Empty State -->
    <div v-else-if="activities.length === 0" class="flex flex-col items-center gap-4 py-8">
      <Badge variant="secondary" icon="fa fa-clock-rotate-left" size="lg" />
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ $t('home.recentActivities.noRecentActivities', 'No recent activities') }}
      </p>
    </div>

    <!-- Recent Activities -->
    <div v-else class="space-y-3">
      <RecentActivityItem v-for="activity in activities" :key="activity.id" :activity="activity" />
    </div>
  </Card>
</template>

<script setup lang="ts">
import { Alert, Badge } from '@owlint/feathers-vue'
import Card from '@/components/ui/Card.vue'
import RecentActivityItem from './RecentActivityItem.vue'
import type { Activity } from '@/types/organization'

interface Props {
  activities: Activity[]
  isLoading?: boolean
  error?: Error | null
}

defineProps<Props>()
</script>
