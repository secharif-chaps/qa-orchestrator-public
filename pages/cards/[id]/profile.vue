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
        v-if="company?.pendingStates?.profile?.error || company?.pendingStates?.digital?.error"
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

const { company } = useCompanyData()

const profilePending = computed(() => {
  return (
    company.value?.pendingStates?.profile?.pending ||
    company.value?.pendingStates?.digital?.pending ||
    company.value?.pendingStates?.press?.pending ||
    company.value?.pendingStates?.csr?.pending
  )
})

const companyStore = useCompanyStore()

const refreshCompany = () => {
  if (company.value?.name && company.value?.website) {
    companyStore.startQuery(company.value.name, company.value.website, 'profile')
    companyStore.startQuery(company.value.name, company.value.website, 'digital')
    companyStore.startQuery(company.value.name, company.value.website, 'press')
    companyStore.startQuery(company.value.name, company.value.website, 'csr')
  }
}

const router = useRouter()

onMounted(() => {
  if (!company.value) {
    router.push('/cards')
  }
})
</script>
