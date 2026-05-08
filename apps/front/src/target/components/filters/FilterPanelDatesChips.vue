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
import { getPeriodDates } from '@/utils/date'
import { getLocalTimeZone } from '@internationalized/date'
import { Icon } from '@owlint/feathers-vue'
import type { DatesPeriod, FilterDates } from '@target/types/filter'
import type { DateRange } from 'reka-ui'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t, d } = useI18n()

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

const resolveDateLabel = (
  usePublished: boolean,
  datePattern: string,
  params: Record<string, string>,
): string => {
  if (usePublished) {
    switch (datePattern) {
      case 'after_date':
        return t('target.watchFiles.filters.type.dates.published.after_date', params)
      case 'before_date':
        return t('target.watchFiles.filters.type.dates.published.before_date', params)
      case 'date_range':
        return t('target.watchFiles.filters.type.dates.published.date_range', params)
      case 'single_date':
        return t('target.watchFiles.filters.type.dates.published.single_date', params)
      default:
        return ''
    }
  } else {
    switch (datePattern) {
      case 'after_date':
        return t('target.watchFiles.filters.type.dates.collected.after_date', params)
      case 'before_date':
        return t('target.watchFiles.filters.type.dates.collected.before_date', params)
      case 'date_range':
        return t('target.watchFiles.filters.type.dates.collected.date_range', params)
      case 'single_date':
        return t('target.watchFiles.filters.type.dates.collected.single_date', params)
      default:
        return ''
    }
  }
}

const datesFilterLabel = computed(() => {
  if (!datesFilterCount) return ''

  // For analysis, we don't have date type distinction; use collected keys.
  // For documents, use the date type to determine if it's published or collected.
  const usePublished = showDateType && selectedDateType === 'publication'

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
    return ''
  }

  const translationParams: Record<string, string> = {}

  if (interval.start) {
    translationParams.startDate = d(interval.start, 'short')
  }
  if (interval.end) {
    translationParams.endDate = d(interval.end, 'short')
  }

  return resolveDateLabel(usePublished, datePattern, translationParams)
})
</script>
