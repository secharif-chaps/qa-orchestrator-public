<template>
  <div class="relative flex items-start gap-6 pb-6">
    <!-- Date indicator -->
    <div class="w-20 text-right pt-4">
      <div class="text-sm font-medium text-secondary">
        {{ formattedDate }}
      </div>
    </div>

    <!-- Timeline line -->
    <div class="absolute top-6 left-[101px] w-0.5 h-full bg-base-300"></div>

    <!-- Timeline dot -->
    <div class="relative">
      <div
        class="absolute top-4 -left-2.5 w-4 h-4 rounded-full bg-sage-50 dark:bg-base-100 ring-4 ring-white dark:ring-base-100 border-2 border-base-300 dark:border-base-300"
      ></div>
    </div>

    <!-- Event content -->
    <div class="flex-1 bg-base-100 border border-primary-stroke rounded-lg p-5">
      <!-- Event Header -->
      <div class="flex items-start justify-between mb-3">
        <h3 class="text-lg font-semibold leading-tight">
          {{ displayedEvent.title }}
        </h3>
      </div>

      <!-- Tags/Badges -->
      <div class="flex gap-2 mb-4 flex-wrap">
        <Tag size="sm" class="flex items-center gap-1">
          <i class="fa fa-clipboard text-xs"></i>
          {{ displayedEvent.category }}
        </Tag>

        <Tag v-if="displayedEvent.location" variant="almond" size="sm" class="flex items-center gap-1">
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
        :title="t('timeline.event.impactAnalysis')"
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
