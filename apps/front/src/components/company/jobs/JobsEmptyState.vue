<template>
  <div class="bg-base-100 rounded-lg p-8">
    <div class="text-center">
      <div class="mb-6">
        <div
          class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full"
          :class="containerClass"
        >
          <i :class="iconClass" class="text-2xl"></i>
        </div>
      </div>

      <h3 class="text-secondary mb-3 text-xl font-semibold">{{ title }}</h3>
      <p class="text-secondary mx-auto mb-6 max-w-md">{{ description }}</p>

      <!-- Loading State -->
      <div v-if="type === 'loading'" class="flex justify-center">
        <div class="text-secondary flex items-center gap-3">
          <i class="fa fa-spinner animate-spin"></i>
          <span>{{ $t('screen.jobs.loading') }}</span>
        </div>
      </div>

      <!-- Actions Slot -->
      <div v-else>
        <slot name="actions">
          <!-- Default action hint -->
          <div v-if="type === 'no-data'" class="text-secondary text-sm">
            {{ $t('screen.jobs.emptyState.hint') }}
          </div>
        </slot>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  type: 'no-data' | 'no-results' | 'loading'
  searchQuery?: string
}

const props = defineProps<Props>()

const iconClass = computed(() => {
  switch (props.type) {
    case 'no-results':
      return 'fa fa-search text-orange-500'
    case 'loading':
      return 'fa fa-briefcase text-sage-content'
    default:
      return 'fa fa-briefcase text-slate-500'
  }
})

const containerClass = computed(() => {
  switch (props.type) {
    case 'no-results':
      return 'bg-orange-50 dark:bg-orange-900/20'
    case 'loading':
      return 'bg-primary/10'
    default:
      return 'bg-slate-50 dark:bg-slate-800/50'
  }
})

const title = computed(() => {
  switch (props.type) {
    case 'no-results':
      return props.searchQuery ? `No jobs found for "${props.searchQuery}"` : 'No jobs found'
    case 'loading':
      return 'Analyzing Job Market'
    default:
      return 'No Job Listings'
  }
})

const description = computed(() => {
  switch (props.type) {
    case 'no-results':
      return 'Try adjusting your search terms or check for different job titles, departments, or locations.'
    case 'loading':
      return "We're scanning job boards and career pages to find current openings and analyze hiring trends for this company."
    default:
      return 'Current job openings, hiring insights, and career opportunities will be displayed here once the analysis is complete.'
  }
})
</script>
