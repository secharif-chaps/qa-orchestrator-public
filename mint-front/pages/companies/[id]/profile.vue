<template>
  <LayoutsCompanyCard
    title="Company Profile"
    icon="fa-building"
    v-if="company"
    :loading="profilePending"
  >
  <div class="flex flex-col gap-4">
    <!-- Task state -->
    <TaskState
      v-if="companyId"
      :company-id="companyId"
      :required-task-types="['profile', 'digital', 'products', 'csr', 'press']"
      loading-title="Loading company profile..."
      loading-description="Fetching comprehensive company information..."
    />

    <div class="@container grid grid-cols-6 gap-2">
      <div class="@max-6xl:col-span-6 @min-6xl:col-span-4">
        <Profile />
      </div>

      <div class="@max-6xl:col-span-6 @min-6xl:col-span-2 space-y-2">
        <div class="flex flex-col h-full gap-2">
          <ProfileGroup />
          <ProfileBusinessLine />
        </div>
      </div>

      <div class="@max-6xl:col-span-6 @min-6xl:col-span-3 space-y-2 flex flex-col">
        <!-- Products and services section -->
        <ProfileProducts />

        <!-- Target audience section -->
        <ProfileTarget />

        <!-- CSR section -->
        <ProfileCSR />
      </div>
      <div
        class="@max-6xl:col-span-6 @min-6xl:col-span-3 col-span-6 lg:col-span-3 flex flex-col space-y-2"
      >
        <div class="grid grid-cols-3 gap-2">
          <!-- Key metrics - each can load independently -->
          <ProfileEstablishment />
          <ProfileEmployees />
          <ProfileRevenue />
        </div>
        <div class="flex flex-col h-full gap-2">
          <!-- Digital strategy section -->
          <ProfileStrategy />

          <!-- Recent news section -->
          <ProfileNews />
        </div>
      </div>
    </div>
  </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { Profile } from '#components'
import TaskState from '~/components/TaskState.vue'

// Set page metadata
useHead({
  title: 'Mint - Company Profile',
  meta: [{ name: 'description', content: 'Company Profile Details' }]
})

const { company, companyId, fetchCompany } = useCompanyData()

const profilePending = computed(() => {
  if (!companyId.value) {
    return false
  }
  return company.value?.tasks?.some(
    (task: { type: string; status: string }) => (task.type === 'profile' || task.type === 'digital' || task.type === 'press' || task.type === 'csr') &&
           (task.status === 'pending' || task.status === 'running')
  )
})

const router = useRouter()

onMounted(async () => {
  await fetchCompany()
  if (!companyId.value) {
    router.push('/companies')
  }
})
</script>
