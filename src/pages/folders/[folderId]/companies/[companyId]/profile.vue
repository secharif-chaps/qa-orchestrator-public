<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State - Show if ANY profile tasks are loading -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />

    <!-- Error State - Show if ALL profile tasks failed -->
    <SectionErrorState
      v-else-if="company && task?.status === 'error'"
      :error-message="task.error"
      :task="task"
    />

    <!-- No Data State - Show if all tasks completed but no data -->
    <NoData v-else-if="task?.status === 'succeeded' && !hasAnyProfileData">
      <p class="text-secondary text-lg font-medium">
        {{ $t('profile.sections.profile.noData', 'No profile data available for this company') }}
      </p>
    </NoData>

    <!-- Partial Data Layout - Show profile sections as they become available -->
    <div v-else class="grid grid-cols-12 gap-4">
      <div class="col-span-9">
        <ProfileHeader />
      </div>

      <div class="col-span-3">
        <div class="flex flex-col h-full gap-2">
          <ProfileGroup />
          <ProfileBusinessLine />
        </div>
      </div>

      <div class="col-span-12 grid grid-cols-3 gap-4">
        <ProfileEstablishment />
        <ProfileEmployees />
        <ProfileRevenue />
      </div>

      <div class="col-span-12 space-y-2 flex flex-col">
        <ProfileProducts />
        <!-- <ProfileTarget /> -->
        <ProfileStrategy />
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
import { computed, ref, inject } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import NoData from '@/components/ui/NoData.vue'
import ProfileHeader from '@/components/company/profile/ProfileHeader.vue'
import ProfileGroup from '@/components/company/profile/ProfileGroup.vue'
import ProfileBusinessLine from '@/components/company/profile/ProfileBusinessLine.vue'
import ProfileProducts from '@/components/company/profile/ProfileProducts.vue'
import ProfileEstablishment from '@/components/company/profile/ProfileEstablishment.vue'
import ProfileEmployees from '@/components/company/profile/ProfileEmployees.vue'
import ProfileRevenue from '@/components/company/profile/ProfileRevenue.vue'
import ProfileStrategy from '@/components/company/profile/ProfileStrategy.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: tasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

const task = computed(() => tasks.value?.find((t) => t.type === 'profile'))

const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: company } = useQuery(
  companyByIdQuery,
  () => ({
    id: companyId.value,
    language: selectedLanguage.value,
  }),
  // Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
  // No polling needed - cache is invalidated automatically when tasks update.
)

// Check if we have any profile data to show
const hasAnyProfileData = computed(() => {
  const comp = company.value
  if (!comp) return false

  // Check if any profile-related data exists (excluding CSR as it has its own section)
  return !!(comp.profile || comp.digital || comp.establishment || comp.employees || comp.revenue)
})
</script>
