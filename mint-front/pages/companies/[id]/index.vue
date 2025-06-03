<template>
  <LayoutsCompanyCard title="Company Dashboard" icon="fa-building" v-if="company">
    <template #actions> </template>

    <template #loading>
      <div class="flex flex-col gap-2" v-if="company && company.id">
        <Tasks
          v-if="company"
          :company-id="company.id"
        />
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

          <div v-if="company?.profile?.catchphrase">
            <p class="text-gray-600">
              {{ getSourcedValue(company.profile.catchphrase) || company.profile.catchphrase }}
            </p>
          </div>

          <div class="flex flex-col gap-2">
            <div class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2">
              <i class="fa fa-link"></i>
              <p></p>
              <a
                :href="formatWebsiteUrl(company?.website)"
                target="_blank"
                class="text-secondary hover:underline hover:text-primary"
              >
                {{ company?.website }}
              </a>
            </div>
            <div
              class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2"
              v-if="company?.profile?.hq"
            >
              <i class="fa fa-map-marker"></i>
              <p></p>
              <span class="text-secondary">
                {{ getSourcedValue(company.profile.hq) || 'Unknown' }}
              </span>
            </div>

            <div
              class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2"
              v-if="company?.profile?.ceo"
            >
              <i class="fa fa-user-tie"></i>
              <p>CEO</p>
              <span class="text-secondary">
                {{ getSourcedValue(company.profile.ceo) || 'Unknown' }}
              </span>
            </div>

            <div
              class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-2"
              v-if="company?.profile?.revenue"
            >
              <i class="fa fa-money-bill"></i>
              <p>Revenue</p>
              <span class="text-secondary">
                {{ getSourcedValue(company.profile.revenue) || 'Unknown' }}
              </span>
            </div>

            <div class="flex space-x-3 self-center mt-2" v-if="company?.digital?.socialMedia">
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
          v-for="card in infoCards"
          :key="card.title"
          :title="card.title"
          :description="card.description"
          :icon="card.icon"
          :to="`/companies/${companyId}/${card.route}`"
          :disabled="card.disabled"
          :loading="isPending(card.loadingKey)"
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
import type { CompanyResponse } from '~/types/company'

// Set page metadata
useHead({
  title: 'Mint - Company Dashboard',
  meta: [{ name: 'description', content: 'Company Information Dashboard' }]
})

const router = useRouter()
const showAiChat = ref(false)

// Use the company data composable
const {
  company,
  companyId,
  fetchCompany,
  getSourcedValue,
} = useCompanyData()

// Info cards configuration
const infoCards = [
  {
    title: 'Company Profile',
    description: 'View detailed company information, business lines, and key metrics.',
    icon: 'fa-building',
    route: 'profile',
    loadingKey: 'profile',
    disabled: false
  },
  {
    title: 'Activities & Events',
    description: 'Explore company events, trade shows, and key activities.',
    icon: 'fa-calendar-days',
    route: 'activities',
    loadingKey: 'timeline',
    disabled: false
  },
  {
    title: 'Products',
    description: 'Browse the company\'s products, services, and offerings.',
    icon: 'fa-box',
    route: 'products',
    loadingKey: 'products',
    disabled: false
  },
  {
    title: 'Team & Management',
    description: 'Leadership team, organizational structure, and key personnel.',
    icon: 'fa-users',
    route: 'team',
    loadingKey: 'team',
    disabled: false
  },
  {
    title: 'Job Offers',
    description: 'Current job openings, career opportunities, and hiring information.',
    icon: 'fa-briefcase',
    route: 'jobs',
    loadingKey: 'jobs',
    disabled: false
  },
  {
    title: 'Corporate Communications',
    description: 'Press releases, public statements, and official communications.',
    icon: 'fa-bullhorn',
    route: 'communications',
    loadingKey: 'communications',
    disabled: true
  },
  {
    title: 'Financials',
    description: 'Financial data, revenue information, and market performance.',
    icon: 'fa-chart-line',
    route: 'financials',
    loadingKey: 'financials',
    disabled: true
  },
  {
    title: 'Mentions',
    description: 'News articles, media coverage, and third-party mentions.',
    icon: 'fa-quote-left',
    route: 'mentions',
    loadingKey: 'press',
    disabled: true
  }
]

// Lifecycle hooks
onMounted(async () => {
  if (!companyId.value) {
    router.push('/companies')
  }
  await fetchCompany()  
})

// Format website URL
const formatWebsiteUrl = (website?: string) => {
  if (!website) return '#'
  return website.startsWith('http') ? website : `https://${website}`
}

// Check if a section is pending
const taskStore = useTaskStore()
const isPending = (sectionKey: string) => {
  const id = companyId.value
  if (!id) return false
  return taskStore.getTaskStatus(id, sectionKey) === 'pending' || 
         taskStore.getTaskStatus(id, sectionKey) === 'running'
}

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
