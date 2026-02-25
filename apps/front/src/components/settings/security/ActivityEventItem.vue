<template>
  <div class="border-primary-stroke flex items-center gap-3 rounded-lg border p-3">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg" :class="iconClass">
      <i :class="event.icon"></i>
    </div>
    <div class="flex-1">
      <p class="text-sm font-medium">{{ event.title }}</p>
      <p class="text-secondary text-xs">{{ event.description }}</p>
      <p class="text-secondary text-xs">
        {{ formatDate(event.timestamp) }}
      </p>
    </div>
    <div v-if="event.ipAddress" class="text-secondary text-xs">
      {{ event.ipAddress }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatDate } from '@/utils/time'
import type { ActivityEvent, ActivityEventType } from '@/types/account'

const props = defineProps<{
  event: ActivityEvent
}>()

const iconClass = computed(() => {
  return getEventIconClass(props.event.displayType)
})

function getEventIconClass(type: ActivityEventType): string {
  switch (type) {
    case 'security':
      // Red/danger for security events (failed logins, password changes)
      return 'bg-red-100 border border-red-200 text-red-600 dark:bg-red-900/30 dark:border-red-800 dark:text-red-400'
    case 'login':
      // Green/success for login events (successful logins, logouts)
      return 'bg-green-100 border border-green-200 text-green-600 dark:bg-green-900/30 dark:border-green-800 dark:text-green-400'
    default:
      // Blue/info for profile updates and other events
      return 'bg-blue-100 border border-blue-200 text-blue-600 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-400'
  }
}
</script>
