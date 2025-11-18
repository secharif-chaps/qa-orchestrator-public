<template>
  <div class="max-w-4xl mx-auto space-y-6" data-cy="company-search-page">
    <!-- Page Header -->
    <div class="space-y-2">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-semibold">{{ $t('search.title') }}</h1>
          <p class="text-secondary">{{ $t('search.companyIdentity') }}</p>
        </div>

        <!-- Token Counter -->
        <div v-if="currentOrganization" class="flex items-center gap-4">
          <div class="text-right">
            <TokenCounter
              module="screen"
              :token-count="screenTokenCount"
              :is-enabled="screenModuleEnabled"
              :is-loading="tokenDataLoading || !currentOrganization?.id"
              :is-refreshing="isRefreshingTokens"
              show-label
              show-status
              @refresh="refreshScreenTokens"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Token Alerts -->
    <InsufficientTokensAlert
      v-if="showInsufficientTokenAlert"
      module="screen"
      :current-tokens="screenTokenCount"
      :required-tokens="35"
      @contact-admin="contactAdmin"
      @refresh="refreshScreenTokens"
      @dismiss="dismissTokenAlert"
    />

    <!-- Search Form Card -->
    <div
      class="bg-base-100 border border-primary-stroke rounded-lg p-6"
      :title="$t('search.companyIdentity')"
    >
      <form @submit.prevent="startSearch" class="space-y-6">
        <!-- Form Fields -->
        <div class="space-y-4">
          <Input
            id="company"
            v-model="company"
            :placeholder="$t('search.fields.companyName.placeholder')"
            :error="companyError"
            data-cy="company-name-input"
            required
            :label="$t('search.fields.companyName.label')"
            icon="fas fa-building"
            clearable
          />

          <Input
            id="website"
            v-model="website"
            :placeholder="$t('search.fields.website.placeholder')"
            :error="websiteError"
            data-cy="website-input"
            required
            :label="$t('search.fields.website.label')"
            icon="fas fa-globe"
            clearable
          />
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-between pt-4">
          <Button
            variant="tertiary"
            icon="fa fa-upload"
            :label="$t('csv.upload.button', 'Upload CSV')"
            @click="goToCSVUpload"
          />

          <Button
            variant="primary"
            icon="fa fa-search"
            :label="$t('search.actions.launchSearch')"
            :loading="mutationLoading"
            :disabled="mutationLoading || !isFormValid || !canPerformSearch"
            @click="submit"
          />
        </div>
      </form>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - company.create
</route>

<script lang="ts" setup>
import { OIcon } from '@owlint/feathers-vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { useCreateCompany } from '@/mutations/companies'
import { useAddItemToFolder } from '@/mutations/folders'
import { currentOrganizationQuery } from '@/queries/organization'
import { moduleTokensQuery } from '@/queries/tokens'
import { InsufficientTokensError } from '@/api/client'
import type { ModuleName } from '@/types/tokens'
import TokenCounter from '@/components/tokens/TokenCounter.vue'
import InsufficientTokensAlert from '@/components/tokens/InsufficientTokensAlert.vue'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

const company = ref('')
const website = ref('')
const companyError = ref('')
const websiteError = ref('')

const { isLoading: mutationLoading, mutateAsync } = useCreateCompany()
const { mutateAsync: addToFolder } = useAddItemToFolder()

// Token validation with real backend integration
const { data: currentOrganization } = useQuery(currentOrganizationQuery, () => ({}))

// Query for screen module tokens - only run when organization ID is available
const {
  data: screenTokenData,
  isLoading: tokenDataLoading,
  refetch: refetchTokens,
} = useQuery(
  moduleTokensQuery,
  () => ({
    organizationId: currentOrganization.value!.id, // Non-null assertion since enabled check ensures it exists
    module: 'screen' as ModuleName,
  }),
  {
    enabled: computed(() => !!currentOrganization.value?.id),
  },
)

// Computed properties based on real token data
const screenTokenCount = computed(() => screenTokenData.value?.token_count ?? 0)
const screenModuleEnabled = computed(() => screenTokenData.value?.enabled ?? false)

const canPerformSearch = computed(() => {
  // Don't allow search if organization or token data is not loaded yet
  if (!currentOrganization.value?.id || tokenDataLoading.value) {
    return false
  }
  // Require at least 35 tokens (cost of 1 company creation)
  return screenModuleEnabled.value && screenTokenCount.value >= 35
})

const showInsufficientTokenAlert = computed(() => {
  // Don't show alert if data is still loading
  if (!currentOrganization.value?.id || tokenDataLoading.value) {
    return false
  }
  // Show alert if tokens are below 35 (cost of 1 company creation)
  return screenModuleEnabled.value && screenTokenCount.value < 35 && !showTokenAlert.value
})

// Token alert state
const showTokenAlert = ref(false)
const isRefreshingTokens = ref(false)

// Computed property to check if there are any validation errors or missing required fields
const hasErrors = computed(() => {
  return !!companyError.value || !!websiteError.value
})

// Computed property to check if both required fields are filled
const isFormValid = computed(() => {
  return company.value.trim().length > 0 && website.value.trim().length > 0 && !hasErrors.value
})

// Validate website URL format
const validateWebsite = (url: string) => {
  if (!url.trim()) return false

  try {
    // Add https:// if no protocol is provided
    const urlToTest = url.includes('://') ? url : `https://${url}`
    const parsedUrl = new URL(urlToTest)

    // Check if it's a valid HTTP/HTTPS URL
    return parsedUrl.protocol === 'http:' || parsedUrl.protocol === 'https:'
  } catch {
    return false
  }
}

// Validate company name
const validateCompany = (name: string) => {
  return name.trim().length >= 2
}

// Watch for changes to validate inputs
watch([company, website], ([newCompany, newWebsite]) => {
  // Reset errors when inputs change
  companyError.value = ''
  websiteError.value = ''

  // Validate company name
  if (newCompany && !validateCompany(newCompany)) {
    companyError.value = t('search.fields.companyName.error')
  }

  // Validate website URL
  if (newWebsite && !validateWebsite(newWebsite)) {
    websiteError.value = t('search.fields.website.error')
  }
})

// Reset the form data
const resetData = () => {
  company.value = ''
  website.value = ''
  companyError.value = ''
  websiteError.value = ''
}

// Token methods
const refreshScreenTokens = async () => {
  isRefreshingTokens.value = true
  try {
    await refetchTokens()
  } finally {
    isRefreshingTokens.value = false
  }
}

const contactAdmin = () => {
  // This would typically open a modal or redirect to admin contact
  console.log('Contact admin for token refill')
}

const dismissTokenAlert = () => {
  showTokenAlert.value = true
}

// Navigation to CSV upload
const goToCSVUpload = () => {
  const folderId = route.params.folderId
  router.push(`/folders/${folderId}/create/company-csv`)
}

// Handle the search and redirection as soon as we get the company name
const submit = async () => {
  // Check if organization data is loaded
  if (!currentOrganization.value?.id) {
    companyError.value = t('company.validation.loadingorganization', 'Loading organization...')
    return
  }

  // Check if token data is still loading
  if (tokenDataLoading.value) {
    companyError.value = t('company.validation.loadingTokens', 'Loading tokens...')
    return
  }

  // Check token availability
  if (!canPerformSearch.value) {
    if (!screenModuleEnabled.value) {
      companyError.value = t('company.validation.moduleDisabled', 'The Stream module is disabled')
    } else {
      companyError.value = t(
        'company.validation.insufficientTokens',
        'Insufficient tokens. You need at least 35 tokens to create a company.',
      )
    }
    return
  }

  if (!validateCompany(company.value)) {
    companyError.value = t('search.fields.companyName.error')
    return
  }

  if (!validateWebsite(website.value)) {
    websiteError.value = t('search.fields.website.error')
    return
  }

  try {
    const trimmedCompany = company.value.trim()
    // Ensure website has protocol
    const websiteUrl = website.value.includes('://') ? website.value : `https://${website.value}`

    const newCompany = await mutateAsync({
      name: trimmedCompany,
      website: websiteUrl,
    })

    // Add the company to the folder
    const folderId = route.params.folderId
    await addToFolder({
      folderId,
      item: {
        item_id: newCompany.id.toString(),
        item_type: 'company',
      },
    })
    // Redirect to the newly created company page
    router.push(`/folders/${folderId}/companies/${newCompany.id}`)
  } catch (error: any) {
    // Handle any unexpected errors during the search process
    console.error('Error during search:', error)

    // Handle insufficient tokens error
    if (error instanceof InsufficientTokensError) {
      companyError.value = t(
        'company.validation.insufficientTokens',
        'Insufficient tokens. You need at least 35 tokens to create a company.',
      )
      // Refresh token data to get current counts
      await refreshScreenTokens()
      return
    }

    // Display user-friendly error message
    if (error?.message) {
      // Extract meaningful error message
      if (error.message.includes('Validation error')) {
        companyError.value = t(
          'company.validation.invalidNameFormat',
          'Company name must contain at least 2 alphabetic characters',
        )
        websiteError.value = t(
          'company.validation.invalidWebsiteFormat',
          'Please enter a valid website URL',
        )
      } else if (error.message.includes('Invalid input')) {
        companyError.value = t('company.validation.nameRequired', 'Company name is required')
        websiteError.value = t('company.validation.websiteRequired', 'Website URL is required')
      } else if (error.message.includes('unauthorized') || error.message.includes('401')) {
        // Authentication error - will be handled by navigateTo('/login') in API service
      } else {
        // Generic error
        companyError.value = t(
          'company.validation.createError',
          'An error occurred while creating the company',
        )
      }
    } else {
      companyError.value = t('company.validation.networkError', 'Network error - please try again')
    }
  }
}
</script>
