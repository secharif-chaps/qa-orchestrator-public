<template>
  <div class="flex flex-col gap-4">
    <!-- Company Header -->
    <div class="flex items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div
          class="ring-primary-stroke relative size-12 flex-shrink-0 overflow-hidden rounded-lg bg-white ring-2"
        >
          <img
            v-if="getCompanyDomain(company?.website)"
            :src="getLogoUrl(company?.website)"
            :alt="`${company?.name} logo`"
            class="h-full w-full object-contain"
            @error="showFallbackIcon = true"
            v-show="!showFallbackIcon"
          />
          <Badge
            v-show="showFallbackIcon || !getCompanyDomain(company?.website)"
            variant="secondary"
            color="sage"
            icon="fa fa-building"
            size="lg"
            class="h-full w-full rounded-none"
          />
        </div>
        <div class="flex flex-col gap-1">
          <h1 class="text-2xl font-bold">{{ company?.name }}</h1>
          <span v-if="company?.created_at" class="text-secondary text-sm">
            {{ t('company.createdAt') }} {{ formatFullDate(company.created_at) }}
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <CompanyTranslation v-model="selectedLanguage" :company-id="companyId" />
        <Export />
        <span v-if="isOwner" :title="refreshButtonTooltip">
          <Button
            variant="tertiary"
            icon="fa fa-refresh"
            :label="t('company.refresh.button')"
            :disabled="hasRunningTasks || !allTasksSucceeded || !hasEnoughTokens"
            :loading="isRefreshing"
            @click="openRefreshModal"
          />
        </span>
        <CompanyDeleteButton :company="company" />
        <Button
          v-if="isDebugUser"
          variant="tertiary"
          size="sm"
          icon="fa fa-bug"
          icon-only
          :title="t('company.debug.workflowTitle')"
          @click="showTasksModal = true"
        />
      </div>
    </div>

    <RouterView />

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
import { Badge, Button } from '@owlint/feathers-vue'
import CompanyRefreshModal from '@/components/companies/CompanyRefreshModal.vue'
import { companyByIdQuery } from '@/queries/companies'
import { useQuery } from '@pinia/colada'
import { computed, ref, watch, provide } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import CompanyTranslation from '@/components/company/CompanyTranslation.vue'
import CompanyDeleteButton from '@/components/company/CompanyDeleteButton.vue'
import Export from '@/components/company/Export.vue'
import TasksFlowModal from '@/components/company/TasksFlowModal.vue'
import { organizationBalanceQuery } from '@/queries/tokens'
import { currentOrganizationQuery } from '@/queries/organization'
import { useRefreshCompany } from '@/mutations/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { formatFullDate } from '@/utils/time'

const route = useRoute('/folders/[folderId]/companies/[companyId]')
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()

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

// Modal state
const showTasksModal = ref(false)
const showFallbackIcon = ref(false)
const showRefreshModal = ref(false)

// Get current organization
const { data: currentOrganization } = useQuery(() => currentOrganizationQuery())

// Get token balance for refresh button
const { data: tokenBalanceData } = useQuery({
  ...organizationBalanceQuery({ organizationId: currentOrganization.value?.id ?? '' }),
  enabled: () => !!currentOrganization.value?.id,
})

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
  if (hasRunningTasks.value) return t('company.refresh.tooltip.tasksRunning')
  if (!allTasksSucceeded.value) return t('company.refresh.tooltip.waitForTasks')
  if (!hasEnoughTokens.value) return t('company.refresh.tooltip.insufficientTokens')
  return ''
})

// Reset fallback icon when company changes
watch(company, () => {
  showFallbackIcon.value = false
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

// Helper function to get logo URL from logo.dev
const getLogoUrl = (website?: string) => {
  const domain = getCompanyDomain(website)
  if (!domain) return ''
  return `https://img.logo.dev/${domain}?token=pk_Buf4yyXmRC2HMagyfO0jrg&retina=true`
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
</script>
