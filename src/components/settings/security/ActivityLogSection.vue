<template>
  <div class="bg-base-100 border border-primary-stroke rounded-card">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.activity.title') }}</h2>
      <p class="text-sm text-secondary mt-1">
        {{ $t('settings.security.activity.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <!-- Loading State -->
      <div v-if="isLoading && events.length === 0" class="flex flex-col gap-3">
        <div
          v-for="i in 3"
          :key="i"
          class="flex items-center gap-3 p-3 border border-primary-stroke rounded-lg animate-pulse"
        >
          <div class="w-10 h-10 rounded-lg bg-base-200"></div>
          <div class="flex-1 flex flex-col gap-2">
            <div class="h-4 bg-base-200 rounded w-1/3"></div>
            <div class="h-3 bg-base-200 rounded w-1/2"></div>
          </div>
        </div>
      </div>

      <!-- Error State -->
      <Alert
        v-else-if="error"
        variant="danger"
        :title="$t('settings.security.activity.error')"
        :message="error.message || $t('settings.security.activity.errorDescription')"
        icon="fa fa-exclamation-circle"
      />

      <!-- Events List -->
      <div v-else class="flex flex-col gap-3">
        <!-- Empty State -->
        <p v-if="events.length === 0" class="text-sm text-secondary text-center py-8">
          {{ $t('settings.security.activity.noEvents') }}
        </p>

        <!-- Event Items -->
        <div
          v-for="event in events"
          :key="event.id"
          class="flex items-center gap-3 p-3 border border-primary-stroke rounded-lg"
        >
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center"
            :class="getEventIconClass(event.displayType)"
          >
            <i :class="event.icon"></i>
          </div>
          <div class="flex-1">
            <p class="text-sm font-medium">{{ event.title }}</p>
            <p class="text-xs text-secondary">{{ event.description }}</p>
            <p class="text-xs text-secondary">
              {{ formatDate(event.timestamp) }}
            </p>
          </div>
          <div v-if="event.ipAddress" class="text-xs text-secondary">
            {{ event.ipAddress }}
          </div>
        </div>

        <!-- Load More Button -->
        <div v-if="hasMore" class="flex justify-center pt-4">
          <Button
            :label="isLoading ? $t('common.loading') : $t('settings.security.activity.loadMore')"
            variant="secondary"
            :disabled="isLoading"
            @click="handleLoadMore"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Alert, Button } from '@owlint/feathers-vue'
import type { ActivityEvent, ActivityEventType } from '@/types/account'

defineProps<{
  events: ActivityEvent[]
  isLoading: boolean
  error?: Error | null
  hasMore: boolean
}>()

const emit = defineEmits<{
  loadMore: []
}>()

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

function formatDate(dateString: string): string {
  const date = new Date(dateString)
  return date.toLocaleString()
}

function handleLoadMore() {
  emit('loadMore')
}
</script>
