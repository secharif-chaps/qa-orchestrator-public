<template>
  <Card>
    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
      {{ $t('home.recentActivities.title') }}
    </h3>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-8">
      <i class="fa fa-spinner fa-spin text-2xl text-sage-500"></i>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="py-6">
      <Alert
        variant="error"
        title="Unable to load recent activities"
        message="There was a problem loading workspace activities. Please try again later."
        icon="fa fa-exclamation-triangle"
      />
    </div>

    <!-- Empty State -->
    <div v-else-if="activities.length === 0" class="py-8 text-center">
      <div
        class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center"
      >
        <i class="fa fa-clock-rotate-left text-2xl text-gray-400"></i>
      </div>
      <p class="text-sm text-gray-500 dark:text-gray-400">No recent activities</p>
    </div>

    <!-- Recent Activities -->
    <div
      v-else
      class="space-y-3 max-h-[400px] overflow-y-auto border border-primary-stroke rounded-card p-4"
    >
      <RecentActivityItem
        v-for="activity in activities"
        :key="activity.id"
        :icon="activity.icon"
        :target="activity.target"
        :username="activity.username"
        :time="activity.time"
      />
    </div>
  </Card>
</template>

<script setup lang="ts">
import Card from '@/components/ui/Card.vue'
import Alert from '@/components/ui/Alert.vue'
import RecentActivityItem from './RecentActivityItem.vue'

interface Activity {
  id: number | string
  icon: string
  target: string
  username: string
  time: string
}

interface Props {
  activities: Activity[]
  isLoading?: boolean
  error?: Error | null
}

defineProps<Props>()
</script>
