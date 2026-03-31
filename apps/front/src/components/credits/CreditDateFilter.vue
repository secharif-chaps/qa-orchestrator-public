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
  { value: '7d', label: t('settings.credits.period.7d') },
  { value: '30d', label: t('settings.credits.period.30d') },
  { value: '90d', label: t('settings.credits.period.90d') },
  { value: 'all', label: t('settings.credits.period.all') },
]
</script>
