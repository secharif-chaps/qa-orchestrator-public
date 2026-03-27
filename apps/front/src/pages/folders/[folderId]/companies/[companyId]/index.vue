<template>
  <div class="space-y-6">
    <Card v-if="tasks && completedCount < tasks.length">
      <div class="bg-base-200 flex h-3 w-full overflow-hidden rounded-full">
        <!-- Completed segment -->
        <div
          v-if="completedPercentage > 0"
          class="bg-success-500 h-full transition-all duration-500 ease-out"
          :style="{ width: `${completedPercentage}%` }"
          :title="
            t('screen.company.tasks.completed', {
              count: completedCount,
              percentage: Math.round(completedPercentage),
            })
          "
        ></div>

        <!-- Running segment -->
        <div
          v-if="runningPercentage > 0"
          class="bg-info-500 h-full transition-all duration-500 ease-out"
          :style="{ width: `${runningPercentage}%` }"
          :title="
            t('screen.company.tasks.running', {
              count: runningCount,
              percentage: Math.round(runningPercentage),
            })
          "
        ></div>

        <!-- Error segment -->
        <div
          v-if="errorPercentage > 0"
          class="bg-error-500 h-full transition-all duration-500 ease-out"
          :style="{ width: `${errorPercentage}%` }"
          :title="
            t('screen.company.tasks.error', {
              count: errorCount,
              percentage: Math.round(errorPercentage),
            })
          "
        ></div>

        <!-- Pending segment -->
        <div
          v-if="pendingPercentage > 0"
          class="bg-sage-200 h-full transition-all duration-500 ease-out"
          :style="{ width: `${pendingPercentage}%` }"
          :title="
            t('screen.company.tasks.pending', {
              count: pendingCount,
              percentage: Math.round(pendingPercentage),
            })
          "
        ></div>
      </div>
    </Card>

    <!-- Company Info Card - Full Width -->
    <div class="gap-4 space-y-4 xl:flex xl:space-y-0">
      <Card class="relative flex-1">
        <div
          v-if="isTaskRunning('profile')"
          class="bg-base-100/80 rounded-card absolute inset-0 flex items-center justify-center backdrop-blur-sm"
        >
          <div class="text-secondary flex items-center gap-3 text-base">
            <i class="fas fa-spinner fa-spin text-xl"></i>
            <span>{{ t('screen.company.analysis.loading', 'Loading analysis...') }}</span>
          </div>
        </div>
        <div class="flex items-start gap-6">
          <!-- Company Info -->
          <div class="flex min-w-0 flex-1 flex-col gap-2">
            <div v-if="!isTaskRunning('profile')">
              <h3 class="font-bold" v-if="company?.profile?.businessLine">
                {{ getSourcedValue(company.profile.businessLine) }}
              </h3>
              <h3 class="font-bold" v-else>
                {{ t('screen.company.fields.notSpecified', 'Not specified') }}
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

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <ProfileInfoItem
                v-for="item in companyInfoItems"
                :key="item.label"
                :icon="item.icon"
                :label="item.label"
                :value="item.value"
                :loading="isTaskRunning('profile')"
                :placeholder="t('screen.company.fields.notSpecified', 'Not specified')"
              />
            </div>

            <!-- Social Media -->
          </div>
        </div>
      </Card>

      <Card class="relative xl:max-w-md">
        <div
          v-if="isTaskRunning('digital')"
          class="bg-base-100/80 rounded-card absolute inset-0 flex items-center justify-center backdrop-blur-sm"
        >
          <div class="text-secondary flex items-center gap-3 text-base">
            <i class="fas fa-spinner fa-spin text-xl"></i>
            <span>{{ t('screen.company.analysis.loading', 'Loading analysis...') }}</span>
          </div>
        </div>
        <p>{{ t('screen.company.onlinePresence.title', 'Online Presence') }}</p>
        <a
          v-if="company?.website"
          :href="formatWebsiteUrl(company.website)"
          target="_blank"
          rel="noopener noreferrer"
          class="bg-base-200 rounded-card hover:bg-base-300 flex cursor-pointer items-center gap-3 px-4 py-3 transition-colors"
        >
          <i class="fa-solid fa-link fa-fw text-secondary"></i>
          <div class="flex w-44 flex-col gap-1">
            <span class="truncate text-sm">
              {{ t('screen.company.onlinePresence.website', 'Website') }}
            </span>
            <span class="text-secondary truncate text-xs underline">
              {{ company.website }}
            </span>
          </div>
          <i class="fa-solid fa-external-link fa-fw text-secondary ml-auto text-xs"></i>
        </a>
        <div v-else class="bg-base-200 rounded-card flex items-center gap-3 px-4 py-3">
          <i class="fa-solid fa-link fa-fw text-secondary"></i>
          <div class="flex w-44 flex-col gap-1">
            <span class="truncate text-sm">
              {{ t('screen.company.onlinePresence.website', 'Website') }}
            </span>
            <span class="text-secondary truncate text-xs">
              {{ t('screen.company.fields.notSpecified', 'Not specified') }}
            </span>
          </div>
        </div>
        <p>{{ t('screen.company.onlinePresence.socialMedia', 'Social Media Presence') }}</p>
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
              variant="slate"
              v-for="account in company.digital.socialMediaAccounts"
              :key="account.platform"
              :href="account.url"
              target="_blank"
            >
              <i class="fa" :class="getSocialIcon(account.platform)"></i>
              <span class="underline">
                {{ account.platform }}
              </span>
            </Tag>
          </div>
          <!-- No data -->
          <span v-else class="text-secondary text-xs">
            {{ t('screen.company.fields.notSpecified', 'Not specified') }}
          </span>
        </div>
      </Card>
    </div>

    <!-- Chapse Assist Alert (Onboarding) -->
    <ChapseAssistAlert @setup="handleAssistSetup" @dismiss="handleAssistDismiss" />

    <!-- Chapse Assist Quick Actions -->
    <ChapseAssistQuickActions
      v-if="company?.id"
      :company-id="company.id"
      :company="company"
      :tasks="tasks"
      @action-click="handleQuickActionClick"
      @load-success="handleQuickActionsSuccess"
      @load-error="handleQuickActionsError"
    />

    <div>
      <h4 class="font-semibold">{{ t('screen.company.sections.analyses', 'Analyses') }}</h4>
    </div>

    <!-- Analysis Cards Grid -->
    <div class="grid auto-rows-fr grid-cols-1 gap-6 md:grid-cols-2">
      <AnalysisCard
        v-for="card in analysisCards"
        :key="card.section"
        :title="card.title"
        :description="card.description"
        :icon="card.icon"
        :insights="card.insights"
        :task-status="card.taskStatus"
        :error-details="card.errorDetails"
        :task-id="card.taskId"
        :task-updated-at="card.taskUpdatedAt"
        :disabled="card.disabled"
        @click="openSection(card.section)"
        @restart="handleRestartTask"
      />
    </div>

    <!-- Footer -->
    <div v-if="company" class="text-secondary text-center text-xs italic">
      {{
        t('screen.company.footer.createdBy', {
          username: company.owner_username,
          date: formatFullDate(company.created_at),
        })
      }}
    </div>
  </div>

  <!-- Raw Knowledge Debug Section (only for suh/nmr) -->
  <RawKnowledgeDebug v-if="isDebugUser" />

  <!-- Section Modal -->
  <SectionModal v-model="showSectionModal" v-model:section="activeSection" />
</template>

<route lang="yaml">
meta:
  permissions:
    - organization.read
</route>

<script lang="ts" setup>
import AnalysisCard from '@/components/company/AnalysisCard.vue'
import RawKnowledgeDebug from '@/components/company/profile/RawKnowledgeDebug.vue'
import SectionModal from '@/components/company/SectionModal.vue'
import ChapseAssistAlert from '@/components/features/chapse-assist/ChapseAssistAlert.vue'
import ChapseAssistQuickActions from '@/components/features/chapse-assist/ChapseAssistQuickActions.vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Card from '@/components/ui/Card.vue'
import Tag from '@/components/ui/Tag.vue'
import ProfileInfoItem from '@/components/company/profile/ProfileInfoItem.vue'
import { useRestartTask } from '@/mutations/tasks'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { useAuthStore } from '@/stores/auth'
import type { QuickAction } from '@/types/ai-preferences'
import type { TaskStatus, TaskType } from '@/types/task'
import { useQuery } from '@pinia/colada'
import { computed, ref, inject } from 'vue'
import type { Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { formatFullDate } from '@/utils/time'

const router = useRouter()
const route = useRoute('/folders/[folderId]/companies/[companyId]/')
const { t } = useI18n()
const authStore = useAuthStore()

const showSectionModal = ref(false)
const activeSection = ref<TaskType | null>(null)

const companyId = computed(() => route.params.companyId)

// Inject selected language from parent [companyId].vue
const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const isDebugUser = computed(() => {
  const username = authStore.user?.profile?.preferred_username?.toLowerCase()
  return username === 'nmr' || username === 'suh' || username === 'nmr-cv'
})

// Use the company data composable with language for translations
const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
    language: selectedLanguage.value,
  }),
)

// Restart task mutation
const { mutate: restartTaskMutation } = useRestartTask()

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

// Helper function to get task status by type
const getTaskStatus = (taskType: TaskType): TaskStatus | null => {
  if (!tasks.value) return null
  const task = tasks.value?.find((t) => t.type === taskType)
  return task?.status || null
}

// Helper function to get structured Dify error details
const getTaskErrorDetails = (taskType: TaskType) => {
  if (!tasks.value) return null
  const task = tasks.value?.find((t) => t.type === taskType)
  return task?.error_details || null
}

// Helper function to get task updated_at timestamp (used for accurate countdown after refresh/reopen)
const getTaskUpdatedAt = (taskType: TaskType): string | null => {
  if (!tasks.value) return null
  const task = tasks.value?.find((t) => t.type === taskType)
  return task?.updated_at || null
}

// Helper function to get task ID by type
const getTaskId = (taskType: TaskType): number | null => {
  if (!tasks.value) return null
  const task = tasks.value?.find((t) => t.type === taskType)
  return task?.id || null
}

// Company info items for the grid
const companyInfoItems = computed(() => [
  {
    icon: 'fa fa-users',
    label: t('screen.company.fields.employeeCount', 'Employee Count'),
    value: company.value?.profile?.employeeCount?.value,
  },
  {
    icon: 'fa fa-map-marker',
    label: t('screen.company.fields.headquarters', 'Headquarters'),
    value: company.value?.profile?.hq?.value,
  },
  {
    icon: 'fa fa-user-tie',
    label: t('screen.company.fields.ceo', 'CEO'),
    value: company.value?.profile?.ceo?.value,
  },
  {
    icon: 'fa fa-money-bill',
    label: t('screen.company.fields.revenue', 'Revenue'),
    value: company.value?.profile?.revenue?.value,
  },
])

// Analysis cards configuration
const analysisCards = computed(() => {
  return [
    {
      section: 'profile' as TaskType,
      title: t('screen.company.analysisCards.profile.title', 'Company Profile'),
      description: t(
        'screen.company.analysisCards.profile.description',
        'View detailed company information, business lines, and key metrics',
      ),
      icon: 'fas fa-building',
      insights: company.value?.profile?.businessLine?.value || company.value?.digital?.insights,
      taskStatus: getTaskStatus('profile') || getTaskStatus('digital'),
      errorDetails: getTaskErrorDetails('profile') || getTaskErrorDetails('digital'),
      taskId: getTaskId('profile') || getTaskId('digital'),
      taskUpdatedAt: getTaskUpdatedAt('profile') || getTaskUpdatedAt('digital'),
      disabled: false,
    },
    {
      section: 'timeline' as TaskType,
      title: t('screen.company.analysisCards.timeline.title', 'Timeline & History'),
      description: t(
        'screen.company.analysisCards.timeline.description',
        'Company history, milestones, and key events over time',
      ),
      icon: 'fas fa-calendar-days',
      insights: t(
        'screen.company.analysisCards.timeline.insights',
        'Discover the company history and key events',
      ),
      taskStatus: getTaskStatus('timeline'),
      errorDetails: getTaskErrorDetails('timeline'),
      taskId: getTaskId('timeline'),
      taskUpdatedAt: getTaskUpdatedAt('timeline'),
      disabled: false,
    },
    {
      section: 'products' as TaskType,
      title: t('screen.company.analysisCards.products.title', 'Products & Services'),
      description: t(
        'screen.company.analysisCards.products.description',
        'Browse products, services, and offerings',
      ),
      icon: 'fas fa-box',
      insights:
        company.value?.products?.insights ||
        t(
          'screen.company.analysisCards.products.insights',
          'Discover the company products and services',
        ),
      taskStatus: getTaskStatus('products'),
      errorDetails: getTaskErrorDetails('products'),
      taskId: getTaskId('products'),
      taskUpdatedAt: getTaskUpdatedAt('products'),
      disabled: false,
    },
    {
      section: 'team' as TaskType,
      title: t('screen.company.analysisCards.team.title', 'Team & Management'),
      description: t(
        'screen.company.analysisCards.team.description',
        'Leadership team, organizational structure, and key personnel',
      ),
      icon: 'fas fa-users',
      insights: t(
        'screen.company.analysisCards.team.insights',
        'Discover the organizational structure and key members',
      ),
      taskStatus: getTaskStatus('team'),
      errorDetails: getTaskErrorDetails('team'),
      taskId: getTaskId('team'),
      taskUpdatedAt: getTaskUpdatedAt('team'),
      disabled: false,
    },
    {
      section: 'jobs' as TaskType,
      title: t('screen.company.analysisCards.jobs.title', 'Job Offers'),
      description: t(
        'screen.company.analysisCards.jobs.description',
        'Current job openings and career opportunities',
      ),
      icon: 'fas fa-briefcase',
      insights: company.value?.jobs?.insights?.hiring_focus?.value,
      taskStatus: getTaskStatus('jobs'),
      errorDetails: getTaskErrorDetails('jobs'),
      taskId: getTaskId('jobs'),
      taskUpdatedAt: getTaskUpdatedAt('jobs'),
      disabled: false,
    },
    {
      section: 'press' as TaskType,
      title: t('screen.company.analysisCards.press.title', 'Press & Media'),
      description: t(
        'screen.company.analysisCards.press.description',
        'Press releases, news articles, and media coverage',
      ),
      icon: 'fas fa-newspaper',
      insights: company.value?.press?.insights,
      taskStatus: getTaskStatus('press'),
      errorDetails: getTaskErrorDetails('press'),
      taskId: getTaskId('press'),
      taskUpdatedAt: getTaskUpdatedAt('press'),
      disabled: false,
    },
    {
      section: 'csr' as TaskType,
      title: t('screen.company.analysisCards.csr.title', 'Corporate Social Responsibility'),
      description: t(
        'screen.company.analysisCards.csr.description',
        'CSR initiatives, sustainability programs, and social impact',
      ),
      icon: 'fas fa-leaf',
      insights: company.value?.csr?.insights,
      taskStatus: getTaskStatus('csr'),
      errorDetails: getTaskErrorDetails('csr'),
      taskId: getTaskId('csr'),
      taskUpdatedAt: getTaskUpdatedAt('csr'),
      disabled: false,
    },
    {
      section: 'digital' as TaskType,
      title: t('screen.company.analysisCards.communications.title', 'Corporate Communications'),
      description: t(
        'screen.company.analysisCards.communications.description',
        'Press releases, public statements, and official communications',
      ),
      icon: 'fas fa-bullhorn',
      insights: null,
      taskStatus: null,
      errorDetails: null,
      taskId: null,
      taskUpdatedAt: null,
      disabled: true,
    },
  ]
})

// Open section in modal
const openSection = (section: TaskType) => {
  activeSection.value = section
  showSectionModal.value = true

  // Update URL with query param
  router.push({
    query: { ...route.query, section },
  })
}

// Handle restart task with optimistic UI
const handleRestartTask = async (taskId: number) => {
  try {
    await restartTaskMutation(taskId)
    console.log('✅ Task restarted successfully')
  } catch (error) {
    console.error('❌ Error restarting task:', error)
  }
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

// Calculate task status percentages
const completedPercentage = computed(() => {
  if (!tasks.value) return 0
  const completedCount = tasks.value.filter((t) => t.status === 'succeeded').length
  return (completedCount / tasks.value.length) * 100
})

const runningPercentage = computed(() => {
  if (!tasks.value) return 0
  const runningCount = tasks.value.filter(
    (t) => t.status === 'running' || t.status === 'pending',
  ).length
  return (runningCount / tasks.value.length) * 100
})

const errorPercentage = computed(() => {
  if (!tasks.value) return 0
  const errorCount = tasks.value.filter((t) => t.status === 'error').length
  return (errorCount / tasks.value.length) * 100
})

const pendingPercentage = computed(() => {
  if (!tasks.value) return 0
  const pendingCount = tasks.value.filter((t) => t.status === 'pending').length
  return (pendingCount / tasks.value.length) * 100
})

const runningCount = computed(() => {
  if (!tasks.value) return 0
  const runningCount = tasks.value.filter((t) => t.status === 'running').length
  return runningCount
})

const errorCount = computed(() => {
  if (!tasks.value) return 0
  const errorCount = tasks.value.filter((t) => t.status === 'error').length
  return errorCount
})

const pendingCount = computed(() => {
  if (!tasks.value) return 0
  const pendingCount = tasks.value.filter((t) => t.status === 'pending').length
  return pendingCount
})

const completedCount = computed(() => {
  if (!tasks.value) return 0
  const completedCount = tasks.value.filter((t) => t.status === 'succeeded').length
  return completedCount
})
</script>
