<template>
  <Card padding="p-6">
    <!-- Header with filters -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <h3 class="text-lg font-semibold">
        {{ $t('credits.dailyUsage.title', 'Consommation quotidienne') }}
      </h3>

      <div class="flex items-center gap-3 flex-wrap">
        <!-- Module filter -->
        <CreditModuleFilter v-model="selectedModule" />

        <!-- Period filter -->
        <CreditDateFilter v-model:period="selectedPeriod" />
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-spinner animate-spin text-2xl text-secondary mb-2"></i>
        <p class="text-sm text-secondary">{{ $t('common.loading', 'Chargement...') }}</p>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!hasData" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-chart-bar text-2xl text-secondary mb-2"></i>
        <p class="text-sm text-secondary">
          {{ $t('credits.dailyUsage.noData', 'Aucune consommation pour cette période') }}
        </p>
      </div>
    </div>

    <!-- Chart -->
    <DailyCreditUsageChart v-else :daily-usage="dailyUsage" />
  </Card>
</template>

<script setup lang="ts">
/**
 * Daily credit usage card with filters and bar chart.
 */
import { computed } from 'vue'
import type { DailyUsage } from '@/types/credits'
import Card from '@/components/ui/Card.vue'
import CreditModuleFilter from './CreditModuleFilter.vue'
import CreditDateFilter from './CreditDateFilter.vue'
import DailyCreditUsageChart from './DailyCreditUsageChart.vue'

interface Props {
  dailyUsage: DailyUsage[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const selectedModule = defineModel<string>('module', { default: 'all' })
const selectedPeriod = defineModel<string>('period', { default: '30d' })

const hasData = computed(() => {
  return props.dailyUsage.some(item => item.creditsConsumed > 0)
})
</script>
