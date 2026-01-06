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
            <DropdownItem
              v-for="lang in translationLanguages"
              :key="lang.code"
              @click="handleTranslate(lang.code); close()"
            >
              <div class="flex items-center justify-between w-full gap-2">
                <span>{{ lang.name }}</span>
                <i
                  v-if="getLanguageStatus(lang.code) === 'none'"
                  class="fa fa-download text-secondary"
                  :title="t('company.translation.notTranslated', 'Not translated')"
                />
                <i
                  v-else
                  class="fa fa-check text-success"
                  :title="t('company.translation.translated', 'Translated')"
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
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
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

const isDebugUser = computed(() => {
  // Always show debug button in dev mode
  if (import.meta.env.DEV) return true
  const username = authStore.user?.profile?.preferred_username?.toLowerCase()
  return username === 'nmr' || username === 'suh' || username === 'nmr-cv'
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

// Translation languages
const { data: translationLanguages } = useQuery(translationLanguagesQuery, () => ({}))

// Translation status for company
const { data: translationStatus } = useQuery(
  companyTranslationStatusQuery,
  () => ({ companyId: companyId.value }),
  {
    enabled: () =>
      !!companyId.value && companyId.value !== 'null' && companyId.value !== 'undefined',
  },
)

/**
 * Get translation status for a specific language.
 * Returns 'none', 'partial', or 'complete'.
 */
function getLanguageStatus(languageCode: string): 'none' | 'partial' | 'complete' {
  if (!translationStatus.value?.translations) return 'none'
  return translationStatus.value.translations[languageCode]?.status ?? 'none'
}

/**
 * Handle translation request for a language.
 */
async function handleTranslate(languageCode: string) {
  if (!companyId.value) return

  try {
    const response = await requestTranslation(companyId.value, languageCode)
    console.log('Translation queued:', response)

    // Invalidate status query to refresh after translation starts
    queryCache.invalidateQueries({
      key: TRANSLATION_QUERY_KEYS.status(companyId.value),
    })
  } catch (error) {
    console.error('Translation request failed:', error)
  }
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
function formatDate(dateString: string): string {
  if (!dateString) return t('common.na')
  const localeCode = locale.value === 'fr-FR' ? 'fr-FR' : 'en-US'
  return new Date(dateString).toLocaleDateString(localeCode)
}
</script>
