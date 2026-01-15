<template>
  <Toggle v-model="selectedRange" :options="rangeOptions" variant="pill" />
</template>

<script setup lang="ts">
/**
 * Time range toggle component for usage dashboard filtering.
 *
 * Wraps the Vuellar Toggle component with predefined time range options
 * and exposes computed start/end dates for API filtering.
 */
import { computed, watch } from 'vue'
import { Toggle } from '@owlint/feathers-vue'

/** Available time range keys that determine date filtering */
type TimeRangeKey = '7d' | '30d' | '90d' | 'all'

interface Props {
  /** Currently selected time range key */
  modelValue: TimeRangeKey
}

const props = defineProps<Props>()

const emit = defineEmits<{
  /** Emitted when the selected range changes */
  'update:modelValue': [value: TimeRangeKey]
  /** Emitted with computed dates when range changes, for parent convenience */
  'update:dates': [dates: { startDate: string | null; endDate: string }]
}>()

// Two-way binding for the selected range
const selectedRange = computed({
  get: () => props.modelValue,
  set: (value: TimeRangeKey) => emit('update:modelValue', value),
})

// Toggle options configuration
const rangeOptions = [
  { value: '7d', label: 'Last 7 days' },
  { value: '30d', label: 'Last 30 days' },
  { value: '90d', label: 'Last 90 days' },
  { value: 'all', label: 'All time' },
]

/**
 * Computes the start date based on the selected time range.
 * Returns ISO format date string for API consumption.
 * Returns null for 'all' time range (no start date filter).
 */
const startDate = computed((): string | null => {
  const now = new Date()
  let daysAgo: number | null = null

  switch (props.modelValue) {
    case '7d':
      daysAgo = 7
      break
    case '30d':
      daysAgo = 30
      break
    case '90d':
      daysAgo = 90
      break
    case 'all':
      // Return null for 'all time' - no start date restriction
      return null
  }

  if (daysAgo !== null) {
    const date = new Date(now)
    date.setDate(date.getDate() - daysAgo)
    // Return date-only format (YYYY-MM-DD) for API
    return date.toISOString().split('T')[0]
  }

  return null
})

/**
 * Computes the end date (always today).
 * Returns date-only format (YYYY-MM-DD) for API consumption.
 */
const endDate = computed((): string => {
  const now = new Date()
  // Return date-only format (YYYY-MM-DD) for API
  return now.toISOString().split('T')[0]
})

// Emit dates when range changes for parent convenience
watch(
  selectedRange,
  () => {
    emit('update:dates', {
      startDate: startDate.value,
      endDate: endDate.value,
    })
  },
  { immediate: true },
)

// Expose computed dates for direct access via template ref
defineExpose({
  startDate,
  endDate,
})
</script>
