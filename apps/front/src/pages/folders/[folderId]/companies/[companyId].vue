<template>
  <div class="gap-xl flex flex-col">
    <CompanyHeader
      :company="company"
      :folder="folder"
      :folder-id="folderId"
      :company-id="companyId"
      :progress-segments="progressSegments"
      :show-progress-ring="showProgressRing"
      :current-message="currentMessage"
      :is-owner="isOwner"
      :refresh-button-tooltip="refreshButtonTooltip"
      :has-running-tasks="hasRunningTasks"
      :all-tasks-succeeded="allTasksSucceeded"
      :has-enough-tokens="hasEnoughTokens"
      :is-refreshing="isRefreshing"
      :is-debug-user="isDebugUser"
      v-model:selected-language="selectedLanguage"
      @open-refresh-modal="openRefreshModal"
      @open-tasks-modal="showTasksModal = true"
    />

    <!-- Created by + Tab content -->
    <div class="gap-xl bg-neutral p-xl -mx-4 flex flex-col sm:-mx-6 lg:-mx-8">
      <div v-if="company?.created_at" class="text-neutral-black-font flex items-center gap-2">
        <Badge icon="fa-pen" variant="secondary" size="sm" />
        <span class="text-base">
          {{
            t('screen.company.header.createdBy', {
              username: company.owner_username,
              date: formatDate(company.created_at, 'eventDate'),
            })
          }}
        </span>
      </div>

      <RouterView />
    </div>
    <!-- Refresh company Modal -->
    <CompanyRefreshModal
      v-if="company"
      v-model="showRefreshModal"
      :company="company"
      @refresh-company="handleRefreshCompany"
    />
    <TasksFlowModal v-model="showTasksModal" />
  </div>
</template>

<script lang="ts" setup>
import CompanyRefreshModal from '@/components/companies/CompanyRefreshModal.vue'
import CompanyHeader from '@/components/company/CompanyHeader.vue'
import TasksFlowModal from '@/components/company/TasksFlowModal.vue'
import { useActivityMessages } from '@/composables/useActivityMessages'
import { useTaskProgress } from '@/composables/useTaskProgress'
import { useRefreshCompany } from '@/mutations/companies'
import { companyByIdQuery } from '@/queries/companies'
import { folderByIdQuery } from '@/queries/folders'
import { currentOrganizationQuery } from '@/queries/organization'
import { companyTasksQuery } from '@/queries/tasks'
import { organizationBalanceQuery } from '@/queries/tokens'
import { useDateTime } from '@/composables/useDateTime'
import { useAuthStore } from '@/stores/auth'
import { Badge } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, provide, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute('/folders/[folderId]/companies/[companyId]')
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()
const { formatDate } = useDateTime()

const companyId = computed(() => route.params.companyId)
const folderId = computed(() => route.params.folderId)

// Selected language for viewing translated content
const selectedLanguage = ref<string | undefined>(undefined)

// Provide selected language to child components (index.vue, etc.)
provide('selectedLanguage', selectedLanguage)

const isDebugUser = computed(() => {
  // Always show debug button in dev mode
  if (import.meta.env.DEV) return true
  const username = authStore.user?.profile?.preferred_username?.toLowerCase()
  return username === 'nmr' || username === 'suh' || username === 'nmr-cv'
})

// Get company data with optional language for translations
const {
  data: company,
  error,
  status,
} = useQuery(() => companyByIdQuery({ id: companyId.value, language: selectedLanguage.value }))

// Get company tasks
const { data: tasks } = useQuery(() => companyTasksQuery({ companyId: companyId.value }))

// Get folder data for privacy tag in header
const { data: folder } = useQuery(() => folderByIdQuery({ id: folderId.value }))

// Task progress ring — revert to sage ring once all tasks complete
const { segments: progressSegments, isInProgress: showProgressRing } = useTaskProgress(tasks)

// Cycling activity messages while screening is in progress
const { currentMessage } = useActivityMessages(showProgressRing)

// Modal state
const showTasksModal = ref(false)
const showRefreshModal = ref(false)

// Get current organization
const { data: currentOrganization } = useQuery(() => currentOrganizationQuery())

// Get token balance for refresh button
const { data: tokenBalanceData } = useQuery(() =>
  organizationBalanceQuery({ organizationId: currentOrganization.value?.id ?? '' }),
)

// Use refresh mutation
const { isLoading: isRefreshing } = useRefreshCompany()

// Computed properties for refresh button
const isOwner = computed(() => authStore.userId === company.value?.owner_id)
const allTasksSucceeded = computed(
  () => tasks.value?.every((task) => task.status === 'succeeded') ?? false,
)
const hasRunningTasks = computed(
  () => tasks.value?.some((task) => task.status === 'running') ?? false,
)
const tokenBalance = computed(() => tokenBalanceData.value?.balance ?? 0)
const hasEnoughTokens = computed(() => tokenBalance.value >= 35)

const refreshButtonTooltip = computed(() => {
  if (hasRunningTasks.value) return t('screen.company.refresh.tooltip.tasksRunning')
  if (!allTasksSucceeded.value) return t('screen.company.refresh.tooltip.waitForTasks')
  if (!hasEnoughTokens.value) return t('screen.company.refresh.tooltip.insufficientTokens')
  return ''
})

// Handle 404 errors - redirect to companies list if company doesn't exist
watch([error, status], ([newError, newStatus]) => {
  // Check for 404 error in multiple possible formats
  const err = newError as {
    status?: number
    response?: { status: number }
    message?: string
  } | null
  if (
    (err && (err.status === 404 || err.response?.status === 404)) ||
    (newStatus === 'error' && err && err.message?.includes('404'))
  ) {
    // Company not found, redirect to companies list
    router.push(`/folders/${folderId.value}`)
  }
})

const handleRefreshCompany = () => {
  // Modal handles the refresh logic, just close it here if needed
}

const openRefreshModal = () => {
  if (company.value) {
    showRefreshModal.value = true
  }
}
</script>
