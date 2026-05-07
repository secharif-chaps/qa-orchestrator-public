<template>
  <div
    v-if="hasData"
    class="bg-primary-lightest rounded-card border-primary-lighter-stroke flex flex-col gap-4 border p-6"
  >
    <h3 class="text-base font-semibold">
      {{ t('screen.profile.sections.financial.valuation.title') }}
    </h3>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
      <FinancialMetricCard
        v-if="marketCap?.value"
        icon="fa-landmark"
        :label="t('screen.profile.sections.financial.valuation.marketCap')"
        :sourced-value="marketCap"
      />
      <FinancialMetricCard
        v-if="enterpriseValue?.value"
        icon="fa-building"
        :label="t('screen.profile.sections.financial.valuation.enterpriseValue')"
        :sourced-value="enterpriseValue"
      />
      <FinancialMetricCard
        v-if="peRatio?.value"
        icon="fa-calculator"
        :label="t('screen.profile.sections.financial.valuation.peRatio')"
        :sourced-value="peRatio"
      />
      <FinancialMetricCard
        v-if="evEbitda?.value"
        icon="fa-chart-line"
        :label="t('screen.profile.sections.financial.valuation.evEbitda')"
        :sourced-value="evEbitda"
      />
      <FinancialMetricCard
        v-if="evRevenue?.value"
        icon="fa-chart-area"
        :label="t('screen.profile.sections.financial.valuation.evRevenue')"
        :sourced-value="evRevenue"
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
  marketCap: SourcedValue<string> | null
  enterpriseValue: SourcedValue<string> | null
  peRatio: SourcedValue<string> | null
  evEbitda: SourcedValue<string> | null
  evRevenue: SourcedValue<string> | null
}

const { marketCap, enterpriseValue, peRatio, evEbitda, evRevenue } = defineProps<Props>()

const hasData = computed(
  () =>
    marketCap?.value ||
    enterpriseValue?.value ||
    peRatio?.value ||
    evEbitda?.value ||
    evRevenue?.value,
)
</script>
