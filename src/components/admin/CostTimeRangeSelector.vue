<template>
  <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between mb-6">
    <div>
      <h1 class="text-3xl font-bold">Cost Analysis Dashboard</h1>
      <p class="text-primary-light-content mt-2">
        Comprehensive overview of application costs and usage metrics
      </p>
    </div>

    <div class="flex flex-col gap-3 items-end">
      <!-- Button Group for Time Range Selection -->
      <ButtonGroup
        v-model="selectedPreset"
        :options="buttonGroupOptions"
        @update:model-value="handlePresetChange"
      />

      <!-- Custom Date Range (shown when custom is selected) -->
      <transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
      >
        <div v-if="selectedPreset === 'custom'" class="flex gap-2 items-center">
          <input
            v-model="startDate"
            type="date"
            class="px-3 py-2 text-sm border border-primary-stroke rounded-lg bg-base-100 text-primary focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
            @change="handleCustomDateChange"
          />
          <span class="text-primary-light-content text-sm">to</span>
          <input
            v-model="endDate"
            type="date"
            class="px-3 py-2 text-sm border border-primary-stroke rounded-lg bg-base-100 text-primary focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
            @change="handleCustomDateChange"
          />
        </div>
      </transition>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import ButtonGroup from '@/components/ui/ButtonGroup.vue'

interface DatePreset {
  key: string
  label: string
  startDate: string
  endDate: string
  icon?: string
}

interface Props {
  modelValue: {
    start_date?: string
    end_date?: string
  }
}

interface Emits {
  (e: 'update:modelValue', value: { start_date?: string; end_date?: string }): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const selectedPreset = ref('current-month')

const getCurrentMonthDates = () => {
  const now = new Date()
  const firstDay = new Date(now.getFullYear(), now.getMonth(), 1)
  const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0)

  return {
    start: firstDay.toISOString().split('T')[0],
    end: lastDay.toISOString().split('T')[0],
  }
}

const getLast30DaysDates = () => {
  const now = new Date()
  const thirtyDaysAgo = new Date(now)
  thirtyDaysAgo.setDate(now.getDate() - 30)

  return {
    start: thirtyDaysAgo.toISOString().split('T')[0],
    end: now.toISOString().split('T')[0],
  }
}

const getCurrentMonthPreset = () => {
  const dates = getCurrentMonthDates()
  return {
    key: 'current-month',
    label: 'Current Month',
    startDate: dates.start,
    endDate: dates.end,
  }
}

const getLast30DaysPreset = () => {
  const dates = getLast30DaysDates()
  return {
    key: 'last-30-days',
    label: 'Last 30 Days',
    startDate: dates.start,
    endDate: dates.end,
  }
}

const presets: DatePreset[] = [
  {
    ...getCurrentMonthPreset(),
    icon: 'fa fa-calendar',
  },
  {
    ...getLast30DaysPreset(),
    icon: 'fa fa-clock',
  },
  {
    key: 'all-time',
    label: 'All Time',
    startDate: '',
    endDate: '',
    icon: 'fa fa-infinity',
  },
]

const presetsWithCustom = computed(() => [
  ...presets,
  {
    key: 'custom',
    label: 'Custom',
    startDate: '',
    endDate: '',
    icon: 'fa fa-calendar-days',
  },
])

// Convert presets to ButtonGroup options format
const buttonGroupOptions = computed(() =>
  presetsWithCustom.value.map(preset => ({
    value: preset.key,
    label: preset.label,
    icon: preset.icon,
  }))
)

const startDate = ref(props.modelValue.start_date || getCurrentMonthDates().start)
const endDate = ref(props.modelValue.end_date || getCurrentMonthDates().end)

const handlePresetChange = (presetKey: string | number) => {
  const preset = presetsWithCustom.value.find(p => p.key === presetKey)
  if (preset) {
    selectPreset(preset)
  }
}

const selectPreset = (preset: DatePreset) => {
  selectedPreset.value = preset.key

  if (preset.key === 'custom') {
    // Keep the current dates when switching to custom
    return
  } else if (preset.key === 'all-time') {
    startDate.value = ''
    endDate.value = ''
  } else {
    startDate.value = preset.startDate
    endDate.value = preset.endDate
  }
}

const handleCustomDateChange = () => {
  // Automatically select custom when dates are manually changed
  selectedPreset.value = 'custom'
}

// Watch for changes and emit
watch(
  [startDate, endDate],
  () => {
    // Update selected preset based on current dates
    const currentDates = { start: startDate.value, end: endDate.value }
    const matchingPreset = presets.find(
      (preset) => preset.startDate === currentDates.start && preset.endDate === currentDates.end,
    )

    if (matchingPreset) {
      selectedPreset.value = matchingPreset.key
    } else {
      selectedPreset.value = 'custom'
    }

    emit('update:modelValue', {
      start_date: startDate.value || undefined,
      end_date: endDate.value || undefined,
    })
  },
  { immediate: true },
)

// Initialize with current month by default
const currentMonthPreset = presets.find((p) => p.key === 'current-month')!
selectPreset(currentMonthPreset)
</script>
