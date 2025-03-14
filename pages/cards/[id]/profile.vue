<template>
  <LayoutsCompanyCard
    title="Company Profile"
    icon="fa-building"
    :loading="isProfileLoading"
  >
    <template #actions> </template>

    <template #loading>
      <OAlert
        v-if="!hasAnyData"
        message="Loading company information..."
        title="Please wait"
        icon="fa-spinner fa-spin"
        color="blue"
      >
        <p>Fetching data from AI agent...</p>
      </OAlert>
      <!-- Insights section (only show if we have insights) -->
      <OAlert
        v-if="hasPropertyBeenUpdated('insights')"
        message="This company is a member of the Sephora group"
        title="Insights"
        icon="fa-wand-magic-sparkles"
        :description="company?.insights?.value"
      >
      </OAlert>
      <OAlert
        v-else-if="!isCompanyNew"
        message="Loading insights..."
        title="Insights"
        icon="fa-spinner-third animate-spin"
        color="blue"
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

      <div
        class="@max-6xl:col-span-6 @min-6xl:col-span-3 space-y-2 flex flex-col"
      >
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
import { OAlert } from '@owlint/feathers-vue'
import { useCompanyStore } from '~/stores/company'
import type { Company } from '~/types.global'

// Set page metadata
useHead({
  title: 'Mint - Company Profile',
  meta: [{ name: 'description', content: 'Company Profile Details' }],
})

const { companyName, hasPropertyBeenUpdated } = useCompanyData()
const { pending } = useAgent()

const company = ref<Partial<Company> | null>(null)

const companyStore = useCompanyStore()

// Set up company data
const hasAnyData = ref(false)
const isCompanyNew = ref(true)

// Track loading states for different sections
const isProfileLoading = computed(() => {
  return (
    pending.value || (!hasAnyData.value && !hasPropertyBeenUpdated('profile'))
  )
})

// Subscribe to company store for updates
watch(
  () => companyStore.getCompanyByName(companyName.value),
  (newCompany) => {
    if (newCompany) {
      company.value = newCompany
      hasAnyData.value = true
      isCompanyNew.value = false
    }
  },
  { immediate: true, deep: true }
)

// Initialize the company in the store if it doesn't exist yet
onMounted(() => {
  if (!companyStore.getCompanyByName(companyName.value)) {
    companyStore.initCompany(companyName.value)
    isCompanyNew.value = true
  } else {
    company.value = companyStore.getCompanyByName(companyName.value)
    hasAnyData.value = true
    isCompanyNew.value = false
  }
})
</script>
