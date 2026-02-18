<template>
  <div
    v-if="datesFilterLabel"
    class="bg-sage-200 text-sage-600 flex items-center gap-1 rounded-sm p-1 text-sm"
  >
    <Icon icon="fa-calendar" />
    <span class="shrink">{{ datesFilterLabel }}</span>
    <button class="flex items-center justify-center" @click="emit('openEditFilter', 'dates')">
      <Icon icon="fa-pen" />
    </button>
    <button class="flex items-center justify-center" @click="emit('reset')">
      <Icon icon="fa-xmark" />
    </button>
  </div>
</template>

<script lang="ts" setup>
import { getLocalTimeZone } from '@internationalized/date'
import { Icon } from '@owlint/feathers-vue'
import type { DateRange } from 'reka-ui'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDate } from '~/composables/useDate'
import type { DatesPeriod, FilterDates } from '~/types/filter'

const { t, d } = useI18n()
const { getPeriodDates } = useDate()

interface Props {
  datesPicker?: DateRange
  selectedPeriod?: DatesPeriod
  selectedDateType?: FilterDates
  datesFilterCount?: number
  showDateType?: boolean
}

const {
  datesPicker = undefined,
  selectedPeriod = undefined,
  selectedDateType = undefined,
  datesFilterCount = 0,
  showDateType = true,
} = defineProps<Props>()

const emit = defineEmits<{
  openEditFilter: [filterType: string]
  reset: []
}>()

const datesFilterLabel = computed(() => {
  if (!datesFilterCount) return ''

  // For analysis, we don't have date type distinction, use a generic key
  // For documents, use the date type to determine if it's published or collected
  const filterTypeKey = showDateType
    ? selectedDateType === 'publication'
      ? 'published'
      : 'collected'
    : 'date' // Generic key for analysis

  let interval: {
    start: Date | undefined
    end: Date | undefined
  } = {
    start: undefined,
    end: undefined,
  }

  if (selectedPeriod) {
    interval = getPeriodDates(selectedPeriod)
  } else if (datesPicker) {
    interval.start = datesPicker.start?.toDate(getLocalTimeZone())
    interval.end = datesPicker.end?.toDate(getLocalTimeZone())
  }

  let datePattern: string
  if (interval?.end && interval?.start) {
    if (interval.end.getTime() === interval.start.getTime()) {
      datePattern = 'single_date'
    } else {
      datePattern = 'date_range'
    }
  } else if (interval.start) {
    datePattern = 'after_date'
  } else if (interval.end) {
    datePattern = 'before_date'
  } else {
    datePattern = 'no_date'
  }

  // Use different translation keys based on whether we have date type or not
  // For analysis (no date type), use "collected" keys as analysis is about collected documents
  const translationKey = showDateType
    ? `watch_files.filters.type.dates.${filterTypeKey}.${datePattern}`
    : `watch_files.filters.type.dates.collected.${datePattern}`

  const translationParams: Record<string, string> = {}

  if (interval.start) {
    translationParams.startDate = d(interval.start, 'short')
  }
  if (interval.end) {
    translationParams.endDate = d(interval.end, 'short')
  }

  return t(translationKey, translationParams)
})
</script>
