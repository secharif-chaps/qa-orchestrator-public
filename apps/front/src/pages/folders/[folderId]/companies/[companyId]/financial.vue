<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />

    <!-- Error State -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State -->
    <EmptyState
      v-else-if="company && !hasFinancialData"
      :title="$t('screen.profile.sections.financial.noData')"
    />

    <!-- Main Content -->
    <div v-else class="flex flex-col gap-6">
      <!-- Insights -->
      <ChapseAlert v-if="financial?.insights?.value" variant="mage">
        {{ financial.insights.value }}
      </ChapseAlert>

      <!-- Identity -->
      <FinancialIdentity
        :company-type="financial?.companyType ?? null"
        :ticker-symbol="financial?.tickerSymbol ?? null"
        :stock-exchange="financial?.stockExchange ?? null"
        :currency="financial?.currency ?? null"
      />

      <!-- Key Metrics -->
      <FinancialKeyMetrics
        :revenue="financial?.revenue ?? null"
        :revenue-growth="financial?.revenueGrowth ?? null"
        :gross-margin="financial?.grossMargin ?? null"
        :ebitda-margin="financial?.ebitdaMargin ?? null"
        :net-margin="financial?.netMargin ?? null"
        :debt-to-equity="financial?.debtToEquity ?? null"
        :free-cash-flow="financial?.freeCashFlow ?? null"
      />

      <!-- Valuation (public companies only) -->
      <FinancialValuation
        v-if="isPublicCompany"
        :market-cap="financial?.marketCap ?? null"
        :enterprise-value="financial?.enterpriseValue ?? null"
        :pe-ratio="financial?.peRatio ?? null"
        :ev-ebitda="financial?.evEbitda ?? null"
        :ev-revenue="financial?.evRevenue ?? null"
      />

      <!-- Funding (private companies only) -->
      <FinancialFunding
        v-if="isPrivateCompany"
        :total-funding="financial?.totalFunding ?? null"
        :last-valuation="financial?.lastValuation ?? null"
        :funding-rounds="financial?.fundingRounds ?? []"
      />

      <!-- Historical Metrics -->
      <FinancialHistory :metrics="financial?.metrics ?? []" />
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - company.view
</route>

<script lang="ts" setup>
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import ChapseAlert from '@/components/ui/ChapseAlert.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FinancialIdentity from '@/components/company/financial/FinancialIdentity.vue'
import FinancialKeyMetrics from '@/components/company/financial/FinancialKeyMetrics.vue'
import FinancialValuation from '@/components/company/financial/FinancialValuation.vue'
import FinancialFunding from '@/components/company/financial/FinancialFunding.vue'
import FinancialHistory from '@/components/company/financial/FinancialHistory.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { useQuery } from '@pinia/colada'
import type { Ref } from 'vue'
import { computed, inject, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute('/folders/[folderId]/companies/[companyId]/financial')

const companyId = computed(() => route.params.companyId)

const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: tasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const task = computed(() => tasks.value?.find((t) => t.type === 'financial'))

const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
    language: selectedLanguage.value,
  }),
)

const financial = computed(() => company.value?.financial)

const isPublicCompany = computed(
  () => financial.value?.companyType?.value?.toLowerCase() === 'public',
)

const isPrivateCompany = computed(() => {
  const fin = financial.value
  if (!fin) return false
  const type = fin.companyType?.value?.toLowerCase()
  return (
    type === 'private' ||
    (!type && (fin.totalFunding !== null || (fin.fundingRounds?.length ?? 0) > 0))
  )
})

const hasFinancialData = computed(() => {
  const fin = financial.value
  if (!fin) return false
  return (
    fin.insights !== null ||
    fin.revenue !== null ||
    fin.marketCap !== null ||
    fin.totalFunding !== null ||
    (fin.metrics && fin.metrics.length > 0) ||
    (fin.fundingRounds && fin.fundingRounds.length > 0)
  )
})
</script>
