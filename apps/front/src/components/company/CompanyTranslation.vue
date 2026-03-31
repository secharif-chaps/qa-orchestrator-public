<template>
  <Dropdown v-if="isTranslationEnabled && translationLanguages?.length" align="left" width="sm">
    <template #trigger>
      <Button
        variant="tertiary"
        icon="fa fa-language"
        :label="t('screen.company.translation.button')"
      />
    </template>
    <template #content>
      <!-- Original language option -->
      <DropdownItem :class="{ 'bg-primary-light': !modelValue }" @click="resetToOriginal()">
        <div class="flex w-full items-center justify-between gap-2">
          <span>{{ t('screen.company.translation.original') }}</span>
          <i
            v-if="!modelValue"
            class="fa fa-eye text-primary"
            :title="t('screen.company.translation.currentlyViewing')"
          />
        </div>
      </DropdownItem>
      <!-- Separator -->
      <div class="border-base-300 my-1 border-t" />
      <!-- Language options -->
      <DropdownItem
        v-for="lang in translationLanguages"
        :key="lang.code"
        :disabled="isLanguageTranslating(lang.code)"
        :class="{ 'bg-primary-light': modelValue === lang.code }"
        @click="handleTranslate(lang.code)"
      >
        <div class="flex w-full items-center justify-between gap-2">
          <span>{{ getLanguageName(lang.code) }}</span>
          <!-- Loading spinner for in-progress translations -->
          <span
            v-if="isLanguageTranslating(lang.code)"
            class="text-info flex items-center gap-1"
            :title="getProgressTitle(lang.code)"
          >
            <i class="fa fa-spinner fa-spin" />
            <span class="text-xs">{{ getProgressPercent(lang.code) }}%</span>
          </span>
          <!-- Download icon for untranslated -->
          <i
            v-else-if="getLanguageStatus(lang.code) === 'none'"
            class="fa fa-download text-secondary"
            :title="t('screen.company.translation.clickToTranslate')"
          />
          <!-- Eye icon for currently viewing -->
          <i
            v-else-if="modelValue === lang.code"
            class="fa fa-eye text-primary"
            :title="t('screen.company.translation.currentlyViewing')"
          />
          <!-- Check icon for translated (click to view) -->
          <i
            v-else
            class="fa fa-check text-success"
            :title="t('screen.company.translation.clickToView')"
          />
        </div>
      </DropdownItem>
    </template>
  </Dropdown>
</template>

<script lang="ts" setup>
import { Button } from '@owlint/feathers-vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import { translationLanguagesQuery, companyTranslationStatusQuery } from '@/queries/translation'
import { organizationFeatureFlagsQuery } from '@/queries/feature-flags'
import { currentOrganizationQuery } from '@/queries/organization'
import { requestTranslation } from '@/api/translation'
import { useQuery } from '@pinia/colada'
import { computed, ref, watch, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { toast } from '@/utils/toast'
import type { TranslationJob } from '@/api/translation'

const props = defineProps<{
  companyId: string
}>()

// Two-way binding for selected language
const modelValue = defineModel<string | undefined>()

const { t } = useI18n()

// Fetch current organization
const { data: currentOrganization } = useQuery(() => currentOrganizationQuery())

// Organization feature flags - check if translation is enabled
const { data: featureFlagsData } = useQuery({
  ...organizationFeatureFlagsQuery({ organizationId: currentOrganization.value?.id || '' }),
  enabled: () => !!currentOrganization.value?.id,
})

// Check if translation feature flag is enabled for the organization
const isTranslationEnabled = computed(() => {
  if (!featureFlagsData.value?.feature_flags) return false
  const translationFlag = featureFlagsData.value.feature_flags.find((f) => f.flag === 'translation')
  return translationFlag?.enabled ?? false
})

// Translation languages
const { data: translationLanguages } = useQuery(() => translationLanguagesQuery())

// Optimistic UI state for in-progress translations
const optimisticTranslations = ref<Map<string, { job: TranslationJob | null }>>(new Map())

// Polling interval reference for cleanup
let pollingInterval: ReturnType<typeof setInterval> | null = null

// Translation status for company with refetchInterval for active jobs
const { data: translationStatus, refetch: refetchStatus } = useQuery({
  ...companyTranslationStatusQuery({ companyId: props.companyId }),
  enabled: () => !!props.companyId && props.companyId !== 'null' && props.companyId !== 'undefined',
})

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
    return t('screen.company.translation.inProgress')
  }
  const job = langStatus.active_job
  return t('screen.company.translation.progressDetail', {
    translated: job.translated_fields,
    total: job.total_fields,
    percent: Math.round(job.progress_percentage),
  })
}

/**
 * Reset to viewing the original (English) content.
 */
const resetToOriginal = () => {
  if (modelValue.value) {
    modelValue.value = undefined
    toast.info(t('screen.company.translation.viewingDefault'))
  }
}

/**
 * Handle translation action for a language.
 * If translation is complete, switch to viewing in that language.
 * If not complete, request translation.
 */
const handleTranslate = async (languageCode: string) => {
  if (!props.companyId) return

  const langStatus = getLanguageStatus(languageCode)

  // If translation is complete, switch to viewing in that language
  if (langStatus === 'complete') {
    // Toggle: if already viewing this language, switch back to default
    if (modelValue.value === languageCode) {
      modelValue.value = undefined
      toast.info(t('screen.company.translation.viewingDefault'))
    } else {
      modelValue.value = languageCode
      toast.success(
        t('screen.company.translation.viewingIn', {
          language: getLanguageName(languageCode),
        }),
      )
    }
    return
  }

  // Prevent duplicate requests
  if (isLanguageTranslating(languageCode)) {
    toast.info(t('screen.company.translation.alreadyInProgress'))
    return
  }

  // Optimistic update: immediately show as translating
  optimisticTranslations.value.set(languageCode, { job: null })

  try {
    const response = await requestTranslation(props.companyId, languageCode)

    // Update optimistic state with actual job
    if (response.job) {
      optimisticTranslations.value.set(languageCode, { job: response.job })
    }

    // Show success toast
    if (response.fields_queued > 0) {
      toast.success(
        t('screen.company.translation.started', {
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
    toast.error(t('screen.company.translation.failed'))
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
</script>
