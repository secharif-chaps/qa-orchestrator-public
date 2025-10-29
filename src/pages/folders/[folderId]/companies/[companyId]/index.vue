<template>
  <div class="space-y-6">
    <!-- Company Info Card - Full Width -->
    <div class="space-y-4 xl:space-y-0 xl:flex gap-4">
      <Card class="flex-1">
        <div class="flex items-start gap-6">
          <!-- Company Info -->
          <div class="flex-1 min-w-0 flex flex-col gap-2">
            <div>
              <h3 v-if="company?.profile?.catchphrase" class="font-bold">
                {{ getSourcedValue(company.profile.businessLine) }}
              </h3>
            </div>
            <div>
              <p>{{ company?.products?.insights }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <!-- Employee Count -->
              <div class="flex items-center gap-3 rounded-card px-4 py-3">
                <i class="fa-solid fa-users fa-fw"></i>
                <div class="flex flex-col gap-1">
                  <span class="text-sm truncate">
                    {{ t('company.fields.employeeCount', 'Employee Count') }}
                  </span>
                  <span class="text-xs text-secondary">
                    {{
                      company?.profile?.employeeCount?.value ||
                      t('company.fields.notSpecified', 'Not specified')
                    }}
                  </span>
                </div>
              </div>

              <!-- HQ -->
              <div
                v-if="company?.profile?.hq"
                class="flex items-center gap-3 rounded-card px-4 py-3"
              >
                <i class="fa-solid fa-map-marker fa-fw text-secondary"></i>
                <div class="flex flex-col gap-1">
                  <span class="text-sm truncate">
                    {{ t('company.fields.headquarters', 'Headquarters') }}
                  </span>
                  <span class="text-xs text-secondary">
                    {{
                      company?.profile?.hq?.value ||
                      t('company.fields.notSpecified', 'Not specified')
                    }}
                  </span>
                </div>
              </div>

              <!-- CEO -->
              <div
                v-if="company?.profile?.ceo"
                class="flex items-center gap-3 rounded-card px-4 py-3"
              >
                <i class="fa-solid fa-user-tie fa-fw text-secondary"></i>
                <div class="flex flex-col gap-1">
                  <span class="text-sm truncate"> {{ t('company.fields.ceo', 'CEO') }} </span>
                  <span class="text-xs text-secondary">
                    {{
                      company?.profile?.ceo?.value ||
                      t('company.fields.notSpecified', 'Not specified')
                    }}
                  </span>
                </div>
              </div>

              <!-- Revenue -->
              <div
                v-if="company?.profile?.revenue"
                class="flex items-center gap-3 rounded-card px-4 py-3"
              >
                <i class="fa-solid fa-money-bill fa-fw text-secondary"></i>
                <div class="flex flex-col gap-1">
                  <span class="text-sm truncate">
                    {{ t('company.fields.revenue', 'Revenue') }}
                  </span>
                  <span class="text-xs text-secondary">
                    {{
                      company?.profile?.revenue?.value ||
                      t('company.fields.notSpecified', 'Not specified')
                    }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Social Media -->
          </div>
        </div>
      </Card>

      <Card class="xl:max-w-md">
        <p>Présence en ligne</p>
        <div class="flex bg-base-200 items-center gap-3 rounded-card px-4 py-3">
          <i class="fa-solid fa-link fa-fw text-secondary"></i>
          <div class="flex flex-col gap-1 w-44">
            <span class="text-sm truncate"> Site web </span>
            <span class="text-xs text-secondary truncate">
              {{ company?.website || 'Non renseigné' }}
            </span>
          </div>
        </div>
        <p>Présence sur les réseaux sociaux</p>
        <div>
          <div v-if="company?.digital?.socialMediaAccounts" class="flex flex-wrap gap-2">
            <Tag
              variant="slate"
              v-for="account in company?.digital?.socialMediaAccounts.value"
              :key="account.platform"
              :href="getSourcedValue(account.url)"
              target="_blank"
            >
              <i class="fa" :class="getSocialIcon(account.platform)"></i>
              <span class="underline">
                {{ account.platform }}
              </span>
            </Tag>
          </div>
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
      @action-click="handleQuickActionClick"
      @load-success="handleQuickActionsSuccess"
      @load-error="handleQuickActionsError"
    />

    <div>
      <h4 class="font-semibold">Analyses</h4>
    </div>

    <!-- Analysis Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 auto-rows-fr">
      <AnalysisCard
        v-for="card in analysisCards"
        :key="card.section"
        :title="card.title"
        :description="card.description"
        :icon="card.icon"
        :insights="card.insights"
        :task-status="card.taskStatus"
        :error-message="card.errorMessage"
        :task-id="card.taskId"
        :disabled="card.disabled"
        @click="openSection(card.section)"
        @restart="handleRestartTask"
      />
    </div>

    <!-- Footer -->
    <div v-if="company" class="text-xs text-secondary italic text-center">
      Created by {{ company.owner_username }} on {{ formatDate(company.created_at) }}
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
    - company.view
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
import { useRestartTask } from '@/mutations/tasks'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { useAuthStore } from '@/stores/auth'
import type { QuickAction } from '@/types/ai-preferences'
import type { TaskStatus, TaskType } from '@/types/task'
import { useQuery } from '@pinia/colada'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const router = useRouter()
const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()

const showSectionModal = ref(false)
const activeSection = ref<TaskType | null>(null)

const companyId = computed(() => route.params.companyId as string)
const folderId = computed(() => route.params.folderId as string)

const isDebugUser = computed(() => {
  const username = authStore.user?.profile?.preferred_username?.toLowerCase()
  return username === 'nmr' || username === 'suh'
})

// Use the company data composable
const { data: company } = useQuery(companyByIdQuery, () => ({ id: companyId.value }), {
  enabled: () => !!companyId.value && companyId.value !== 'null' && companyId.value !== 'undefined',
})

// Restart task mutation
const { mutate: restartTaskMutation } = useRestartTask()

const { data: tasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

// Helper function to get task status by type
const getTaskStatus = (taskType: TaskType): TaskStatus | null => {
  if (!tasks.value) return null
  const task = tasks.value?.find((t) => t.type === taskType)
  return task?.status || null
}

// Helper function to get task error message
const getTaskError = (taskType: TaskType): string | null => {
  if (!tasks.value) return null
  const task = tasks.value?.find((t) => t.type === taskType)
  return task?.error || null
}

// Helper function to get task ID by type
const getTaskId = (taskType: TaskType): number | null => {
  if (!tasks.value) return null
  const task = tasks.value?.find((t) => t.type === taskType)
  return task?.id || null
}

// Check if data_collection task is running (blocks all other tasks)
const isDataCollectionRunning = computed(() => {
  const status = getTaskStatus('data_collection')
  return status === 'pending' || status === 'running'
})

// Analysis cards configuration
const analysisCards = computed(() => {
  // If data_collection is running, show all cards as loading
  const dataCollectionStatus = isDataCollectionRunning.value ? 'running' : null

  return [
    {
      section: 'profile' as TaskType,
      title: t('company.analysisCards.profile.title', 'Company Profile'),
      description: t(
        'company.analysisCards.profile.description',
        'View detailed company information, business lines, and key metrics',
      ),
      icon: 'fas fa-building',
      insights: company.value?.profile?.businessLine?.value || company.value?.digital?.insights,
      taskStatus: dataCollectionStatus || getTaskStatus('profile') || getTaskStatus('digital'),
      errorMessage: getTaskError('profile') || getTaskError('digital'),
      taskId: getTaskId('profile') || getTaskId('digital'),
      disabled: false,
    },
    {
      section: 'timeline' as TaskType,
      title: t('company.analysisCards.timeline.title', 'Timeline & History'),
      description: t(
        'company.analysisCards.timeline.description',
        'Company history, milestones, and key events over time',
      ),
      icon: 'fas fa-calendar-days',
      insights: t(
        'company.analysisCards.timeline.insights',
        'Discover the company history and key events',
      ),
      taskStatus: dataCollectionStatus || getTaskStatus('timeline'),
      errorMessage: getTaskError('timeline'),
      taskId: getTaskId('timeline'),
      disabled: false,
    },
    {
      section: 'products' as TaskType,
      title: t('company.analysisCards.products.title', 'Products & Services'),
      description: t(
        'company.analysisCards.products.description',
        'Browse products, services, and offerings',
      ),
      icon: 'fas fa-box',
      insights:
        company.value?.products?.insights ||
        t('company.analysisCards.products.insights', 'Discover the company products and services'),
      taskStatus: dataCollectionStatus || getTaskStatus('products'),
      errorMessage: getTaskError('products'),
      taskId: getTaskId('products'),
      disabled: false,
    },
    {
      section: 'team' as TaskType,
      title: t('company.analysisCards.team.title', 'Team & Management'),
      description: t(
        'company.analysisCards.team.description',
        'Leadership team, organizational structure, and key personnel',
      ),
      icon: 'fas fa-users',
      insights: t(
        'company.analysisCards.team.insights',
        'Discover the organizational structure and key members',
      ),
      taskStatus: dataCollectionStatus || getTaskStatus('team'),
      errorMessage: getTaskError('team'),
      taskId: getTaskId('team'),
      disabled: false,
    },
    {
      section: 'jobs' as TaskType,
      title: t('company.analysisCards.jobs.title', 'Job Offers'),
      description: t(
        'company.analysisCards.jobs.description',
        'Current job openings and career opportunities',
      ),
      icon: 'fas fa-briefcase',
      insights: company.value?.jobs?.insights?.hiring_focus?.value,
      taskStatus: dataCollectionStatus || getTaskStatus('jobs'),
      errorMessage: getTaskError('jobs'),
      taskId: getTaskId('jobs'),
      disabled: false,
    },
    {
      section: 'press' as TaskType,
      title: t('company.analysisCards.press.title', 'Press & Media'),
      description: t(
        'company.analysisCards.press.description',
        'Press releases, news articles, and media coverage',
      ),
      icon: 'fas fa-newspaper',
      insights: company.value?.press?.insights,
      taskStatus: dataCollectionStatus || getTaskStatus('press'),
      errorMessage: getTaskError('press'),
      taskId: getTaskId('press'),
      disabled: false,
    },
    {
      section: 'csr' as TaskType,
      title: t('company.analysisCards.csr.title', 'Corporate Social Responsibility'),
      description: t(
        'company.analysisCards.csr.description',
        'CSR initiatives, sustainability programs, and social impact',
      ),
      icon: 'fas fa-leaf',
      insights: company.value?.csr?.insights,
      taskStatus: dataCollectionStatus || getTaskStatus('csr'),
      errorMessage: getTaskError('csr'),
      taskId: getTaskId('csr'),
      disabled: false,
    },
    {
      section: 'digital' as TaskType,
      title: t('company.analysisCards.communications.title', 'Corporate Communications'),
      description: t(
        'company.analysisCards.communications.description',
        'Press releases, public statements, and official communications',
      ),
      icon: 'fas fa-bullhorn',
      insights: null,
      taskStatus: null,
      errorMessage: null,
      taskId: null,
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

// Format website URL
const formatWebsiteUrl = (website?: string) => {
  if (!website) return '#'
  return website.startsWith('http') ? website : `https://${website}`
}

// Format date
const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString()
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

/**
 * Chapse Assist Event Handlers
 */

// Handle setup button click (navigates to AI preferences setup)
const handleAssistSetup = () => {
  console.log('User clicked setup AI preferences')
  router.push({ name: '/ai-preferences-setup' })
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
