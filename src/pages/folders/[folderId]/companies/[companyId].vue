<template>
  <div class="flex flex-col gap-4">
    <div class="flex gap-4 items-center justify-between">
      <div class="flex items-center gap-4">
        <div
          class="relative size-12 rounded-lg overflow-hidden bg-white ring-2 ring-primary-stroke flex-shrink-0"
        >
          <img
            v-if="getCompanyDomain(company?.website)"
            :src="getLogoUrl(company?.website)"
            :alt="`${company?.name} logo`"
            class="w-full h-full object-contain"
            @error="showFallbackIcon = true"
            v-show="!showFallbackIcon"
          />
          <div
            v-show="showFallbackIcon || !getCompanyDomain(company?.website)"
            class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary/10 to-primary/20"
          >
            <i class="fa fa-building text-4xl text-secondary"></i>
          </div>
        </div>
        <h1 class="text-2xl font-bold">{{ company?.name }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <Button
          v-if="isDebugUser"
          variant="tertiary"
          size="sm"
          icon="fa fa-bug"
          icon-only
          :title="t('company.debug.workflowTitle', 'Debug: Search Workflow')"
          @click="showTasksModal = true"
        />
        <Button
          v-if="canDeleteCompany && company"
          variant="tertiary"
          color="danger"
          icon="fa fa-trash"
          :label="$t('company.delete.button', 'Delete')"
          @click="confirmDelete"
        />
        <Export />
      </div>
    </div>

    <RouterView />
    <!-- Archive company Modal -->
    <CompanyArchiveModal
      v-model="showDeleteModal"
      :company-to-archive="company"
      @archive-company="handleArchiveCompany"
    />
    <TasksFlowModal v-model="showTasksModal" />
  </div>
</template>

<script lang="ts" setup>
import CompanyArchiveModal from '@/components/companies/CompanyArchiveModal.vue'
import Button from '@/components/ui/Button.vue'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { companyByIdQuery } from '@/queries/companies'
import { useQuery } from '@pinia/colada'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import TasksFlowModal from '@/components/company/TasksFlowModal.vue'
import { useAuthStore } from '@/stores/auth'
import Export from '@/components/company/Export.vue'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()

const companyId = computed(() => (route.params as { companyId: string }).companyId)
const folderId = computed(() => (route.params as { folderId: string }).folderId)

const isDebugUser = computed(() => {
  const username = authStore.user?.profile?.preferred_username?.toLowerCase()
  return username === 'nmr' || username === 'suh'
})

// Get company data
const {
  data: company,
  error,
  status,
} = useQuery(companyByIdQuery, () => ({ id: companyId.value }), {
  enabled: () => !!companyId.value && companyId.value !== 'null' && companyId.value !== 'undefined',
})

// Permissions
const { canDeleteCompany } = useCompanyPermissions()

// Modal state
const showDeleteModal = ref(false)
const showTasksModal = ref(false)

// Reset fallback icon when company changes
watch(company, () => {
  showFallbackIcon.value = false
})

// Handle 404 errors - redirect to companies list if company doesn't exist
watch([error, status], ([newError, newStatus]) => {
  // Check for 404 error in multiple possible formats
  if (
    (newError && (newError.status === 404 || newError.response?.status === 404)) ||
    (newStatus === 'error' && newError && newError.message?.includes('404'))
  ) {
    // Company not found, redirect to companies list
    router.push(`/folders/${folderId.value}`)
  }
})

const confirmDelete = () => {
  showDeleteModal.value = true
}

const handleArchiveCompany = async () => {
  // Redirect to home page after deletion
  router.push('/')
}

const showFallbackIcon = ref(false)

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
