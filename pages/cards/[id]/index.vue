<template>
  <LayoutsCompanyCard title="Company Dashboard" icon="fa-building" v-if="company">
    <template #actions> </template>

    <template #loading>
      <div class="flex flex-col gap-2">
        <OAlert
          v-if="hasQueriesPending"
          title="Loading company information..."
          icon="fa-info-circle"
          color="blue"
        >
          <template #description>
            <div class="flex flex-col gap-2">
              <div class="text-sm text-gray-600">
                <div class="grid grid-cols-2 gap-2">
                  <div
                    v-for="(state, key) in company?.pendingStates"
                    :key="key"
                    class="flex items-center gap-2"
                  >
                    <i v-if="state.pending" class="fa fa-spinner fa-spin text-blue-500"></i>
                    <i v-else-if="state.error" class="fa fa-exclamation-circle text-red-500"></i>
                    <i v-else class="fa fa-check-circle text-green-500"></i>
                    <span class="capitalize">{{ key }}</span>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </OAlert>
      </div>
    </template>

    <!-- Main content grid -->
    <div class="grid grid-cols-12 gap-6 bg-bg1 p-4 rounded-lg">
      <!-- First column: Company general info -->
      <div class="col-span-12 lg:col-span-4 space-y-4 w-full card bg-bg3">
        <div class="flex flex-col gap-4">
          <div class="flex flex-col items-center text-center gap-2">
            <div class="bg-primary w-20 h-20 rounded-full flex items-center justify-center">
              <i class="fa fa-building text-4xl text-white"></i>
            </div>
            <div>
              <h2 class="text-2xl font-bold">
                {{ company?.name }}
              </h2>
            </div>
          </div>

          <div>
            <p class="text-gray-600">
              {{ getSourcedValue(company?.profile?.catchphrase) }}
            </p>
          </div>

          <div class="flex flex-col gap-2">
            <div class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2">
              <i class="fa fa-link"></i>
              <p></p>
              <a
                :href="company?.website"
                target="_blank"
                class="text-secondary hover:underline hover:text-primary"
              >
                {{ company?.website }}
              </a>
            </div>
            <div class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2">
              <i class="fa fa-map-marker"></i>
              <p></p>
              <span class="text-secondary">
                {{ getSourcedValue(company?.profile?.hq) || 'Unknown' }}
              </span>
            </div>

            <div class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2">
              <i class="fa fa-user-tie"></i>
              <p>CEO</p>
              <span class="text-secondary">
                {{ ceo || 'Unknown' }}
              </span>
            </div>

            <div class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2">
              <i class="fa fa-map-marker"></i>
              <p>Revenue</p>
              <span class="text-secondary">
                {{ getSourcedValue(company?.profile?.revenue) || 'Unknown' }}
              </span>
            </div>

            <div class="flex space-x-3 self-center mt-2">
              <a
                v-for="platform in company?.digital?.socialMedia"
                :key="platform.name"
                :href="getSourcedValue(platform.url)"
                target="_blank"
                class="text-primary hover:text-primary-dark"
              >
                <i class="fa" :class="getSocialIcon(platform.name)"></i>
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
          :to="`/cards/${company.name}/profile`"
          :loading="
            company.pendingStates?.profile?.pending || company.pendingStates?.digital?.pending
          "
        />

        <InfoCard
          title="Activities & Events"
          description="Explore company events, trade shows, and key activities."
          icon="fa-calendar-days"
          :to="`/cards/${company.name}/activities`"
          :loading="company.pendingStates?.timeline?.pending"
        />

        <InfoCard
          disabled
          title="Corporate Communications"
          description="Press releases, public statements, and official communications."
          icon="fa-bullhorn"
          :to="`/cards/${company.name}/communications`"
          :loading="company.pendingStates?.communications?.pending"
        />

        <InfoCard
          title="Products"
          description="Browse the company's products, services, and offerings."
          icon="fa-box"
          :to="`/cards/${company.name}/products`"
          :loading="company.pendingStates?.products?.pending"
        />

        <InfoCard
          disabled
          title="Financials"
          description="Financial data, revenue information, and market performance."
          icon="fa-chart-line"
          :to="`/cards/${company.name}/financials`"
          :loading="company.pendingStates?.financials?.pending"
        />

        <InfoCard
          disabled
          title="Mentions"
          description="News articles, media coverage, and third-party mentions."
          icon="fa-quote-left"
          :to="`/cards/${company.name}/mentions`"
          :loading="company.pendingStates?.mentions?.pending"
        />

        <InfoCard
          title="Team & Management"
          description="Leadership team, organizational structure, and key personnel."
          icon="fa-users"
          :to="`/cards/${company.name}/team`"
          :loading="company.pendingStates?.team?.pending"
        />

        <InfoCard
          title="Job Offers"
          description="Current job openings, career opportunities, and hiring information."
          icon="fa-briefcase"
          :to="`/cards/${company.name}/jobs`"
          :loading="company.pendingStates?.jobs?.pending"
        />
      </div>
    </div>

    <!-- AI Chat sidebar -->
    <div v-if="showAiChat" class="fixed right-4 top-24 w-96 z-10">
      <div class="bg-white rounded-lg shadow-lg">
        <Chat @hide="showAiChat = false" />
      </div>
    </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { OAlert } from '@owlint/feathers-vue'

// Set page metadata
useHead({
  title: 'Mint - Company Dashboard',
  meta: [{ name: 'description', content: 'Company Information Dashboard' }]
})

const { company, getSourcedValue } = useCompanyData()

const showAiChat = ref(false)

const ceo = computed(() => {
  if (!company.value?.team) return 'Unknown'
  return company.value?.team[0]?.firstName + ' ' + company.value?.team[0]?.lastName
})

const hasQueriesPending = computed(() => {
  return (
    company.value?.pendingStates?.profile?.pending ||
    company.value?.pendingStates?.digital?.pending ||
    company.value?.pendingStates?.timeline?.pending ||
    company.value?.pendingStates?.products?.pending ||
    company.value?.pendingStates?.team?.pending ||
    company.value?.pendingStates?.jobs?.pending
  )
})

onMounted(() => {
  if (!company.value) {
    navigateTo('/cards')
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
    tiktok: 'fab fa-tiktok',
    pinterest: 'fa-pinterest',
    snapchat: 'fa-snapchat',
    telegram: 'fa-telegram',
    whatsapp: 'fa-whatsapp',
    discord: 'fa-discord',
    reddit: 'fa-reddit',
    twitch: 'fa-twitch',
    github: 'fa-github',
    gitlab: 'fa-gitlab',
    bitbucket: 'fa-bitbucket'
  }

  return iconMap[platform.toLowerCase()] || 'fa-globe'
}
</script>
