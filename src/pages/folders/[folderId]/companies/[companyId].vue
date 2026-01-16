<template>
  <div class="flex flex-col gap-4">
    <!-- Company Header -->
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
          <Badge
            v-show="showFallbackIcon || !getCompanyDomain(company?.website)"
            variant="secondary"
            color="sage"
            icon="fa fa-building"
            size="lg"
            class="w-full h-full rounded-none"
          />
        </div>
        <div class="flex flex-col gap-1">
          <h1 class="text-2xl font-bold">{{ company?.name }}</h1>
          <span v-if="company?.created_at" class="text-sm text-secondary">
            {{ t('company.createdAt') }} {{ formatDate(company.created_at) }}
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <CompanyTranslation v-model="selectedLanguage" :company-id="companyId" />
        <Export />
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

    <TasksFlowModal v-model="showTasksModal" />
  </div>
</template>

<script lang="ts" setup>
import { Badge, Button } from '@owlint/feathers-vue'
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

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const authStore = useAuthStore()

const companyId = computed(() => (route.params as { companyId: string }).companyId)
const folderId = computed(() => (route.params as { folderId: string }).folderId)

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
} = useQuery(
  companyByIdQuery,
  () => ({ id: companyId.value, language: selectedLanguage.value }),
  {
    enabled: () =>
      !!companyId.value && companyId.value !== 'null' && companyId.value !== 'undefined',
  },
)

// Modal state
const showTasksModal = ref(false)
const showFallbackIcon = ref(false)

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

// Helper function to format date
const formatDate = (dateString: string): string => {
  if (!dateString) return t('common.na')
  const localeCode = locale.value === 'fr-FR' ? 'fr-FR' : 'en-US'
  return new Date(dateString).toLocaleDateString(localeCode)
}
</script>
