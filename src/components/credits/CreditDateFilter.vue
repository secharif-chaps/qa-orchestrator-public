<template>
  <Toggle v-model="selectedPeriod" :options="periodOptions" variant="pill" />
</template>

<script setup lang="ts">
/**
 * Date filter component for credit statistics.
 * Provides period presets (7d, 30d, 90d, all) using a toggle.
 */
import { useI18n } from 'vue-i18n'
import { Toggle } from '@owlint/feathers-vue'

const { t } = useI18n()

const selectedPeriod = defineModel<string>('period', { default: '30d' })

const periodOptions = [
  { value: '7d', label: t('credits.period.7d', '7 days') },
  { value: '30d', label: t('credits.period.30d', '30 days') },
  { value: '90d', label: t('credits.period.90d', '90 days') },
  { value: 'all', label: t('credits.period.all', 'All time') },
]
</script>
