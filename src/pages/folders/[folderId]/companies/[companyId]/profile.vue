<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State - Show if ANY profile tasks are loading -->
    <PageState 
      v-if="taskState.isLoading.value" 
      state="loading" 
      page-type="profile"
      :task-progress="taskState.taskProgress.value"
    />
    
    <!-- Error State - Show if ALL profile tasks failed -->
    <PageState
      v-else-if="taskState.hasErrors.value && !taskState.isComplete.value && !hasAnyProfileData"
      state="error"
      page-type="profile"
      :error-message="taskState.errorMessages.value[0]"
      @retry="handleRetry"
    />
    
    <!-- No Data State - Show if all tasks completed but no data -->
    <PageState 
      v-else-if="taskState.isComplete.value && !hasAnyProfileData" 
      state="no-data" 
      page-type="profile"
    />

    <!-- Partial Data Layout - Show profile sections as they become available -->
    <div v-else class="grid grid-cols-12 gap-2">
      <div class="col-span-9">
        <ProfileHeader />
      </div>

      <div class="col-span-3">
        <div class="flex flex-col h-full gap-2">
          <ProfileGroup />
          <ProfileBusinessLine />
        </div>
      </div>

      <div class="col-span-12 grid grid-cols-3 gap-2">
        <ProfileEstablishment />
        <ProfileEmployees />
        <ProfileRevenue />
      </div>

      <div class="col-span-12 space-y-2 flex flex-col">
        <ProfileProducts />
        <!-- <ProfileTarget /> -->
        <ProfileCSR />

        <div class="flex flex-col h-full gap-2">
          <ProfileStrategy />
          <ProfileNews />
        </div>
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
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useTaskState } from '@/composables/useTaskState'
import PageState from '@/components/company/PageState.vue'
import ProfileHeader from '@/components/company/profile/ProfileHeader.vue'
import ProfileGroup from '@/components/company/profile/ProfileGroup.vue'
import ProfileBusinessLine from '@/components/company/profile/ProfileBusinessLine.vue'
import ProfileProducts from '@/components/company/profile/ProfileProducts.vue'
import ProfileCSR from '@/components/company/profile/ProfileCSR.vue'
import ProfileEstablishment from '@/components/company/profile/ProfileEstablishment.vue'
import ProfileEmployees from '@/components/company/profile/ProfileEmployees.vue'
import ProfileRevenue from '@/components/company/profile/ProfileRevenue.vue'
import ProfileStrategy from '@/components/company/profile/ProfileStrategy.vue'
import ProfileNews from '@/components/company/profile/ProfileNews.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))

// Task state management for profile-related tasks
const taskState = useTaskState(company, ['profile', 'digital', 'csr'])

// Handle retry action
const handleRetry = () => {
  // TODO: Implement retry logic - trigger profile tasks restart
  console.log('Retrying profile data fetch...')
}

// Check if we have any profile data to show
const hasAnyProfileData = computed(() => {
  const comp = company.value
  if (!comp) return false
  
  // Check if any profile-related data exists
  return !!(comp.profile || comp.digital || comp.csr || comp.establishment || comp.employees || comp.revenue)
})
</script>
