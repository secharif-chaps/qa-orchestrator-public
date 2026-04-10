<template>
  <div class="border-primary-lighter-stroke rounded-card border bg-white">
    <div class="border-primary-lighter-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.security.activity.title') }}</h2>
      <p class="text-neutral-black-font mt-1 text-sm">
        {{ $t('settings.security.activity.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <!-- Loading State -->
      <div v-if="isLoading && events.length === 0" class="flex flex-col gap-3">
        <div
          v-for="i in 3"
          :key="i"
          class="border-primary-lighter-stroke flex animate-pulse items-center gap-3 rounded-sm border p-3"
        >
          <div class="bg-primary-lightest h-10 w-10 rounded-sm"></div>
          <div class="flex flex-1 flex-col gap-2">
            <div class="bg-primary-lightest h-4 w-1/3 rounded"></div>
            <div class="bg-primary-lightest h-3 w-1/2 rounded"></div>
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
        <p v-if="events.length === 0" class="text-neutral-black-font py-8 text-center text-sm">
          {{ $t('settings.security.activity.noEvents') }}
        </p>

        <!-- Event Items -->
        <ActivityEventItem v-for="event in events" :key="event.id" :event="event" />

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
import type { ActivityEvent } from '@/types/account'
import { Alert, Button } from '@owlint/feathers-vue'
import ActivityEventItem from './ActivityEventItem.vue'

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
