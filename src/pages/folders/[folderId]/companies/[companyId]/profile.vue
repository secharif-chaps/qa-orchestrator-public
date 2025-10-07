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
import ProfileEstablishment from '@/components/company/profile/ProfileEstablishment.vue'
import ProfileEmployees from '@/components/company/profile/ProfileEmployees.vue'
import ProfileRevenue from '@/components/company/profile/ProfileRevenue.vue'
import ProfileStrategy from '@/components/company/profile/ProfileStrategy.vue'
import { useRestartTask } from '@/mutations/tasks'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(
  companyByIdQuery,
  () => ({
    id: companyId.value,
  }),
  {
    // Poll every 5 seconds when any task is running
    refetchInterval: () => {
      const hasRunningTasks = company.value?.tasks?.some(
        (t) => t.status === 'running' || t.status === 'pending'
      )
      return hasRunningTasks ? 5000 : false
    },
  }
)

// Task state management for profile-related tasks
const taskState = useTaskState(company, ['profile', 'digital'])

// Restart task mutation
const { mutate: restartTaskMutation } = useRestartTask()

// Handle retry action - restarts all profile-related tasks
const handleRetry = async () => {
  const profileTasks = company.value?.tasks?.filter((t) =>
    ['profile', 'digital'].includes(t.type)
  )

  if (profileTasks && profileTasks.length > 0) {
    console.log('🔄 Retrying profile tasks:', profileTasks.map(t => t.type))
    for (const task of profileTasks) {
      if (task.status === 'error') {
        try {
          await restartTaskMutation(task.id)
          console.log(`✅ ${task.type} task restarted successfully`)
        } catch (error) {
          console.error(`❌ Error restarting ${task.type} task:`, error)
        }
      }
    }
  } else {
    console.warn('⚠️ No profile tasks found')
  }
}

// Check if we have any profile data to show
const hasAnyProfileData = computed(() => {
  const comp = company.value
  if (!comp) return false

  // Check if any profile-related data exists (excluding CSR as it has its own section)
  return !!(comp.profile || comp.digital || comp.establishment || comp.employees || comp.revenue)
})
</script>
