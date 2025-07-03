<template>
  <LayoutsCompanyCard :title="$t('company.dashboard.title')" icon="fa-building" v-if="company">

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
              <h2 class="text-2xl font-bold capitalize">
                {{ company?.name }}
              </h2>
            </div>
          </div>

          <div v-if="company?.profile?.catchphrase">
            <p class="text-gray-600">
              {{ getSourcedValue(company.profile.catchphrase) || "No catchphrase found" }}
            </p>
          </div>

          <div class="flex flex-col gap-2">
            <OPopper :text="$t('company.dashboard.generalInfo.website')" side="left"> 
            <div class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-4 text-left">
              <i class="fa fa-link fa-fw"></i>
              <a
                :href="formatWebsiteUrl(company?.website)"
                target="_blank"
                class="text-blue-500 hover:underline hover:text-primary underline text-sm"
              >
                {{ company?.website }}
              </a>
            </div>
          </OPopper>

            <OPopper :text="$t('company.dashboard.generalInfo.headquarters')" side="left">
            <div
              class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-4 text-left"
              v-if="company?.profile?.hq"
            >
              <i class="fa fa-map-marker fa-fw"></i>
              <span class="text-secondary text-sm text-left">
                {{ getSourcedValue(company.profile.hq) || 'Unknown' }}
              </span>
            </div>
          </OPopper>

          <OPopper :text="$t('company.dashboard.generalInfo.ceo')" side="left">
            <div
              class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-4 text-left"
              v-if="company?.profile?.ceo"
            >
              <i class="fa fa-user-tie fa-fw"></i>
              <span class="text-secondary text-sm text-left">
                {{ getSourcedValue(company.profile.ceo) || 'Unknown' }}
              </span>
            </div>
          </OPopper>

          <OPopper :text="$t('company.dashboard.generalInfo.revenue')" side="left">
            <div
              class="flex items-center bg-bg1 px-4 py-2 rounded-lg gap-4 text-left"
              v-if="company?.profile?.revenue"
            >
              <i class="fa fa-money-bill fa-fw"></i>
              <span class="text-secondary text-sm text-left">
                {{ getSourcedValue(company.profile.revenue) || 'Unknown' }}
              </span>
            </div>
          </OPopper>

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
          :key="card.titleKey"
          :title="$t(card.titleKey)"
          :description="$t(card.descriptionKey)"
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
import { OAlert, OPopper } from '@owlint/feathers-vue'
import type { CompanyResponse } from '~/types/company'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Set page metadata
useHead({
  title: `Mint - ${t('company.dashboard.title')}`,
  meta: [{ name: 'description', content: t('company.dashboard.description') }]
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
    titleKey: 'company.dashboard.infoCards.profile.title',
    descriptionKey: 'company.dashboard.infoCards.profile.description',
    icon: 'fa-building',
    route: 'profile',
    loadingKey: 'profile',
    disabled: false
  },
  {
    titleKey: 'company.dashboard.infoCards.activities.title',
    descriptionKey: 'company.dashboard.infoCards.activities.description',
    icon: 'fa-calendar-days',
    route: 'activities',
    loadingKey: 'timeline',
    disabled: false
  },
  {
    titleKey: 'company.dashboard.infoCards.products.title',
    descriptionKey: 'company.dashboard.infoCards.products.description',
    icon: 'fa-box',
    route: 'products',
    loadingKey: 'products',
    disabled: false
  },
  {
    titleKey: 'company.dashboard.infoCards.team.title',
    descriptionKey: 'company.dashboard.infoCards.team.description',
    icon: 'fa-users',
    route: 'team',
    loadingKey: 'team',
    disabled: false
  },
  {
    titleKey: 'company.dashboard.infoCards.jobs.title',
    descriptionKey: 'company.dashboard.infoCards.jobs.description',
    icon: 'fa-briefcase',
    route: 'jobs',
    loadingKey: 'jobs',
    disabled: false
  },
  {
    titleKey: 'company.dashboard.infoCards.communications.title',
    descriptionKey: 'company.dashboard.infoCards.communications.description',
    icon: 'fa-bullhorn',
    route: 'communications',
    loadingKey: 'communications',
    disabled: true
  },
  {
    titleKey: 'company.dashboard.infoCards.financials.title',
    descriptionKey: 'company.dashboard.infoCards.financials.description',
    icon: 'fa-chart-line',
    route: 'financials',
    loadingKey: 'financials',
    disabled: true
  },
  {
    titleKey: 'company.dashboard.infoCards.mentions.title',
    descriptionKey: 'company.dashboard.infoCards.mentions.description',
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
  if (!company.value) {
    await fetchCompany()
  }
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
  return taskStore.getTaskStatus(id, sectionKey) === 'running'
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
