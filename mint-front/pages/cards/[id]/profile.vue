<template>
  <LayoutsCompanyCard
    title="Company Profile"
    icon="fa-building"
    v-if="company"
    :loading="profilePending"
  >
    <template #actions>
      <OButton @click="refreshCompany" type="secondary" icon="fa-refresh">Refresh</OButton>
    </template>

    <template #loading>
      <!-- Insights section (only show if we have insights) -->

      <OAlert
        v-if="profilePending"
        message="Loading insights..."
        title="Insights"
        icon="fa-spinner-third animate-spin"
        color="blue"
      >
      </OAlert>

      <OAlert
        v-if="company?.tasks?.some(task => task.type === 'profile' && task.status === 'error') || 
              company?.tasks?.some(task => task.type === 'digital' && task.status === 'error')"
        title="Oops, something went wrong"
        description="Please try again later or contact support"
        icon="fa-exclamation-triangle"
        color="red"
      >
      </OAlert>
    </template>

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
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { Profile } from '#components'
import { OAlert, OButton } from '@owlint/feathers-vue'
import { useCompanyStore } from '~/stores/company'

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
    task => (task.type === 'profile' || task.type === 'digital' || task.type === 'press' || task.type === 'csr') &&
           (task.status === 'pending' || task.status === 'running')
  )
})

const companyStore = useCompanyStore()

const refreshCompany = () => {
  if (companyId.value) {
    companyStore.startQuery(companyId.value, 'profile')
    companyStore.startQuery(companyId.value, 'digital')
    companyStore.startQuery(companyId.value, 'press')
    companyStore.startQuery(companyId.value, 'csr')
  }
}

const router = useRouter()

onMounted(async () => {
  await fetchCompany()
  if (!companyId.value) {
    router.push('/cards')
  }
})
</script>
