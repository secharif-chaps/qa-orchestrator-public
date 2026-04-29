<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />

    <!-- Error State -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State -->
    <EmptyState v-else-if="!hasCsrData" :title="$t('screen.profile.sections.csr.noData')" />

    <!-- Main Content -->
    <div v-else class="flex flex-col gap-6">
      <!-- AI-Generated CSR Insights -->
      <ChapseAlert
        v-if="company?.csr?.insights"
        variant="mage"
        :title="$t('screen.profile.sections.csr.insightsTitle')"
      >
        {{ company.csr.insights }}
      </ChapseAlert>

      <!-- Engagements & Initiatives Card -->
      <SectionCard>
        <!-- Header -->
        <template #header>
          <div class="flex items-center justify-between">
            <SectionTitle :title="$t('screen.profile.sections.csr.engagements')" />
            <Searchbar
              id="csr-search"
              v-model="searchQuery"
              :placeholder="$t('screen.profile.sections.csr.searchPlaceholder')"
              class="w-64"
            />
          </div>
        </template>

        <!-- Items list -->
        <div class="gap-2xs flex flex-col">
          <CsrItem
            v-for="(item, index) in filteredItems"
            :key="`${item.category}-${index}`"
            :item
          />
          <p v-if="filteredItems.length === 0" class="text-neutral-black-font text-sm italic">
            {{ $t('common.notFound') }}
          </p>
        </div>
      </SectionCard>
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
import CsrItem from '@/components/company/csr/CsrItem.vue'
import type { CsrFlatItem } from '@/components/company/csr/types'
import { getSourcedSource, getSourcedValue } from '@/components/helpers/sourcedValues'
import ChapseAlert from '@/components/ui/ChapseAlert.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import SectionTitle from '@/components/ui/SectionTitle.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import type { SourcedValue } from '@/types/company'
import { Searchbar } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import type { Ref } from 'vue'
import { computed, inject, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute('/folders/[folderId]/companies/[companyId]/csr')

const companyId = computed(() => route.params.companyId)

// Inject selected language from parent [companyId].vue
const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: tasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const task = computed(() => tasks.value?.find((t) => t.type === 'csr'))

// Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
// No polling needed - cache is invalidated automatically when tasks update.
const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
    language: selectedLanguage.value,
  }),
)

const searchQuery = ref('')

const allItems = computed((): CsrFlatItem[] => {
  const csr = company.value?.csr
  if (!csr) return []

  const items: CsrFlatItem[] = []

  // Single responsibility field (string or SourcedValue)
  if (csr.responsibility) {
    const value = getSourcedValue(csr.responsibility)
    const source =
      typeof csr.responsibility !== 'string'
        ? getSourcedSource(csr.responsibility as SourcedValue<string>)
        : undefined
    if (value) items.push({ value, source, category: 'responsibility' })
  }

  // Array fields
  const arrays: Array<{ data?: SourcedValue<string>[]; category: string }> = [
    { data: csr.responsibility_initiatives, category: 'responsibility_initiatives' },
    { data: csr.charity_actions, category: 'charity' },
    { data: csr.sustainability_programs, category: 'sustainability' },
    { data: csr.community_involvement, category: 'community' },
    { data: csr.diversity_inclusion, category: 'diversity' },
    { data: csr.ethical_practices, category: 'ethics' },
    { data: csr.awards_certifications, category: 'awards' },
  ]

  for (const { data, category } of arrays) {
    if (!data) continue
    for (const item of data) {
      const value = getSourcedValue(item)
      if (value) items.push({ value, source: item.source, category })
    }
  }

  return items
})

const filteredItems = computed(() => {
  if (!searchQuery.value.trim()) return allItems.value
  const q = searchQuery.value.toLowerCase()
  return allItems.value.filter((item) => item.value.toLowerCase().includes(q))
})

const hasCsrData = computed(() => {
  const csr = company.value?.csr
  if (!csr) return false

  return !!(
    csr.insights ||
    csr.responsibility ||
    (csr.awards_certifications && csr.awards_certifications.length > 0) ||
    (csr.charity_actions && csr.charity_actions.length > 0) ||
    (csr.community_involvement && csr.community_involvement.length > 0) ||
    (csr.diversity_inclusion && csr.diversity_inclusion.length > 0) ||
    (csr.ethical_practices && csr.ethical_practices.length > 0) ||
    (csr.responsibility_initiatives && csr.responsibility_initiatives.length > 0) ||
    (csr.sustainability_programs && csr.sustainability_programs.length > 0)
  )
})
</script>
