<template>
  <div class="bg-base-100 border border-primary-stroke rounded-card">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.activity.title') }}</h2>
      <p class="text-sm text-secondary mt-1">
        {{ $t('settings.security.activity.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <div class="flex flex-col gap-3">
        <div
          v-for="activity in recentActivity"
          :key="activity.id"
          class="flex items-center gap-3 p-3 border border-primary-stroke rounded-lg"
        >
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center"
            :class="getActivityIconClass(activity.type)"
          >
            <i :class="activity.icon"></i>
          </div>
          <div class="flex-1">
            <p class="text-sm font-medium">{{ activity.title }}</p>
            <p class="text-xs text-secondary">{{ activity.description }}</p>
            <p class="text-xs text-secondary">
              {{ formatDate(activity.timestamp) }}
            </p>
          </div>
          <div v-if="activity.location" class="text-xs text-secondary">
            {{ activity.location }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Activity {
  id: string
  type: 'login' | 'security' | 'update'
  icon: string
  title: string
  description: string
  location?: string
  timestamp: Date
}

defineProps<{
  recentActivity: Activity[]
}>()

function getActivityIconClass(type: string) {
  switch (type) {
    case 'security':
      return 'bg-error-light text-error-light-content'
    case 'login':
      return 'bg-success-light text-success-light-content'
    default:
      return 'bg-info-light text-info-light-content'
  }
}

function formatDate(date: Date) {
  return date.toLocaleString()
}
</script>
