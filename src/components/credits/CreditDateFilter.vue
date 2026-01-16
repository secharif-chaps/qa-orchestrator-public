<template>
  <div class="flex items-center gap-2">
    <!-- Period Preset Dropdown -->
    <Select
      v-model="selectedPeriod"
      :options="periodOptions"
      :placeholder="$t('credits.period.select', 'Period')"
      icon="fa fa-calendar"
    />

    <!-- Custom Date Range (shown when period is 'custom') -->
    <template v-if="selectedPeriod === 'custom'">
      <input
        v-model="startDate"
        type="date"
        class="px-3 py-2 bg-base-200 border border-primary-stroke rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
        :placeholder="$t('credits.period.startDate', 'Start')"
      />
      <span class="text-secondary">-</span>
      <input
        v-model="endDate"
        type="date"
        class="px-3 py-2 bg-base-200 border border-primary-stroke rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
        :placeholder="$t('credits.period.endDate', 'End')"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
/**
 * Date filter component for credit statistics.
 * Provides period presets (7d, 30d, 90d) and custom date range.
 */
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Select } from '@owlint/feathers-vue'

const { t } = useI18n()

const selectedPeriod = defineModel<string>('period', { default: '30d' })
const startDate = defineModel<string>('startDate', { default: '' })
const endDate = defineModel<string>('endDate', { default: '' })

const periodOptions = computed(() => [
  { value: '7d', label: t('credits.period.7d', '7 derniers jours') },
  { value: '30d', label: t('credits.period.30d', '30 derniers jours') },
  { value: '90d', label: t('credits.period.90d', '90 derniers jours') },
  { value: 'custom', label: t('credits.period.custom', 'Personnalisé') },
])

// Reset custom dates when switching away from custom
watch(selectedPeriod, (newPeriod) => {
  if (newPeriod !== 'custom') {
    startDate.value = ''
    endDate.value = ''
  }
})
</script>
