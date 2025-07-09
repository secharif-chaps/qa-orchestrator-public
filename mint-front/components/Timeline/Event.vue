<template>
  <div class="relative flex items-start gap-6 pb-6">
    <!-- Date indicator -->
    <div class="w-36 text-right pt-3">
      <div class="text-sm font-medium text-secondary pt-2.5">
        {{ formattedDate }}
      </div>
    </div>

    <!-- Timeline line -->
    <div class="absolute top-6 left-[163px] w-0.5 h-full bg-bg2"></div>

    <!-- Timeline dot -->
    <div class="relative">
      <div class="absolute top-6 -left-2.5 w-4 h-4 rounded-full bg-primary ring-4 ring-primary/20 border-2 border-white dark:border-slate-800"></div>
    </div>

    <!-- Event content -->
    <div class="flex-1 bg-bg1 border border-border-2 rounded-lg p-5">
      <!-- Event Header -->
      <div class="flex items-start justify-between mb-3">
        <h3 class="text-lg font-semibold leading-tight">
          {{ event.title }}
        </h3>
        <OIndicator 
          v-if="getCategoryColor(event.category)"
          :color="getCategoryColor(event.category)"
          size="sm"
        />
      </div>

      <!-- Tags/Badges -->
      <div class="flex gap-2 mb-4 flex-wrap">
        <OBadge 
          :text="event.category"
          color="primary"
          size="sm"
          class="flex items-center gap-1"
        >
          <i class="fa fa-clipboard text-xs"></i>
          {{ event.category }}
        </OBadge>
        
        <OBadge
          v-if="event.location"
          :text="event.location"
          color="slate"
          size="sm"
          class="flex items-center gap-1"
        >
          <i class="fa fa-map-marker-alt text-xs"></i>
          {{ event.location }}
        </OBadge>
      </div>

      <!-- Description -->
      <p class="text-secondary mb-4 leading-relaxed">
        {{ event.description }}
      </p>

      <!-- Impact Section -->
      <div v-if="event.impact" class="bg-bg3 border border-border-2 rounded-lg p-4 mb-4">
        <div class="flex items-center gap-2 mb-2">
          <i class="fa fa-bolt text-yellow-500 text-sm"></i>
          <span class="text-xs uppercase font-semibold text-secondary tracking-wide">
            Impact Analysis
          </span>
        </div>
        <p class="text-sm text-secondary italic leading-relaxed">
          {{ event.impact }}
        </p>
      </div>

      <!-- Source -->
      <div v-if="event.source" class="flex justify-end">
        <Source :source="event.source" />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { OBadge, OIndicator } from '@owlint/feathers-vue'

interface TimelineEvent {
  date: string
  title: string
  description: string
  category: string
  location?: string
  impact?: string
  source?: string
}

const props = defineProps<{
  event: TimelineEvent
}>()

// Get color for category indicator
const getCategoryColor = (category: string) => {
  const categoryColorMap: Record<string, string> = {
    'funding': 'green',
    'acquisition': 'blue',
    'product': 'purple',
    'expansion': 'orange',
    'partnership': 'indigo',
    'leadership': 'pink',
    'milestone': 'yellow',
    'award': 'emerald',
    'regulatory': 'red',
    'technology': 'cyan'
  }
  
  const normalizedCategory = category.toLowerCase()
  return categoryColorMap[normalizedCategory] || 'slate'
}

// Format date for display (handle partial dates like YYYY or YYYY-MM)
const formattedDate = computed(() => {
  const dateStr = props.event.date

  if (!dateStr) return 'N/A'

  if (dateStr.length === 4) {
    return dateStr // Just the year
  } else if (dateStr.length === 7) {
    // YYYY-MM format
    const [year, month] = dateStr.split('-')
    const date = new Date(parseInt(year), parseInt(month) - 1)
    return date.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'long',
    })
  } else {
    // Full date
    const date = new Date(dateStr)
    return date.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    })
  }
})
</script>

<style scoped>
/* Enhanced timeline styles with animations */
.timeline-event {
  animation: slideInFromLeft 0.5s ease-out;
}

@keyframes slideInFromLeft {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

/* Hover effects */
.timeline-event:hover {
  transform: translateY(-2px);
  transition: transform 0.2s ease;
}
</style>
