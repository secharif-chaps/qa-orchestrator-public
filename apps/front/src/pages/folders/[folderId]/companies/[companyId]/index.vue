<template>
  <div class="space-y-6">
    <div class="gap-4 space-y-4">
      <ChapseAssistAlert @setup="handleAssistSetup" @dismiss="handleAssistDismiss" />

      <ChapseAssistQuickActions
        v-if="company?.id"
        :company-id="company.id"
        :company="company"
        :tasks="tasks"
        @action-click="handleQuickActionClick"
        @load-success="handleQuickActionsSuccess"
        @load-error="handleQuickActionsError"
      />

      <Card class="relative flex-1">
        <div
          v-if="isTaskRunning('profile')"
          class="rounded-card absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm"
        >
          <div class="text-neutral-black-font flex items-center gap-3 text-base">
            <Icon icon="fas fa-spinner" class="fa-spin text-xl" />
            <span>{{ t('screen.company.analysis.loading') }}</span>
          </div>
        </div>
        <div class="flex items-start gap-6">
          <!-- Company Info -->
          <div class="text-neutral-black-font gap-md flex min-w-0 flex-1 flex-col">
            <div class="gap-2xs flex flex-col">
              <div v-if="!isTaskRunning('profile')">
                <h3 class="font-bold" v-if="company?.profile?.businessLine">
                  {{ getSourcedValue(company.profile.businessLine) }}
                </h3>
                <h3 class="font-bold" v-else>
                  {{ t('screen.company.fields.notSpecified') }}
                </h3>
              </div>
              <div v-else>
                <div class="flex flex-wrap gap-2">
                  <div class="bg-sage-100 h-12 w-full rounded-lg"></div>
                </div>
              </div>
              <div>
                <p>{{ company?.products?.insights }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <ProfileInfoItem
                v-for="item in companyInfoItems"
                :key="item.label"
                :icon="item.icon"
                :label="item.label"
                :value="item.value"
                :loading="isTaskRunning('profile')"
                :placeholder="t('screen.company.fields.notSpecified')"
              />
            </div>

            <!-- Social Media -->
          </div>
        </div>
      </Card>

      <Card class="relative">
        <div
          v-if="isTaskRunning('digital')"
          class="rounded-card absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm"
        >
          <div class="text-neutral-black-font flex items-center gap-3 text-base">
            <Icon icon="fas fa-spinner" class="fa-spin text-xl" />
            <span>{{ t('screen.company.analysis.loading') }}</span>
          </div>
        </div>
        <div class="space-y-2xs">
          <p class="text-neutral-black-font text-lg font-bold">
            {{ t('screen.company.onlinePresence.title') }}
          </p>
          <div class="gap-xs grid grid-cols-2 lg:grid-cols-3">
            <a
              v-if="company?.website"
              :href="formatWebsiteUrl(company.website)"
              target="_blank"
              rel="noopener noreferrer"
              class="bg-primary-lighter border-primary-light-stroke text-secondary-alt-font hover:bg-primary-lighter p-xs gap-md flex cursor-pointer items-center rounded-lg border transition-colors"
            >
              <Icon icon="fa-link" class="fa-fw" />
              <div class="flex w-44 flex-col">
                <div class="gap-3xs flex items-center">
                  <span class="truncate">
                    {{ t('screen.company.onlinePresence.website') }}
                  </span>
                  <Icon icon="fa-external-link" class="text-base" />
                </div>
                <span class="text-neutral-black-font truncate text-sm">
                  {{ company.website }}
                </span>
              </div>
            </a>
            <div v-else class="bg-primary-lightest rounded-card flex items-center gap-3 px-4 py-3">
              <Icon icon="fas fa-link" class="fa-fw text-neutral-black-font" />
              <div class="flex w-44 flex-col gap-1">
                <span class="truncate text-sm">
                  {{ t('screen.company.onlinePresence.website') }}
                </span>
                <span class="text-neutral-black-font truncate text-xs">
                  {{ t('screen.company.fields.notSpecified') }}
                </span>
              </div>
            </div>
          </div>
        </div>
        <div class="space-y-2xs">
          <p class="text-neutral-black-font text-lg font-bold">
            {{ t('screen.company.onlinePresence.socialMedia') }}
          </p>
          <div>
            <!-- Loading state -->
            <div v-if="isTaskRunning('digital')" class="flex flex-wrap gap-2">
              <div v-for="i in 4" :key="i" class="bg-sage-100 h-4 w-12 rounded-full"></div>
            </div>
            <!-- Social media accounts -->
            <div
              v-else-if="company?.digital?.socialMediaAccounts?.length"
              class="flex flex-wrap gap-2"
            >
              <Tag
                v-for="account in company.digital.socialMediaAccounts"
                color="sage"
                size="sm"
                as="a"
                :key="account.platform"
                :href="account.url"
                target="_blank"
                rel="noopener noreferrer"
                :icon="getSocialIcon(account.platform)"
              >
                {{ account.platform }}
              </Tag>
            </div>
            <!-- No data -->
            <span v-else class="text-neutral-black-font text-xs">
              {{ t('screen.company.fields.notSpecified') }}
            </span>
          </div>
        </div>
      </Card>
      <Card class="col-span-12">
        <div class="flex flex-col gap-4">
          <!-- Header Row: Title on left, Tabs on right -->
          <div class="flex items-center justify-between">
            <h3 class="text-neutral-black-font text-lg font-bold">
              {{ t('screen.profile.title') }}
            </h3>
            <Toggle size="sm" v-model="currentTab" :options="tabOptions" />
          </div>

          <!-- Tab Content with transition -->
          <Transition name="tab-fade" mode="out-in">
            <ProfileTabProductsOverview v-if="currentTab === 'products-overview'" key="products" />
            <ProfileTabPartnersLabels v-else-if="currentTab === 'partners'" key="partners" />
            <ProfileTabDigitalStrategy v-else-if="currentTab === 'strategy'" key="strategy" />
          </Transition>
        </div>
      </Card>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - organization.read
</route>

<script lang="ts" setup>
import ProfileInfoItem from '@/components/company/profile/ProfileInfoItem.vue'
import ProfileTabDigitalStrategy from '@/components/company/profile/ProfileTabDigitalStrategy.vue'
import ProfileTabPartnersLabels from '@/components/company/profile/ProfileTabPartnersLabels.vue'
import ProfileTabProductsOverview from '@/components/company/profile/ProfileTabProductsOverview.vue'
import ChapseAssistAlert from '@/components/features/chapse-assist/ChapseAssistAlert.vue'
import ChapseAssistQuickActions from '@/components/features/chapse-assist/ChapseAssistQuickActions.vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Card from '@/components/ui/Card.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import type { QuickAction } from '@/types/ai-preferences'
import type { TaskType } from '@/types/task'
import { Icon, Tag, Toggle } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import type { Ref } from 'vue'
import { computed, inject, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const router = useRouter()
const route = useRoute('/folders/[folderId]/companies/[companyId]/')
const { t } = useI18n()

const companyId = computed(() => route.params.companyId)

// Inject selected language from parent [companyId].vue
const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

// Tab configuration
const tabOptions = computed(() => [
  {
    value: 'products-overview',
    icon: 'fa fa-box-open',
    label: t('screen.profile.tabs.productsOverview'),
  },
  {
    value: 'partners',
    icon: 'fa fa-handshake',
    label: t('screen.profile.tabs.partnersLabels'),
  },
  {
    value: 'strategy',
    icon: 'fa fa-chart-line',
    label: t('screen.profile.tabs.digitalStrategy'),
  },
])
// Current tab based on query param - synced with Toggle
const currentTab = computed({
  get: () => {
    const tab = route.query.tab as string | undefined
    if (tab === 'partners') return 'partners'
    if (tab === 'strategy') return 'strategy'
    return 'products-overview'
  },
  set: (value: string) => {
    const query = { ...route.query }
    if (value === 'products-overview') {
      delete query.tab
    } else {
      query.tab = value
    }
    router.push({ query })
  },
})

// Use the company data composable with language for translations
const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
    language: selectedLanguage.value,
  }),
)

const { data: tasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const isTaskRunning = (taskType: TaskType): boolean => {
  if (!tasks.value) return false
  const task = tasks.value?.find((t) => t.type === taskType)
  return task?.status === 'running' || task?.status === 'pending'
}

// Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
// No polling needed - cache is invalidated automatically when tasks update.

// Company info items for the grid
const companyInfoItems = computed(() => [
  {
    icon: 'fa fa-users',
    label: t('screen.company.fields.employeeCount'),
    value: company.value?.profile?.employeeCount?.value,
  },
  {
    icon: 'fa fa-map-marker',
    label: t('screen.company.fields.headquarters'),
    value: company.value?.profile?.hq?.value,
  },
  {
    icon: 'fa fa-user-tie',
    label: t('screen.company.fields.ceo'),
    value: company.value?.profile?.ceo?.value,
  },
  {
    icon: 'fa fa-money-bill',
    label: t('screen.company.fields.revenue'),
    value: company.value?.profile?.revenue?.value,
  },
])

// Helper function to get social media icon
const getSocialIcon = (platform: string) => {
  const iconMap: Record<string, string> = {
    facebook: 'fab fa-facebook',
    twitter: 'fab fa-twitter',
    instagram: 'fab fa-instagram',
    linkedin: 'fab fa-linkedin',
    youtube: 'fab fa-youtube',
    tiktok: 'fab fa-tiktok',
    pinterest: 'fab fa-pinterest',
    snapchat: 'fab fa-snapchat',
    telegram: 'fab fa-telegram',
    whatsapp: 'fab fa-whatsapp',
    discord: 'fab fa-discord',
    reddit: 'fab fa-reddit',
    twitch: 'fab fa-twitch',
    github: 'fab fa-github',
    gitlab: 'fab fa-gitlab',
    bitbucket: 'fab fa-bitbucket',
  }

  return iconMap[platform.toLowerCase()] || 'fa-globe'
}

// Helper function to format website URL (ensure it starts with http/https)
const formatWebsiteUrl = (url: string): string => {
  if (!url) return ''
  // If URL already has a protocol, return as-is
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url
  }
  // Default to https
  return `https://${url}`
}

/**
 * Chapse Assist Event Handlers
 */

// Handle setup button click (navigates to AI preferences setup)
const handleAssistSetup = () => {
  router.push({ name: '/settings/ai-preferences.ai-preferences-setup' })
}

// Handle alert dismiss
const handleAssistDismiss = () => {
  console.log('User dismissed Chapse Assist alert')
}

// Handle quick action button click
const handleQuickActionClick = (action: QuickAction) => {
  console.log('User clicked quick action:', action)
  // The openQuickAction function in useChapseAssist composable
  // will dispatch the custom event that ChapseSidebar listens for
}

// Handle successful quick actions load
const handleQuickActionsSuccess = (actions: QuickAction[]) => {
  console.log('✅ Quick actions loaded successfully:', actions.length)
}

// Handle quick actions load error
const handleQuickActionsError = (error: string) => {
  console.error('❌ Failed to load quick actions:', error)
}
</script>

<style scoped>
.tab-fade-enter-active,
.tab-fade-leave-active {
  transition: opacity 0.15s ease;
}

.tab-fade-enter-from,
.tab-fade-leave-to {
  opacity: 0;
}
</style>
