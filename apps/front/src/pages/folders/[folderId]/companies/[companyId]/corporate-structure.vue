<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="
        isLoadingTasks ||
        isLoadingCompany ||
        (company && (task?.status === 'pending' || task?.status === 'running'))
      "
    />

    <!-- Error State -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State -->
    <EmptyState
      v-else-if="!hasCorporateStructureData"
      :title="$t('screen.profile.sections.corporateStructure.noData')"
    />

    <!-- Main Content -->
    <div v-else class="flex flex-col gap-6">
      <!-- Section Header -->
      <h3 class="text-neutral-black-font flex items-center gap-2 font-bold">
        <Icon icon="fa-sitemap" />
        <span>{{ $t('screen.profile.sections.corporateStructure.title') }}</span>
      </h3>

      <!-- Corporate Structure Grid -->
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <CorporateEntityGroup
          icon="fa-building"
          :title="$t('screen.profile.sections.corporateStructure.parents')"
          :entities="corporateStructure?.parents"
        />
        <CorporateEntityGroup
          icon="fa-diagram-project"
          :title="$t('screen.profile.sections.corporateStructure.subsidiaries')"
          :entities="corporateStructure?.subsidiaries"
        />
        <CorporateEntityGroup
          icon="fa-handshake"
          :title="$t('screen.profile.sections.corporateStructure.affiliates')"
          :entities="corporateStructure?.affiliates"
        />
        <CorporateEntityGroup
          icon="fa-location-dot"
          :title="$t('screen.profile.sections.corporateStructure.branches')"
          :entities="corporateStructure?.branches"
        />
        <CorporateEntityGroup
          icon="fa-globe"
          :title="$t('screen.profile.sections.corporateStructure.regionalEntities')"
          :entities="corporateStructure?.regional_entities"
        />
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - company.view
</route>

<script lang="ts" setup>
import CorporateEntityGroup from '@/components/company/CorporateEntityGroup.vue'
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { Icon } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import type { Ref } from 'vue'
import { computed, inject, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute('/folders/[folderId]/companies/[companyId]/corporate-structure')

const companyId = computed(() => route.params.companyId)

const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: tasks, isLoading: isLoadingTasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const task = computed(() => tasks.value?.find((t) => t.type === 'corporate_structure'))

const { data: company, isLoading: isLoadingCompany } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
    language: selectedLanguage.value,
  }),
)

const corporateStructure = computed(() => company.value?.corporate_structure)

const hasCorporateStructureData = computed(() => {
  const cs = corporateStructure.value
  if (!cs) return false

  return !!(
    (cs.parents && cs.parents.length > 0) ||
    (cs.subsidiaries && cs.subsidiaries.length > 0) ||
    (cs.affiliates && cs.affiliates.length > 0) ||
    (cs.branches && cs.branches.length > 0) ||
    (cs.regional_entities && cs.regional_entities.length > 0)
  )
})
</script>
