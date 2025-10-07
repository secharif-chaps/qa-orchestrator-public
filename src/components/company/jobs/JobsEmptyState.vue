<template>
  <div class="bg-base-100 p-8 rounded-lg">
    <div class="text-center">
      <div class="mb-6">
        <div 
          class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4"
          :class="containerClass"
        >
          <i :class="iconClass" class="text-2xl"></i>
        </div>
      </div>
      
      <h3 class="text-xl font-semibold text-primary mb-3">{{ title }}</h3>
      <p class="text-primary-light-content mb-6 max-w-md mx-auto">{{ description }}</p>
      
      <!-- Loading State -->
      <div v-if="type === 'loading'" class="flex justify-center">
        <div class="flex items-center gap-3 text-primary-light-content">
          <i class="fa fa-spinner animate-spin"></i>
          <span>{{ $t('jobs.loading', 'Analyzing job market and opportunities...') }}</span>
        </div>
      </div>
      
      <!-- Actions Slot -->
      <div v-else>
        <slot name="actions">
          <!-- Default action hint -->
          <div v-if="type === 'no-data'" class="text-sm text-primary-light-content">
            {{ $t('jobs.emptyState.hint', 'Job listings and hiring insights will appear here once available') }}
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
      return 'fa fa-briefcase text-primary'
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
      return props.searchQuery 
        ? `No jobs found for "${props.searchQuery}"`
        : 'No jobs found'
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
      return 'We\'re scanning job boards and career pages to find current openings and analyze hiring trends for this company.'
    default:
      return 'Current job openings, hiring insights, and career opportunities will be displayed here once the analysis is complete.'
  }
})
</script>