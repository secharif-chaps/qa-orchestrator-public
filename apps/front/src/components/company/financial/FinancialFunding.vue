<template>
  <div
    v-if="hasData"
    class="bg-primary-lightest rounded-card border-primary-lighter-stroke flex flex-col gap-4 border p-6"
  >
    <h3 class="text-base font-semibold">
      {{ t('screen.profile.sections.financial.funding.title') }}
    </h3>

    <!-- Summary metrics -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <FinancialMetricCard
        v-if="totalFunding?.value"
        icon="fa-hand-holding-usd"
        :label="t('screen.profile.sections.financial.funding.totalFunding')"
        :sourced-value="totalFunding"
      />
      <FinancialMetricCard
        v-if="lastValuation?.value"
        icon="fa fa-gem"
        :label="t('screen.profile.sections.financial.funding.lastValuation')"
        :sourced-value="lastValuation"
      />
    </div>

    <!-- Funding Rounds Timeline -->
    <div v-if="fundingRounds.length > 0" class="flex flex-col gap-2">
      <h4 class="text-sm font-semibold">
        {{ t('screen.profile.sections.financial.funding.rounds') }}
      </h4>
      <div class="relative">
        <FinancialFundingRoundItem
          v-for="(round, index) in fundingRounds"
          :key="index"
          :round="round"
        />
      </div>
    </div>

    <!-- Empty rounds state -->
    <p
      v-else-if="totalFunding?.value || lastValuation?.value"
      class="text-neutral-black-font/70 text-sm"
    >
      {{ t('screen.profile.sections.financial.funding.noRounds') }}
    </p>
  </div>
</template>

<script lang="ts" setup>
import type { FundingRound, SourcedValue } from '@/types/company'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FinancialFundingRoundItem from './FinancialFundingRoundItem.vue'
import FinancialMetricCard from './FinancialMetricCard.vue'

const { t } = useI18n()

interface Props {
  totalFunding: SourcedValue<string> | null
  lastValuation: SourcedValue<string> | null
  fundingRounds: FundingRound[]
}

const { totalFunding, lastValuation, fundingRounds } = defineProps<Props>()

const hasData = computed(
  () => totalFunding?.value || lastValuation?.value || fundingRounds.length > 0,
)
</script>
