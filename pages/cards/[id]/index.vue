<template>
  <LayoutsCompanyCard
    title="Company Dashboard"
    icon="fa-building"
    :loading="!hasAnyData"
  >
    <template #actions>
      <Export />
    </template>

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
    </template>

    <!-- Main content grid -->
    <div class="grid grid-cols-12 gap-6 bg-bg1 p-4 rounded-lg">
      <!-- First column: Company general info -->
      <div class="col-span-12 lg:col-span-4 space-y-4 w-full card bg-bg3">
        <div class="flex flex-col gap-4">
          <div class="flex flex-col items-center text-center gap-2">
            <div
              class="bg-primary w-20 h-20 rounded-full flex items-center justify-center"
            >
              <i class="fa fa-building text-4xl text-white"></i>
            </div>
            <div>
              <h2 class="text-2xl font-bold">
                {{ getSourcedValue(company?.profile?.name) || companyName }}
              </h2>
            </div>
          </div>

          <div>
            <p
              v-if="hasPropertyBeenUpdated('profile.catchphrase')"
              class="text-gray-600"
            >
              {{ getSourcedValue(company?.profile?.catchphrase) }}
            </p>
            <p
              v-else
              class="text-gray-400 italic"
            >
              Loading company information...
            </p>
          </div>

          <div class="flex flex-col gap-2">
            <div class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2">
              <i class="fa fa-link"></i>
              <p>Website</p>
              <a
                :href="getSourcedValue(company?.profile?.website)"
                target="_blank"
                class="text-secondary hover:underline hover:text-primary"
              >
                {{ getSourcedValue(company?.profile?.website) }}
              </a>
            </div>
            <div class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2">
              <i class="fa fa-map-marker"></i>
              <p>HQ</p>
              <span class="text-secondary">
                {{ 'Unknown' }}
              </span>
            </div>

            <div class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2">
              <i class="fa fa-user-tie"></i>
              <p>CEO</p>
              <span class="text-secondary">
                {{ 'Unknown' }}
              </span>
            </div>

            <div class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2">
              <i class="fa fa-map-marker"></i>
              <p>Revenue</p>
              <span class="text-secondary">
                {{ getSourcedValue(company?.profile?.revenue) || 'Unknown' }}
              </span>
            </div>

            <div
              v-if="hasPropertyBeenUpdated('social_media')"
              class="flex space-x-3 self-center mt-2"
            >
              <a
                v-for="platform in company?.social_media"
                :key="platform.name"
                :href="getSourcedValue(platform.url)"
                target="_blank"
                class="text-primary hover:text-primary-dark"
              >
                <i
                  class="fa"
                  :class="getSocialIcon(platform.name)"
                ></i>
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Second column: First set of info cards -->
      <div class="col-span-12 lg:col-span-8 gap-4 grid grid-cols-2">
        <InfoCard
          title="Company Profile"
          description="View detailed company information, business lines, and key metrics."
          icon="fa-building"
          :to="`/cards/${companyName}/profile`"
          :loading="isProfileLoading"
        />

        <InfoCard
          title="Activities & Events"
          description="Explore company events, trade shows, and key activities."
          icon="fa-calendar-days"
          :to="`/cards/${companyName}/activities`"
          :loading="isTimelineLoading"
        />

        <InfoCard
          disabled
          title="Corporate Communications"
          description="Press releases, public statements, and official communications."
          icon="fa-bullhorn"
          :to="`/cards/${companyName}/communications`"
        />

        <InfoCard
          title="Products"
          description="Browse the company's products, services, and offerings."
          icon="fa-box"
          :to="`/cards/${companyName}/products`"
          :loading="isProductsLoading"
        />

        <InfoCard
          disabled
          title="Financials"
          description="Financial data, revenue information, and market performance."
          icon="fa-chart-line"
          :to="`/cards/${companyName}/financials`"
        />

        <InfoCard
          disabled
          title="Mentions"
          description="News articles, media coverage, and third-party mentions."
          icon="fa-quote-left"
          :to="`/cards/${companyName}/mentions`"
        />

        <InfoCard
          disabled
          title="Team & Management"
          description="Leadership team, organizational structure, and key personnel."
          icon="fa-users"
          :to="`/cards/${companyName}/team`"
        />

        <InfoCard
          disabled
          title="Job Offers"
          description="Current job openings, career opportunities, and hiring information."
          icon="fa-briefcase"
          :to="`/cards/${companyName}/jobs`"
        />
      </div>
    </div>

    <!-- AI Chat sidebar -->
    <div
      v-if="showAiChat"
      class="fixed right-4 top-24 w-96 z-10"
    >
      <div class="bg-white rounded-lg shadow-lg">
        <Chat @hide="showAiChat = false" />
      </div>
    </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { OAlert } from '@owlint/feathers-vue'
import { useCompanyStore } from '~/stores/company'
import type { Company } from '~/types.global'

// Set page metadata
useHead({
  title: 'Mint - Company Dashboard',
  meta: [{ name: 'description', content: 'Company Information Dashboard' }],
})

const { companyName, hasPropertyBeenUpdated, getSourcedValue } =
  useCompanyData()
const { pending, timelinePending, productsPending } = useAgent()

const company = ref<Partial<Company> | null>(null)

const companyStore = useCompanyStore()

// Set up company data
const hasAnyData = ref(false)
const isCompanyNew = ref(true)

const showAiChat = ref(false)

// Track loading states for different sections
const isProfileLoading = computed(() => {
  // Check if the agent is pending OR if we don't have profile data yet
  return (
    pending.value || (!hasAnyData.value && !hasPropertyBeenUpdated('profile'))
  )
})

const isTimelineLoading = computed(() => {
  // Check if we don't have timeline data yet
  return timelinePending.value || !hasPropertyBeenUpdated('timeline_events')
})

const isProductsLoading = computed(() => {
  // Check if we don't have products data yet
  return productsPending.value || !hasPropertyBeenUpdated('products')
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

// Helper function to get social media icon
const getSocialIcon = (platform: string) => {
  const iconMap: Record<string, string> = {
    facebook: 'fa-facebook',
    twitter: 'fa-twitter',
    instagram: 'fa-instagram',
    linkedin: 'fa-linkedin',
    youtube: 'fa-youtube',
    tiktok: 'fa-tiktok',
    pinterest: 'fa-pinterest',
    snapchat: 'fa-snapchat',
    telegram: 'fa-telegram',
    whatsapp: 'fa-whatsapp',
    discord: 'fa-discord',
    reddit: 'fa-reddit',
    twitch: 'fa-twitch',
    github: 'fa-github',
    gitlab: 'fa-gitlab',
    bitbucket: 'fa-bitbucket',
  }

  return iconMap[platform.toLowerCase()] || 'fa-globe'
}
</script>
