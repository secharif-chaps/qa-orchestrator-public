<template>
  <div class="bg-bg1 p-8 rounded-lg">
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
      <p class="text-secondary mb-6 max-w-md mx-auto">{{ description }}</p>
      
      <!-- Loading State -->
      <div v-if="type === 'loading'" class="flex justify-center">
        <div class="flex items-center gap-3 text-secondary">
          <i class="fa fa-spinner animate-spin"></i>
          <span>{{ $t('timeline.loading', 'Analyzing company timeline...') }}</span>
        </div>
      </div>
      
      <!-- Actions Slot -->
      <div v-else>
        <slot name="actions">
          <!-- Default action could be to suggest running timeline analysis -->
          <div v-if="type === 'no-data'" class="text-sm text-secondary">
            {{ $t('timeline.emptyState.hint', 'Timeline data will appear here once available') }}
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
      return 'fa fa-clock text-primary'
    default:
      return 'fa fa-calendar-days text-slate-500'
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
        ? `No events found for "${props.searchQuery}"`
        : 'No events found'
    case 'loading':
      return 'Building Timeline'
    default:
      return 'No Timeline Data'
  }
})

const description = computed(() => {
  switch (props.type) {
    case 'no-results':
      return 'Try adjusting your search terms or check for different event types and dates.'
    case 'loading':
      return 'We\'re analyzing the company\'s history and building a comprehensive timeline of important events and milestones.'
    default:
      return 'Timeline events and company milestones will be displayed here once the analysis is complete.'
  }
})
</script>