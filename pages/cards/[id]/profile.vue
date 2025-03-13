<template>
  <div class="space-y-6">
    <div>
      <OButton
        type="secondary"
        icon="fa-arrow-left"
        @click="$router.push(`/cards/${companyName}`)"
      >
        Back
      </OButton>
    </div>
    <div class="flex items-center justify-between">
      <div class="flex space-x-4 items-center">
        <OIcon
          icon="fa-building"
          type="secondary"
        ></OIcon>
        <h1 class="text-3xl">Company Profile</h1>
      </div>
      <div class="flex gap-2">
        <OButton
          type="secondary"
          @click="showAiChat = !showAiChat"
          >{{ showAiChat ? 'Hide AI Chat' : 'Ask our AI' }}</OButton
        >
        <Export />
      </div>
    </div>

    <!-- Loading indicator when nothing is available yet -->
    <div class="grid grid-cols-12 gap-2">
      <div
        class="space-y-2"
        :class="{
          'col-span-12': !showAiChat,
          'col-span-7 lg:col-span-9': showAiChat,
        }"
      >
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
      </div>
      <div
        :class="{
          'col-span-0': !showAiChat,
          'col-span-5 lg:col-span-3': showAiChat,
        }"
        v-show="showAiChat"
      >
        <div class="sticky top-20">
          <Chat @hide="showAiChat = false" />
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { Profile } from '#components'
import { OAlert, OButton, OIcon } from '@owlint/feathers-vue'
import { useCompanyStore } from '~/stores/company'
import type { Company } from '~/types.global'

// Set page metadata
useHead({
  title: 'Mint - Company Profile',
  meta: [{ name: 'description', content: 'Company Profile Details' }],
})

const { companyName, hasPropertyBeenUpdated } = useCompanyData()

const company = ref<Partial<Company> | null>(null)

const companyStore = useCompanyStore()

// Set up company data
const hasAnyData = ref(false)
const isCompanyNew = ref(true)

const showAiChat = ref(false)

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
