<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />

    <!-- Error State -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State -->
    <NoData v-else-if="company && !hasFinancialData">
      <p class="text-secondary text-lg font-medium">
        {{ $t('screen.profile.sections.financial.noData') }}
      </p>
    </NoData>

    <!-- Main Content (placeholder — will be implemented in a dedicated story) -->
    <div v-else class="flex flex-col gap-6">
      <ChapseAlert v-if="company?.financial?.insights?.value" variant="mage">
        {{ company.financial.insights.value }}
      </ChapseAlert>
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
import NoData from '@/components/ui/NoData.vue'
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

const hasFinancialData = computed(() => {
  const fin = company.value?.financial
  if (!fin) return false
  return (
    fin.insights !== null ||
    fin.revenue !== null ||
    fin.market_cap !== null ||
    fin.total_funding !== null ||
    (fin.metrics && fin.metrics.length > 0) ||
    (fin.funding_rounds && fin.funding_rounds.length > 0)
  )
})
</script>
