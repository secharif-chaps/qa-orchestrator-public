<template>
  <div class="flex flex-col gap-6">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />
    <!-- Error State -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State -->
    <NoData v-else-if="!hasTeamData">
      <p class="text-neutral-black-font text-lg font-medium">
        {{ $t('screen.profile.sections.team.noData') }}
      </p>
    </NoData>

    <!-- Main content -->
    <div v-else-if="hasTeamData" class="flex flex-col gap-6">
      <!-- Team Header with Stats -->
      <TeamPageHeader :team="company?.team ?? []" />

      <!-- Team Members List -->
      <TeamMembersList :team="company?.team ?? []" @view-in-hierarchy="scrollToMemberInHierarchy" />

      <!-- Hierarchy Graph -->
      <TeamHierarchyGraph ref="hierarchyGraph" :team="company?.team ?? []" />
    </div>
  </div>
</template>

<script lang="ts" setup>
import type { Ref } from 'vue'
import { computed, inject, ref, useTemplateRef } from 'vue'

import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import TeamHierarchyGraph from '@/components/company/team/TeamHierarchyGraph.vue'
import TeamMembersList from '@/components/company/team/TeamMembersList.vue'
import TeamPageHeader from '@/components/company/team/TeamPageHeader.vue'
import NoData from '@/components/ui/NoData.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import type { TeamMember } from '@/types/company'
import { useQuery } from '@pinia/colada'
import { useRoute } from 'vue-router'

const route = useRoute('/folders/[folderId]/companies/[companyId]/team')

const companyId = computed(() => route.params.companyId)

// Inject selected language from parent [companyId].vue
const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: tasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const task = computed(() => tasks.value?.find((t) => t.type === 'team'))

// Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
// No polling needed - cache is invalidated automatically when tasks update.
const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
    language: selectedLanguage.value,
  }),
)

const hierarchyGraphRef = useTemplateRef('hierarchyGraph')

const scrollToMemberInHierarchy = (member: TeamMember) => {
  hierarchyGraphRef.value?.focusMember(member)
}

const hasTeamData = computed(() => {
  const teamData = company.value?.team
  if (!teamData) return false
  return !!(teamData && Array.isArray(teamData) && teamData.length > 0)
})
</script>
