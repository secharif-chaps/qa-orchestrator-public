<template>
  <LayoutsCompanyCard
    title="Company Profile"
    icon="fa-building"
    :loading="profilePending"
  >
    <template #actions>
      <OButton

        @click="retriggerProfileSearch"
        :loading="profilePending"
        icon="fa-refresh"
        type="secondary"
      >
        Refresh Profile
      </OButton>
    </template>

    <template #loading>
      <OAlert
        v-if="!hasAnyData"
        message="Loading company information..."
        title="Oops"
        icon="fa-bug"
        color="blue"
        description="Something went wrong while fetching the company profile. Please try again."
      >
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
import { OAlert, OButton } from '@owlint/feathers-vue'
import { useCompanyStore } from '~/stores/company'
import type { Company } from '~/types.global'
import { useAgentStore } from '~/stores/agent'

// Set page metadata
useHead({
  title: 'Mint - Company Profile',
  meta: [{ name: 'description', content: 'Company Profile Details' }],
})

const { companyName, hasPropertyBeenUpdated, getSourcedValue } = useCompanyData()
const { findProfile } = useAgent()
const agentStore = useAgentStore()
const profilePending = computed(() => agentStore.getPendingState('profile'))
const companyStore = useCompanyStore()

const company = ref<Partial<Company> | null>(null)

// Set up company data
const hasAnyData = ref(false)
const isCompanyNew = ref(true)

const retriggerProfileSearch = async () => {
  if (companyName.value) {
    // Trigger the profile search
    await findProfile(companyName.value, company.value?.profile?.website?.value)
  }
}
</script>
