<template>
  <div class="relative flex items-start gap-6 pb-6">
    <!-- Date indicator -->
    <div class="w-20 pt-4 text-right">
      <div class="text-secondary text-sm font-medium">
        {{ formattedDate }}
      </div>
    </div>

    <!-- Timeline line -->
    <div class="bg-base-300 absolute top-6 left-[101px] h-full w-0.5"></div>

    <!-- Timeline dot -->
    <div class="relative">
      <div
        class="bg-sage-50 dark:bg-base-100 dark:ring-base-100 border-base-300 dark:border-base-300 absolute top-4 -left-2.5 h-4 w-4 rounded-full border-2 ring-4 ring-white"
      ></div>
    </div>

    <!-- Event content -->
    <div class="bg-base-100 border-primary-stroke flex-1 rounded-lg border p-5">
      <!-- Event Header -->
      <div class="mb-3 flex items-start justify-between">
        <h3 class="text-lg leading-tight font-semibold">
          {{ displayedEvent.title }}
        </h3>
      </div>

      <!-- Tags/Badges -->
      <div class="mb-4 flex flex-wrap gap-2">
        <Tag size="sm" class="flex items-center gap-1">
          <i class="fa fa-clipboard text-xs"></i>
          {{ displayedEvent.category }}
        </Tag>

        <Tag
          v-if="displayedEvent.location"
          variant="almond"
          size="sm"
          class="flex items-center gap-1"
        >
          <i class="fa fa-map-marker-alt text-xs"></i>
          {{ displayedEvent.location }}
        </Tag>
      </div>

      <!-- Description -->
      <p class="text-secondary mb-4 leading-relaxed">
        {{ displayedEvent.description }}
      </p>

      <Alert
        v-if="displayedEvent.impact"
        variant="primary"
        icon="fa fa-bolt"
        :title="t('screen.timeline.event.impactAnalysis')"
        :description="displayedEvent.impact"
      />

      <!-- Source -->
      <div v-if="eventSource" class="flex justify-end">
        <Source :source="eventSource" />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import Tag from '@/components/ui/Tag.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Source from '../Source.vue'
import type { SourcedValue } from '@/types/company'
import { Alert } from '@owlint/feathers-vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'

const { t } = useI18n()

interface TimelineEvent {
  date: SourcedValue<string>
  title: SourcedValue<string>
  description: SourcedValue<string>
  category: SourcedValue<string>
  location?: SourcedValue<string>
  impact?: SourcedValue<string>
  source?: string
}

const props = defineProps<{
  event: TimelineEvent
}>()

const displayedEvent = computed(() => {
  return {
    date: getSourcedValue(props.event.date),
    title: getSourcedValue(props.event.title),
    category: getSourcedValue(props.event.category),
    location: getSourcedValue(props.event.location),
    description: getSourcedValue(props.event.description),
    impact: getSourcedValue(props.event.impact),
    source: props.event.source,
  }
})

const eventSource = computed(() => props.event.source)

// Format date for display (handle partial dates like YYYY or YYYY-MM)
const formattedDate = computed(() => {
  const dateStr = displayedEvent.value.date as string

  if (!dateStr) return t('common.na')

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
      month: 'numeric',
      day: 'numeric',
    })
  }
})
</script>
