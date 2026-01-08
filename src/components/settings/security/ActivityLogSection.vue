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
        <ActivityEventItem
          v-for="event in events"
          :key="event.id"
          :event="event"
        />

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
import ActivityEventItem from './ActivityEventItem.vue'
import type { ActivityEvent } from '@/types/account'

defineProps<{
  events: ActivityEvent[]
  isLoading: boolean
  error?: Error | null
  hasMore: boolean
}>()

const emit = defineEmits<{
  loadMore: []
}>()

function handleLoadMore() {
  emit('loadMore')
}
</script>
