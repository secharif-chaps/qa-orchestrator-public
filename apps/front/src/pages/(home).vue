<template>
  <div>
    <div class="flex flex-col gap-6">
      <!-- Greeting Bar -->
      <div>
        <h1 class="text-2xl font-bold text-[#182021] dark:text-white">
          {{ $t('dashboard.home.welcome.title', { name: userDisplayName }) }}
        </h1>
      </div>

      <!-- Chaps-e Insight Section -->
      <ChapseInsightCard :module-actions="moduleActions" @navigate="handleModuleNavigate" />

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Recent Projects -->
        <RecentProjectsList
          :projects="recentProjects"
          :is-loading="isRecentCompaniesLoading"
          :error="recentCompaniesError"
        />

        <!-- Recent Activities -->
        <RecentActivitiesList
          :activities="recentActivities"
          :is-loading="isActivitiesLoading"
          :error="activitiesError"
          :has-user-projects="!!recentCompaniesData?.length"
        />
      </div>

      <!-- Modules Showcase -->
      <div>
        <ModulesShowcase :feature-flags="featureFlags" :modules-data="modulesData?.modules" />
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - organization.read
  requiresAuth: true
</route>

<script setup lang="ts">
import ChapseInsightCard from '@/components/home/ChapseInsightCard.vue'
import type { ModuleAction } from '@/types/module'
import ModulesShowcase from '@/components/home/ModulesShowcase.vue'
import RecentActivitiesList from '@/components/home/RecentActivitiesList.vue'
import RecentProjectsList from '@/components/home/RecentProjectsList.vue'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { useDateTime } from '@/composables/useDateTime'
import { recentCompaniesQuery } from '@/queries/companies'
import { organizationFeatureFlagsQuery } from '@/queries/feature-flags'
import { currentOrganizationQuery, organizationActivitiesQuery } from '@/queries/organization'
import { organizationModulesQuery } from '@/queries/tokens'
import { useAuthStore } from '@/stores/auth'
import type { FeatureFlagConfig } from '@/types/feature-flags'
import { useQuery } from '@pinia/colada'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

// Only access auth on client side
const authStore = useAuthStore()
const user = authStore.user
const { t } = useI18n()
const router = useRouter()
const { canCreateCompany, canViewCompany } = useCompanyPermissions()
const { formatDate, formatRelativeTime } = useDateTime()

const RECENT_PROJECTS_LIMIT = 6

// Reactive data
const currentTime = ref('')
const currentDate = ref('')

// Computed properties
const userDisplayName = computed(() => {
  if (!user) return 'User'
  return user.profile.given_name || user.profile.preferred_username || user.profile.name || 'User'
})

// Fetch recent companies (5 most recent with folder info)
const {
  data: recentCompaniesData,
  isLoading: isRecentCompaniesLoading,
  error: recentCompaniesError,
} = useQuery(() =>
  recentCompaniesQuery({
    limit: RECENT_PROJECTS_LIMIT,
  }),
)

// Fetch organization activities (no organization ID needed - from JWT)
const {
  data: organizationActivitiesData,
  isLoading: isActivitiesLoading,
  error: activitiesError,
} = useQuery(() => organizationActivitiesQuery())

// Fetch current organization
const { data: currentOrganization } = useQuery(() => currentOrganizationQuery())

// Fetch organization feature flags for ModulesShowcase
const { data: featureFlagsData } = useQuery(() =>
  organizationFeatureFlagsQuery({
    organizationId: currentOrganization.value?.id || '',
  }),
)

// Transform feature flags data for ModulesShowcase
const featureFlags = computed<FeatureFlagConfig[]>(() => {
  return featureFlagsData.value?.feature_flags ?? []
})

// Fetch organization modules for Chaps-e insight pills
const { data: modulesData } = useQuery(() =>
  organizationModulesQuery({
    organizationId: currentOrganization.value?.id || '',
  }),
)

// Build module actions for Chaps-e insight section
const moduleActions = computed<ModuleAction[]>(() => {
  const actions: ModuleAction[] = []
  const modules = modulesData.value?.modules ?? []
  const discoverFlag = featureFlags.value.find((f) => f.flag === 'discover')

  // Screen module — requires company.create permission and module enabled
  const screenModule = modules.find((m) => m.name === 'screen')
  if (screenModule?.enabled && canCreateCompany.value) {
    actions.push({
      name: 'screen',
      label: t('dashboard.home.chapse.actions.screen'),
      icon: 'fa-regular fa-buildings',
      color: 'indigo',
      route: '/companies/create',
    })
  }

  // Target module — requires organization.read permission and module enabled
  const targetModule = modules.find((m) => m.name === 'target')
  if (targetModule?.enabled && canViewCompany.value) {
    actions.push({
      name: 'target',
      label: t('dashboard.home.chapse.actions.target'),
      icon: 'fa-regular fa-file-lines',
      color: 'cherry',
      route: '/target',
    })
  }

  // Explore module — requires organization.read permission and module enabled
  const exploreModule = modules.find((m) => m.name === 'explore')
  if (exploreModule?.enabled && canViewCompany.value) {
    actions.push({
      name: 'explore',
      label: t('dashboard.home.chapse.actions.explore'),
      icon: 'fa-regular fa-chart-network',
      color: 'yellow',
    })
  }

  // Discover — always visible (like ModulesShowcase), links to external URL when flag enabled
  actions.push({
    name: 'discover',
    label: t('dashboard.home.chapse.actions.discover'),
    icon: 'fa-regular fa-chart-pie-simple',
    color: 'cyan',
    externalUrl: discoverFlag?.enabled
      ? (discoverFlag.config?.url as string) || undefined
      : undefined,
  })

  return actions
})

// Handle module pill navigation
const handleModuleNavigate = (action: ModuleAction) => {
  if (action.externalUrl) {
    window.open(action.externalUrl, '_blank', 'noopener,noreferrer')
  } else if (action.route) {
    router.push(action.route)
  }
}

const updateTime = () => {
  const now = new Date()
  currentTime.value = formatDate(now, 'time')
  currentDate.value = formatDate(now, 'fullDate')
}

const timeInterval = ref<ReturnType<typeof setInterval> | null>(null)
// Lifecycle
onMounted(() => {
  updateTime()

  // Update time every minute
  timeInterval.value = setInterval(updateTime, 60000)
})

onUnmounted(() => {
  if (timeInterval.value) {
    clearInterval(timeInterval.value)
  }
})

// Transform recent companies data for display
const recentProjects = computed(() => {
  if (!recentCompaniesData.value) return []

  return recentCompaniesData.value
    .filter((company) => company.id !== undefined)
    .map((company) => {
      return {
        id: company.id as number,
        name: company.name,
        folderName: company.folder_name || t('dashboard.home.recentProjects.noFolder'),
        folderId: company.folder_id,
        timeAgo: formatRelativeTime(company.created_at),
        isShared:
          company.folder_is_owner != null
            ? company.folder_is_owner === false && company.folder_share_role != null
            : false,
      }
    })
})

const recentActivities = computed(() => {
  if (!organizationActivitiesData.value) return []

  // Defensive check - ensure it's an array
  return Array.isArray(organizationActivitiesData.value)
    ? organizationActivitiesData.value.slice(0, 7)
    : []
})
</script>
