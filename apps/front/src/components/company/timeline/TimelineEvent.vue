<template>
  <TimelineItem :date="formattedDate">
    <TimelineCard>
      <!-- Event Header -->
      <div class="gap-2xs flex items-center">
        <h3 class="leading-3 font-bold">
          {{ displayedEvent.title }}
        </h3>
        <Source v-if="eventSource" :source="eventSource" />
      </div>

      <!-- Tags/Badges -->
      <div v-if="displayedEvent.category || displayedEvent.location" class="flex flex-wrap gap-2">
        <Tag v-if="displayedEvent.category" icon="fa-clipboard" size="sm" variant="secondary">
          {{ displayedEvent.category }}
        </Tag>

        <Tag
          v-if="displayedEvent.location"
          color="indigo"
          variant="secondary"
          size="sm"
          icon="fa-map-marker-alt"
        >
          {{ displayedEvent.location }}
        </Tag>
      </div>

      <!-- Description -->
      <p class="leading-3">
        {{ displayedEvent.description }}
      </p>

      <Alert
        v-if="displayedEvent.impact"
        color="pink"
        icon="fa-bolt"
        :title="t('screen.timeline.event.impactAnalysis')"
        :description="displayedEvent.impact"
      />
    </TimelineCard>
  </TimelineItem>
</template>

<script lang="ts" setup>
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import TimelineCard from '@/components/ui/TimelineCard.vue'
import TimelineItem from '@/components/ui/TimelineItem.vue'
import type { SourcedValue } from '@/types/company'
import { Alert, Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Source from '../Source.vue'

const { t, locale } = useI18n()

interface TimelineEvent {
  date: SourcedValue<string>
  title: SourcedValue<string>
  description: SourcedValue<string>
  category: SourcedValue<string>
  location?: SourcedValue<string>
  impact?: SourcedValue<string>
  source?: string
}

interface Props {
  event: TimelineEvent
}

const { event } = defineProps<Props>()

const displayedEvent = computed(() => {
  return {
    date: getSourcedValue(event.date),
    title: getSourcedValue(event.title),
    category: getSourcedValue(event.category),
    location: getSourcedValue(event.location),
    description: getSourcedValue(event.description),
    impact: getSourcedValue(event.impact),
    source: event.source,
  }
})

const eventSource = computed(() => event.source)

const formattedDate = computed(() => {
  const dateStr = displayedEvent.value.date as string

  if (!dateStr) return t('common.na')

  // Pure 4-digit year (e.g. "1959")
  if (/^\d{4}$/.test(dateStr)) return dateStr

  // YYYY-MM (e.g. "1959-03")
  if (/^\d{4}-\d{2}$/.test(dateStr)) {
    const [year, month] = dateStr.split('-')
    return new Date(parseInt(year), parseInt(month) - 1).toLocaleDateString(locale.value, {
      month: '2-digit',
      year: 'numeric',
    })
  }

  // Full parseable date — fall back to raw string if native parsing fails
  // (handles LLM dates like "circa 2020", "Early 2020s", "2020–2023")
  const date = new Date(dateStr)
  if (!isNaN(date.getTime())) {
    return date.toLocaleDateString(locale.value, {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  }

  return dateStr
})
</script>
