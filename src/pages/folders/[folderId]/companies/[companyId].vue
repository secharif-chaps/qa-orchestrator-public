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
      <div class="flex items-center gap-2">
        <Button
          v-if="isDebugUser"
          variant="tertiary"
          size="sm"
          icon="fa fa-bug"
          icon-only
          :title="t('company.debug.workflowTitle')"
          @click="showTasksModal = true"
        />
        <Export />
        <Dropdown v-if="company && translationLanguages?.length" align="left" width="sm">
          <template #trigger>
            <Button
              variant="tertiary"
              icon="fa fa-language"
              :label="t('company.translation.button', 'Translate')"
            />
          </template>
          <template #content="{ close }">
            <!-- Original language option -->
            <DropdownItem
              :class="{ 'bg-primary-light': !selectedLanguage }"
              @click="resetToOriginal(); close()"
            >
              <div class="flex items-center justify-between w-full gap-2">
                <span>{{ t('company.translation.original', 'Original') }}</span>
                <i
                  v-if="!selectedLanguage"
                  class="fa fa-eye text-primary"
                  :title="t('company.translation.currentlyViewing', 'Currently viewing')"
                />
              </div>
            </DropdownItem>
            <!-- Separator -->
            <div class="border-t border-base-300 my-1" />
            <!-- Language options -->
            <DropdownItem
              v-for="lang in translationLanguages"
              :key="lang.code"
              :disabled="isLanguageTranslating(lang.code)"
              :class="{ 'bg-primary-light': selectedLanguage === lang.code }"
              @click="handleTranslate(lang.code); close()"
            >
              <div class="flex items-center justify-between w-full gap-2">
                <span>{{ getLanguageName(lang.code) }}</span>
                <!-- Loading spinner for in-progress translations -->
                <span
                  v-if="isLanguageTranslating(lang.code)"
                  class="flex items-center gap-1 text-info"
                  :title="getProgressTitle(lang.code)"
                >
                  <i class="fa fa-spinner fa-spin" />
                  <span class="text-xs">{{ getProgressPercent(lang.code) }}%</span>
                </span>
                <!-- Download icon for untranslated -->
                <i
                  v-else-if="getLanguageStatus(lang.code) === 'none'"
                  class="fa fa-download text-secondary"
                  :title="t('company.translation.clickToTranslate', 'Click to translate')"
                />
                <!-- Eye icon for currently viewing -->
                <i
                  v-else-if="selectedLanguage === lang.code"
                  class="fa fa-eye text-primary"
                  :title="t('company.translation.currentlyViewing', 'Currently viewing')"
                />
                <!-- Check icon for translated (click to view) -->
                <i
                  v-else
                  class="fa fa-check text-success"
                  :title="t('company.translation.clickToView', 'Click to view in this language')"
                />
              </div>
            </DropdownItem>
          </template>
        </Dropdown>
        <Button
          v-if="canDeleteCompany && company"
          variant="tertiary"
          intent="danger"
          icon="fa fa-trash"
          :label="t('company.delete.button')"
          @click="confirmDelete"
        />
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
import { Badge, Button } from '@owlint/feathers-vue'
import CompanyArchiveModal from '@/components/companies/CompanyArchiveModal.vue'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { companyByIdQuery } from '@/queries/companies'
import { translationLanguagesQuery, companyTranslationStatusQuery } from '@/queries/translation'
import { requestTranslation } from '@/api/translation'
import { useQuery, useQueryCache } from '@pinia/colada'
import { computed, ref, watch, onUnmounted, provide } from 'vue'
import { useI18n } from 'vue-i18n'
import { toast } from '@/utils/toast'
import type { TranslationJob } from '@/api/translation'
import { useRoute, useRouter } from 'vue-router'
import TasksFlowModal from '@/components/company/TasksFlowModal.vue'
import { useAuthStore } from '@/stores/auth'
import Export from '@/components/company/Export.vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import { TRANSLATION_QUERY_KEYS } from '@/queries/translation'

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const authStore = useAuthStore()
const queryCache = useQueryCache()

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

// Permissions
const { canDeleteCompany } = useCompanyPermissions()

// Translation languages
const { data: translationLanguages } = useQuery(translationLanguagesQuery, () => ({}))

// Optimistic UI state for in-progress translations
const optimisticTranslations = ref<Map<string, { job: TranslationJob | null }>>(new Map())

// Polling interval reference for cleanup
let pollingInterval: ReturnType<typeof setInterval> | null = null

// Translation status for company with refetchInterval for active jobs
const { data: translationStatus, refetch: refetchStatus } = useQuery(
  companyTranslationStatusQuery,
  () => ({ companyId: companyId.value }),
  {
    enabled: () =>
      !!companyId.value && companyId.value !== 'null' && companyId.value !== 'undefined',
  },
)

/**
 * Check if there are any active translation jobs (pending or running).
 */
const hasActiveJobs = computed(() => {
  if (!translationStatus.value?.translations) return false
  return Object.values(translationStatus.value.translations).some(
    (langStatus) =>
      langStatus.active_job &&
      (langStatus.active_job.status === 'pending' || langStatus.active_job.status === 'running'),
  )
})

/**
 * Start or stop polling based on active jobs.
 */
watch(
  hasActiveJobs,
  (hasJobs) => {
    if (hasJobs && !pollingInterval) {
      // Start polling every 3 seconds when there are active jobs
      pollingInterval = setInterval(() => {
        refetchStatus()
      }, 3000)
    } else if (!hasJobs && pollingInterval) {
      // Stop polling when no active jobs
      clearInterval(pollingInterval)
      pollingInterval = null
    }
  },
  { immediate: true },
)

// Cleanup polling on unmount
onUnmounted(() => {
  if (pollingInterval) {
    clearInterval(pollingInterval)
  }
})

/**
 * Get translation status for a specific language.
 * Returns 'none', 'partial', or 'complete'.
 */
const getLanguageStatus = (languageCode: string): 'none' | 'partial' | 'complete' => {
  if (!translationStatus.value?.translations) return 'none'
  return translationStatus.value.translations[languageCode]?.status ?? 'none'
}

/**
 * Check if a language is currently being translated.
 */
const isLanguageTranslating = (languageCode: string): boolean => {
  // Check optimistic state first
  if (optimisticTranslations.value.has(languageCode)) {
    return true
  }
  // Check backend status
  const langStatus = translationStatus.value?.translations?.[languageCode]
  if (!langStatus?.active_job) return false
  return langStatus.active_job.status === 'pending' || langStatus.active_job.status === 'running'
}

/**
 * Get progress percentage for a language translation.
 */
const getProgressPercent = (languageCode: string): number => {
  const langStatus = translationStatus.value?.translations?.[languageCode]
  if (!langStatus?.active_job) return 0
  return Math.round(langStatus.active_job.progress_percentage)
}

/**
 * Get progress title for tooltip.
 */
const getProgressTitle = (languageCode: string): string => {
  const langStatus = translationStatus.value?.translations?.[languageCode]
  if (!langStatus?.active_job) {
    return t('company.translation.inProgress', 'Translation in progress...')
  }
  const job = langStatus.active_job
  return t('company.translation.progressDetail', {
    translated: job.translated_fields,
    total: job.total_fields,
    percent: Math.round(job.progress_percentage),
  })
}

/**
 * Reset to viewing the original (English) content.
 */
const resetToOriginal = () => {
  if (selectedLanguage.value) {
    selectedLanguage.value = undefined
    toast.info(t('company.translation.viewingDefault', 'Viewing in original language'))
  }
}

/**
 * Handle translation action for a language.
 * If translation is complete, switch to viewing in that language.
 * If not complete, request translation.
 */
const handleTranslate = async (languageCode: string) => {
  if (!companyId.value) return

  const langStatus = getLanguageStatus(languageCode)

  // If translation is complete, switch to viewing in that language
  if (langStatus === 'complete') {
    // Toggle: if already viewing this language, switch back to default
    if (selectedLanguage.value === languageCode) {
      selectedLanguage.value = undefined
      toast.info(t('company.translation.viewingDefault', 'Viewing in original language'))
    } else {
      selectedLanguage.value = languageCode
      toast.success(
        t('company.translation.viewingIn', {
          language: getLanguageName(languageCode),
        }),
      )
    }
    return
  }

  // Prevent duplicate requests
  if (isLanguageTranslating(languageCode)) {
    toast.info(t('company.translation.alreadyInProgress', 'Translation already in progress'))
    return
  }

  // Optimistic update: immediately show as translating
  optimisticTranslations.value.set(languageCode, { job: null })

  try {
    const response = await requestTranslation(companyId.value, languageCode)

    // Update optimistic state with actual job
    if (response.job) {
      optimisticTranslations.value.set(languageCode, { job: response.job })
    }

    // Show success toast
    if (response.fields_queued > 0) {
      toast.success(
        t('company.translation.started', {
          count: response.fields_queued,
          language: getLanguageName(languageCode),
        }),
      )
    } else {
      toast.info(response.message)
      // Remove from optimistic state if nothing to translate
      optimisticTranslations.value.delete(languageCode)
    }

    // Refresh status to get real backend state
    await refetchStatus()
    // Clear optimistic state once backend state is refreshed
    optimisticTranslations.value.delete(languageCode)
  } catch (error) {
    // Rollback: remove optimistic state on failure
    optimisticTranslations.value.delete(languageCode)

    // Show error toast
    toast.error(t('company.translation.failed', 'Translation request failed. Please try again.'))
    console.error('Translation request failed:', error)
  }
}

/**
 * Get translated language name from code.
 */
const getLanguageName = (languageCode: string): string => {
  // Use i18n translation for language names
  const translationKey = `company.translation.languages.${languageCode}`
  const translated = t(translationKey)
  // If translation key returns the key itself, fall back to API response
  if (translated === translationKey) {
    const lang = translationLanguages.value?.find((l) => l.code === languageCode)
    return lang?.name ?? languageCode
  }
  return translated
}

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

// Helper function to format date
const formatDate = (dateString: string): string => {
  if (!dateString) return t('common.na')
  const localeCode = locale.value === 'fr-FR' ? 'fr-FR' : 'en-US'
  return new Date(dateString).toLocaleDateString(localeCode)
}
</script>
