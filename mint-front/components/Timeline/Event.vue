<template>
  <div class="relative flex items-start gap-6 pb-4">
    <!-- Date indicator -->
    <div class="w-36 text-right pt-2">
      {{ formattedDate }}
    </div>

    <!-- Timeline line -->

    <div class="absolute top-3 left-[163px] w-0.5 h-full bg-border-2"></div>

    <!-- Timeline dot -->
    <div class="relative">
      <div
        class="absolute top-3 -left-2.5 w-3 h-3 rounded-full bg-primary ring-4 ring-primary/30"
      ></div>
    </div>

    <!-- Event content -->
    <div class="flex-1 border border-border-2 rounded-lg p-4">
      <h3 class="text-lg font-bold text-primary">{{ event.title }}</h3>
      <div class="flex gap-2 mt-2 mb-4 flex-wrap">
        <span class="bg-bg3 text-xs rounded-full px-3 py-1 text-secondary">
          <i class="fa fa-clipboard mr-1"></i>
          {{ event.category }}
        </span>
        <span
          v-if="event.location"
          class="bg-bg3 text-secondary text-xs rounded-full px-3 py-1"
        >
          <i class="fa fa-map-marker-alt mr-1"></i> {{ event.location }}
        </span>
      </div>
      <p class="text-secondary mb-4">{{ event.description }}</p>

      <div
        v-if="event.impact"
        class="mt-3"
      >
        <div class="text-xs uppercase font-bold text-slate-500 mb-1">
          Impact
        </div>
        <p class="text-sm text-secondary italic">{{ event.impact }}</p>
      </div>

      <div
        v-if="event.source"
        class="mt-4 text-right"
      >
        <Source
          v-if="event.source"
          :source="event.source"
        />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
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
/* Custom timeline styles */
.bg-primary-50 {
  background-color: #ebf5ff;
}
.text-primary-700 {
  color: var(--color-primary);
}
</style>
