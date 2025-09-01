<template>
  <!-- Main content grid -->
  <div class="grid grid-cols-3 gap-6 bg-bg1 p-6 rounded-lg border border-border-2">
    <!-- First column: Company general info -->
    <div
      class="col-span-3 row-span-4 lg:col-span-1 space-y-4 w-full bg-bg1 p-6 rounded-lg border border-border-2"
    >
      <div class="flex flex-col gap-4">
        <div class="flex flex-col items-center text-center gap-2">
          <div class="relative w-20 h-20 rounded-xl overflow-hidden bg-white ring-2 ring-border-2">
            <img
              v-if="getCompanyDomain(company?.website)"
              :src="getLogoUrl(company?.website)"
              :alt="`${company?.name} logo`"
              class="w-full h-full object-contain p-2"
              @error="showFallbackIcon = true"
              v-show="!showFallbackIcon"
            />
            <div
              v-show="showFallbackIcon || !getCompanyDomain(company?.website)"
              class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary/10 to-primary/20"
            >
              <i class="fa fa-building text-3xl text-primary"></i>
            </div>
          </div>
          <div>
            <h2 class="text-2xl font-bold capitalize">
              {{ company?.name }}
            </h2>
          </div>
        </div>

        <div v-if="company?.profile?.catchphrase">
          <p class="text-secondary text-center italic">
            {{ getSourcedValue(company.profile.catchphrase) || 'No catchphrase found' }}
          </p>
        </div>

        <div class="flex flex-col gap-3">
          <OPopper :text="$t('company.dashboard.generalInfo.website')" side="left">
            <div
              class="flex items-center bg-bg1 border border-border-2 px-4 py-3 rounded-lg gap-4 text-left hover:border-primary/50 transition-colors"
            >
              <i class="fa fa-link fa-fw text-primary"></i>
              <a
                :href="formatWebsiteUrl(company?.website)"
                target="_blank"
                class="text-primary hover:text-primary/80 text-sm font-medium transition-colors"
              >
                {{ company?.website }}
              </a>
            </div>
          </OPopper>

          <OPopper :text="$t('company.dashboard.generalInfo.headquarters')" side="left">
            <div
              class="flex items-center bg-bg1 border border-border-2 px-4 py-3 rounded-lg gap-4 text-left"
              v-if="company?.profile?.hq"
            >
              <i class="fa fa-map-marker fa-fw text-secondary"></i>
              <span class="text-secondary text-sm">
                {{ getSourcedValue(company.profile.hq) || 'Unknown' }}
              </span>
            </div>
          </OPopper>

          <OPopper :text="$t('company.dashboard.generalInfo.ceo')" side="left">
            <div
              class="flex items-center bg-bg1 border border-border-2 px-4 py-3 rounded-lg gap-4 text-left"
              v-if="company?.profile?.ceo"
            >
              <i class="fa fa-user-tie fa-fw text-secondary"></i>
              <span class="text-secondary text-sm">
                {{ getSourcedValue(company.profile.ceo) || 'Unknown' }}
              </span>
            </div>
          </OPopper>

          <OPopper :text="$t('company.dashboard.generalInfo.revenue')" side="left">
            <div
              class="flex items-center bg-bg1 border border-border-2 px-4 py-3 rounded-lg gap-4 text-left"
              v-if="company?.profile?.revenue"
            >
              <i class="fa fa-money-bill fa-fw text-secondary"></i>
              <span class="text-secondary text-sm">
                {{ getSourcedValue(company.profile.revenue) || 'Unknown' }}
              </span>
            </div>
          </OPopper>

          <div
            class="grid grid-cols-6 gap-3 justify-center mt-4"
            v-if="company?.digital?.socialMediaAccounts"
          >
            <a
              v-for="account in company?.digital?.socialMediaAccounts.value"
              :key="account.platform"
              :href="getSourcedValue(account.url)"
              target="_blank"
              class="w-10 h-10 rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center text-primary hover:bg-primary hover:text-white transition-all"
            >
              <i class="fa text-lg" :class="getSocialIcon(account.platform)"></i>
            </a>
          </div>
        </div>
      </div>
    </div>

    <InfoCard
      v-for="card in infoCards"
      :key="card.titleKey"
      :title="$t(card.titleKey)"
      :description="$t(card.descriptionKey)"
      :icon="card.icon"
      :to="`/folders/${folderId}/companies/${companyId}/${card.route}`"
      :disabled="card.disabled"
      :loading="isPending(card.loadingKey)"
    />

    <div v-if="company" class="col-span-3 row-span-1 text-xs text-secondary italic">
      Created by {{ company.owner_username }} on {{ company.created_at }}
    </div>
  </div>

</template>

<route lang="yaml">
meta:
  permissions:
    - company.view
</route>

<script lang="ts" setup>
import InfoCard from '@/components/company/InfoCard.vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import { companyByIdQuery } from '@/queries/companies'
import { OPopper } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const router = useRouter()
const showFallbackIcon = ref(false)
const route = useRoute()

const companyId = computed(() => route.params.companyId as string)
const folderId = computed(() => route.params.folderId as string)

// Use the company data composable
const { data: company } = useQuery(
  companyByIdQuery, 
  () => ({ id: companyId.value }),
  {
    enabled: () => !!companyId.value && companyId.value !== 'null' && companyId.value !== 'undefined',
  }
)

// Reset fallback icon when company changes
watch(company, () => {
  showFallbackIcon.value = false
})

// Info cards configuration
const infoCards = [
  {
    titleKey: 'company.dashboard.infoCards.profile.title',
    descriptionKey: 'company.dashboard.infoCards.profile.description',
    icon: 'fa-building',
    route: 'profile',
    loadingKey: 'profile',
    disabled: false,
  },
  {
    titleKey: 'company.dashboard.infoCards.activities.title',
    descriptionKey: 'company.dashboard.infoCards.activities.description',
    icon: 'fa-calendar-days',
    route: 'timeline',
    loadingKey: 'timeline',
    disabled: false,
  },
  {
    titleKey: 'company.dashboard.infoCards.products.title',
    descriptionKey: 'company.dashboard.infoCards.products.description',
    icon: 'fa-box',
    route: 'products',
    loadingKey: 'products',
    disabled: false,
  },
  {
    titleKey: 'company.dashboard.infoCards.team.title',
    descriptionKey: 'company.dashboard.infoCards.team.description',
    icon: 'fa-users',
    route: 'team',
    loadingKey: 'team',
    disabled: false,
  },
  {
    titleKey: 'company.dashboard.infoCards.jobs.title',
    descriptionKey: 'company.dashboard.infoCards.jobs.description',
    icon: 'fa-briefcase',
    route: 'jobs',
    loadingKey: 'jobs',
    disabled: false,
  },
  {
    titleKey: 'company.dashboard.infoCards.press.title',
    descriptionKey: 'company.dashboard.infoCards.press.description',
    icon: 'fa-newspaper',
    route: 'press',
    loadingKey: 'press',
    disabled: false,
  },
  {
    titleKey: 'company.dashboard.infoCards.communications.title',
    descriptionKey: 'company.dashboard.infoCards.communications.description',
    icon: 'fa-bullhorn',
    route: 'communications',
    loadingKey: 'communications',
    disabled: true,
  },
  {
    titleKey: 'company.dashboard.infoCards.financials.title',
    descriptionKey: 'company.dashboard.infoCards.financials.description',
    icon: 'fa-chart-line',
    route: 'financials',
    loadingKey: 'financials',
    disabled: true,
  },
]

// Lifecycle hooks
onMounted(async () => {
  if (!companyId.value) {
    router.push('/companies')
  }
})

// Format website URL
const formatWebsiteUrl = (website?: string) => {
  if (!website) return '#'
  return website.startsWith('http') ? website : `https://${website}`
}

// Check if a section is pending
// const taskStore = useTaskStore()

const isPending = (sectionKey: string) => {
  const id = companyId.value
  if (!id) return false
  // return taskStore.getTaskStatus(id, sectionKey) === 'running'
  return false
}

// Helper function to extract domain from website URL
const getCompanyDomain = (website?: string) => {
  if (!website) return null
  try {
    // Remove protocol and www
    let domain = website.replace(/^https?:\/\//, '').replace(/^www\./, '')
    // Remove trailing slash and any path
    domain = domain.split('/')[0]
    return domain
  } catch {
    return null
  }
}

// Helper function to get logo URL from logo.dev
const getLogoUrl = (website?: string) => {
  const domain = getCompanyDomain(website)
  if (!domain) return ''
  return `https://img.logo.dev/${domain}?token=pk_Buf4yyXmRC2HMagyfO0jrg&retina=true`
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
    bitbucket: 'fa-bitbucket',
  }

  return iconMap[platform.toLowerCase()] || 'fa-globe'
}
</script>
