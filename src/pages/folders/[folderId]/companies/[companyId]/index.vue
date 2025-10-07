<template>
  <div class="space-y-6">
    <!-- Company Info Card - Full Width -->
    <div class="flex gap-4">
      <Card>
        <div class="flex items-start gap-6">
          <!-- Logo -->

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
                  <span class="text-sm truncate"> Nombre d'employés </span>
                  <span class="text-xs text-primary-light-content">
                    {{ company?.profile?.employeeCount?.value || 'Non renseigné' }}
                  </span>
                </div>
              </div>

              <!-- HQ -->
              <div
                v-if="company?.profile?.hq"
                class="flex items-center gap-3 rounded-card px-4 py-3"
              >
                <i class="fa-solid fa-map-marker fa-fw text-primary-light-content"></i>
                <div class="flex flex-col gap-1">
                  <span class="text-sm truncate"> Siege Social </span>
                  <span class="text-xs text-primary-light-content">
                    {{ company?.profile?.hq?.value || 'Non renseigné' }}
                  </span>
                </div>
              </div>

              <!-- CEO -->
              <div
                v-if="company?.profile?.ceo"
                class="flex items-center gap-3 rounded-card px-4 py-3"
              >
                <i class="fa-solid fa-user-tie fa-fw text-primary-light-content"></i>
                <div class="flex flex-col gap-1">
                  <span class="text-sm truncate"> CEO </span>
                  <span class="text-xs text-primary-light-content">
                    {{ company?.profile?.ceo?.value || 'Non renseigné' }}
                  </span>
                </div>
              </div>

              <!-- Revenue -->
              <div
                v-if="company?.profile?.revenue"
                class="flex items-center gap-3 rounded-card px-4 py-3"
              >
                <i class="fa-solid fa-money-bill fa-fw text-primary-light-content"></i>
                <div class="flex flex-col gap-1">
                  <span class="text-sm truncate"> Chiffre d'affaires </span>
                  <span class="text-xs text-primary-light-content">
                    {{ company?.profile?.revenue?.value || 'Non renseigné' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Social Media -->
          </div>
        </div>
      </Card>

      <Card>
        <p>Présence en ligne</p>
        <div class="flex bg-base-200 items-center gap-3 rounded-card px-4 py-3">
          <i class="fa-solid fa-link fa-fw text-primary"></i>
          <div class="flex flex-col gap-1 w-44">
            <span class="text-sm truncate"> Site web </span>
            <span class="text-xs text-primary-light-content truncate">
              {{ company?.website || 'Non renseigné' }}
            </span>
          </div>
        </div>
        <p>Présence sur les réseaux sociaux</p>
        <div>
          <div v-if="company?.digital?.socialMediaAccounts" class="flex flex-wrap gap-2">
            <Badge
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
            </Badge>
          </div>
        </div>
      </Card>
    </div>

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
    <div v-if="company" class="text-xs text-primary-light-content italic text-center">
      Created by {{ company.owner_username }} on {{ formatDate(company.created_at) }}
    </div>
  </div>

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
import SectionModal from '@/components/company/SectionModal.vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import { companyByIdQuery } from '@/queries/companies'
import { useQuery } from '@pinia/colada'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { TaskType, TaskStatus } from '@/types/task'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import { useRestartTask } from '@/mutations/tasks'

const router = useRouter()
const route = useRoute()

const showSectionModal = ref(false)
const activeSection = ref<TaskType | null>(null)

const companyId = computed(() => route.params.companyId as string)
const folderId = computed(() => route.params.folderId as string)

// Use the company data composable
const { data: company } = useQuery(companyByIdQuery, () => ({ id: companyId.value }), {
  enabled: () => !!companyId.value && companyId.value !== 'null' && companyId.value !== 'undefined',
})

// Restart task mutation
const { mutate: restartTaskMutation } = useRestartTask()

// Helper function to get task status by type
const getTaskStatus = (taskType: TaskType): TaskStatus | null => {
  if (!company.value?.tasks) return null
  const task = company.value.tasks.find((t) => t.type === taskType)
  return task?.status || null
}

// Helper function to get task error message
const getTaskError = (taskType: TaskType): string | null => {
  if (!company.value?.tasks) return null
  const task = company.value.tasks.find((t) => t.type === taskType)
  return task?.error || null
}

// Helper function to get task ID by type
const getTaskId = (taskType: TaskType): number | null => {
  if (!company.value?.tasks) return null
  const task = company.value.tasks.find((t) => t.type === taskType)
  return task?.id || null
}

// Analysis cards configuration
const analysisCards = computed(() => [
  {
    section: 'profile' as TaskType,
    title: "Profil de l'entreprise",
    description: 'Informations générales et présence digitale',
    icon: 'fas fa-building',
    insights: company.value?.profile?.businessLine?.value || company.value?.digital?.insights,
    taskStatus: getTaskStatus('profile') || getTaskStatus('digital'),
    errorMessage: getTaskError('profile') || getTaskError('digital'),
    taskId: getTaskId('profile') || getTaskId('digital'),
    disabled: false,
  },
  {
    section: 'timeline' as TaskType,
    title: 'Activités & Événements',
    description: 'Historique et moments clés',
    icon: 'fas fa-calendar-days',
    insights: company.value?.timeline?.insights,
    taskStatus: getTaskStatus('timeline'),
    errorMessage: getTaskError('timeline'),
    taskId: getTaskId('timeline'),
    disabled: false,
  },
  {
    section: 'products' as TaskType,
    title: 'Produits & Services',
    description: 'Catalogue et gamme de produits',
    icon: 'fas fa-box',
    insights: company.value?.products?.insights,
    taskStatus: getTaskStatus('products'),
    errorMessage: getTaskError('products'),
    taskId: getTaskId('products'),
    disabled: false,
  },
  {
    section: 'team' as TaskType,
    title: 'Équipe & Management',
    description: 'Organigramme et membres clés',
    icon: 'fas fa-users',
    insights: null, // Team doesn't have insights field
    taskStatus: getTaskStatus('team'),
    errorMessage: getTaskError('team'),
    taskId: getTaskId('team'),
    disabled: false,
  },
  {
    section: 'jobs' as TaskType,
    title: "Offres d'emploi",
    description: 'Recrutement et opportunités',
    icon: 'fas fa-briefcase',
    insights: company.value?.jobs?.insights?.hiring_focus?.value,
    taskStatus: getTaskStatus('jobs'),
    errorMessage: getTaskError('jobs'),
    taskId: getTaskId('jobs'),
    disabled: false,
  },
  {
    section: 'press' as TaskType,
    title: 'Presse & Médias',
    description: 'Articles et communiqués',
    icon: 'fas fa-newspaper',
    insights: company.value?.press?.insights,
    taskStatus: getTaskStatus('press'),
    errorMessage: getTaskError('press'),
    taskId: getTaskId('press'),
    disabled: false,
  },
  {
    section: 'csr' as TaskType,
    title: 'Responsabilité sociale',
    description: 'RSE et développement durable',
    icon: 'fas fa-leaf',
    insights: company.value?.csr?.insights,
    taskStatus: getTaskStatus('csr'),
    errorMessage: getTaskError('csr'),
    taskId: getTaskId('csr'),
    disabled: false,
  },
  {
    section: 'digital' as TaskType,
    title: 'Communications',
    description: 'Stratégie de communication',
    icon: 'fas fa-bullhorn',
    insights: null,
    taskStatus: null,
    errorMessage: null,
    taskId: null,
    disabled: true,
  },
])

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
</script>
