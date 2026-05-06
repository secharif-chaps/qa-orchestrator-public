<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State - Show if ANY profile tasks are loading -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />

    <!-- Error State - Show if ALL profile tasks failed -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State - Show if all tasks completed but no data -->
    <EmptyState
      v-else-if="task?.status === 'succeeded' && !hasAnyProfileData"
      :title="$t('screen.profile.sections.profile.noData')"
    />

    <!-- Partial Data Layout - Show profile sections as they become available -->
    <div v-else class="grid grid-cols-12 gap-4">
      <div class="col-span-9">
        <ProfileHeader />
      </div>

      <div class="col-span-3">
        <div class="flex h-full flex-col gap-2">
          <ProfileGroup />
          <ProfileBusinessLine />
        </div>
      </div>

      <div class="col-span-12 grid grid-cols-3 gap-4">
        <ProfileEstablishment />
        <ProfileEmployees />
        <ProfileRevenue />
      </div>

      <!-- Tab Section -->
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - organization.read
</route>

<script lang="ts" setup>
import ProfileBusinessLine from '@/components/company/profile/ProfileBusinessLine.vue'
import ProfileEmployees from '@/components/company/profile/ProfileEmployees.vue'
import ProfileEstablishment from '@/components/company/profile/ProfileEstablishment.vue'
import ProfileGroup from '@/components/company/profile/ProfileGroup.vue'
import ProfileHeader from '@/components/company/profile/ProfileHeader.vue'
import ProfileRevenue from '@/components/company/profile/ProfileRevenue.vue'

import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { useQuery } from '@pinia/colada'
import { computed, inject, ref, type Ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute('/folders/[folderId]/companies/[companyId]/profile')

const companyId = computed(() => route.params.companyId)

const { data: tasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const task = computed(() => tasks.value?.find((t) => t.type === 'profile'))

const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: company } = useQuery(
  // Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
  // No polling needed - cache is invalidated automatically when tasks update.
  () =>
    companyByIdQuery({
      id: companyId.value,
      language: selectedLanguage.value,
    }),
)

// Check if we have any profile data to show
const hasAnyProfileData = computed(() => {
  const comp = company.value
  if (!comp) return false

  // Check if any profile-related data exists (excluding CSR as it has its own section)
  // Profile contains nested fields like establishmentYear, employeeCount, revenue
  return !!(comp.profile || comp.digital)
})
</script>
