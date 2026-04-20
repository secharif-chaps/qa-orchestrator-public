<template>
  <div
    v-if="hasData"
    class="bg-primary-lightest rounded-card border-primary-lighter-stroke flex flex-col gap-4 border p-6"
  >
    <h3 class="text-base font-semibold">
      {{ t('screen.profile.sections.financial.keyMetrics.title') }}
    </h3>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
      <FinancialMetricCard
        v-if="revenue?.value"
        icon="fa fa-money-bill"
        :label="t('screen.profile.sections.financial.keyMetrics.revenue')"
        :sourced-value="revenue"
        :growth-tag="revenueGrowth?.value ?? undefined"
        :growth-positive="isGrowthPositive"
      />
      <FinancialMetricCard
        v-if="grossMargin?.value"
        icon="fa fa-chart-pie"
        :label="t('screen.profile.sections.financial.keyMetrics.grossMargin')"
        :sourced-value="grossMargin"
      />
      <FinancialMetricCard
        v-if="ebitdaMargin?.value"
        icon="fa fa-chart-bar"
        :label="t('screen.profile.sections.financial.keyMetrics.ebitdaMargin')"
        :sourced-value="ebitdaMargin"
      />
      <FinancialMetricCard
        v-if="netMargin?.value"
        icon="fa fa-percent"
        :label="t('screen.profile.sections.financial.keyMetrics.netMargin')"
        :sourced-value="netMargin"
      />
      <FinancialMetricCard
        v-if="debtToEquity?.value"
        icon="fa fa-balance-scale"
        :label="t('screen.profile.sections.financial.keyMetrics.debtToEquity')"
        :sourced-value="debtToEquity"
      />
      <FinancialMetricCard
        v-if="freeCashFlow?.value"
        icon="fa fa-coins"
        :label="t('screen.profile.sections.financial.keyMetrics.freeCashFlow')"
        :sourced-value="freeCashFlow"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FinancialMetricCard from './FinancialMetricCard.vue'
import type { SourcedValue } from '@/types/company'

const { t } = useI18n()

interface Props {
  revenue: SourcedValue<string> | null
  revenueGrowth: SourcedValue<string> | null
  grossMargin: SourcedValue<string> | null
  ebitdaMargin: SourcedValue<string> | null
  netMargin: SourcedValue<string> | null
  debtToEquity: SourcedValue<string> | null
  freeCashFlow: SourcedValue<string> | null
}

const { revenue, revenueGrowth, grossMargin, ebitdaMargin, netMargin, debtToEquity, freeCashFlow } =
  defineProps<Props>()

const hasData = computed(
  () =>
    revenue?.value ||
    grossMargin?.value ||
    ebitdaMargin?.value ||
    netMargin?.value ||
    debtToEquity?.value ||
    freeCashFlow?.value,
)

const isGrowthPositive = computed(() => {
  if (!revenueGrowth?.value) return false
  const num = parseFloat(revenueGrowth.value.replace(/[^-\d.]/g, ''))
  return !isNaN(num) && num > 0
})
</script>
