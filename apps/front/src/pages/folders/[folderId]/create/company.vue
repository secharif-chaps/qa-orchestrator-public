<template>
  <div class="mx-auto flex max-w-4xl flex-col gap-6" data-cy="company-search-page">
    <!-- Page Header -->
    <div class="flex flex-col gap-2">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-semibold">{{ $t('screen.search.title') }}</h1>
          <p class="text-secondary">{{ $t('screen.search.companyIdentity') }}</p>
        </div>

        <!-- Token Counter -->
        <div v-if="currentOrganization" class="flex items-center gap-4">
          <div class="text-right">
            <TokenCounter
              :token-count="tokenBalance"
              :label="$t('settings.tokens.balance')"
              :is-loading="tokenDataLoading || !currentOrganization?.id"
              :is-refreshing="isRefreshingTokens"
              show-label
              show-company-equivalence
              @refresh="refreshTokenData"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Token Alerts -->
    <InsufficientTokensAlert
      v-if="showInsufficientTokenAlert"
      module="screen"
      :current-tokens="tokenBalance"
      :required-tokens="35"
      @contact-admin="contactAdmin"
      @refresh="refreshTokenData"
      @dismiss="dismissTokenAlert"
    />

    <!-- Search Form Card -->
    <div
      class="bg-base-100 border-primary-stroke rounded-lg border p-6"
      :title="$t('screen.search.companyIdentity')"
    >
      <form @submit.prevent="submit" class="flex flex-col gap-6">
        <!-- Form Fields -->
        <div class="flex flex-col gap-4">
          <Input
            id="company"
            v-model="company"
            :placeholder="$t('screen.search.fields.companyName.placeholder')"
            :error="companyError"
            data-cy="company-name-input"
            required
            :label="$t('screen.search.fields.companyName.label')"
            icon="fa-building"
          />

          <Input
            id="website"
            v-model="website"
            :placeholder="$t('screen.search.fields.website.placeholder')"
            :error="websiteError"
            data-cy="website-input"
            required
            :label="$t('screen.search.fields.website.label')"
            icon="fa-globe"
          />
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-between pt-4">
          <Button
            variant="tertiary"
            icon="fa fa-upload"
            :label="$t('screen.csv.upload.button')"
            @click="goToCSVUpload"
          />

          <Button
            variant="primary"
            icon="fa fa-search"
            :label="$t('screen.search.actions.launchSearch')"
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
import { Button, Input } from '@owlint/feathers-vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { useCreateCompany } from '@/mutations/companies'
import { useAddItemToFolder } from '@/mutations/folders'
import { currentOrganizationQuery } from '@/queries/organization'
import { organizationBalanceQuery, organizationModulesQuery } from '@/queries/tokens'
import { InsufficientTokensError } from '@/api/client'
import TokenCounter from '@/components/tokens/TokenCounter.vue'
import InsufficientTokensAlert from '@/components/tokens/InsufficientTokensAlert.vue'

// Token cost for company creation
const TOKENS_PER_COMPANY = 35

const { t } = useI18n()
const router = useRouter()
const route = useRoute('/folders/[folderId]/create/company')

const company = ref('')
const website = ref('')
const companyError = ref('')
const websiteError = ref('')

const {
  isLoading: mutationLoading,
  mutateAsync,
  organizationId: mutationOrgId,
} = useCreateCompany()
const { mutateAsync: addToFolder } = useAddItemToFolder()

// Fetch current organization
const { data: currentOrganization } = useQuery(() => currentOrganizationQuery())

// Global token balance query
const {
  data: balanceData,
  isLoading: tokenDataLoading,
  refetch: refetchBalance,
} = useQuery({
  ...organizationBalanceQuery({
    organizationId: currentOrganization.value?.id ?? '',
  }),
  enabled: computed(() => !!currentOrganization.value?.id),
})

// Module enablement query (to check if screen module is enabled)
const { data: modulesData } = useQuery({
  ...organizationModulesQuery({
    organizationId: currentOrganization.value?.id ?? '',
  }),
  enabled: computed(() => !!currentOrganization.value?.id),
})

// Set organization ID on mutation for optimistic cache update
watch(
  () => currentOrganization.value?.id,
  (orgId) => {
    if (orgId) {
      mutationOrgId.value = orgId
    }
  },
  { immediate: true },
)

// Computed properties based on global token balance
const tokenBalance = computed(() => balanceData.value?.balance ?? 0)
const screenModuleEnabled = computed(() => {
  if (!modulesData.value?.modules) return false
  const screenModule = modulesData.value.modules.find((m) => m.name === 'screen')
  return screenModule?.enabled ?? false
})

const canPerformSearch = computed(() => {
  // Don't allow search if organization or token data is not loaded yet
  if (!currentOrganization.value?.id || tokenDataLoading.value) {
    return false
  }
  // Require at least 35 tokens (cost of 1 company creation)
  return screenModuleEnabled.value && tokenBalance.value >= TOKENS_PER_COMPANY
})

const showInsufficientTokenAlert = computed(() => {
  // Don't show alert if data is still loading
  if (!currentOrganization.value?.id || tokenDataLoading.value) {
    return false
  }
  // Show alert if tokens are below 35 (cost of 1 company creation)
  return (
    screenModuleEnabled.value && tokenBalance.value < TOKENS_PER_COMPANY && !showTokenAlert.value
  )
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
    companyError.value = t('screen.search.fields.companyName.error')
  }

  // Validate website URL
  if (newWebsite && !validateWebsite(newWebsite)) {
    websiteError.value = t('screen.search.fields.website.error')
  }
})

// Token methods
const refreshTokenData = async () => {
  isRefreshingTokens.value = true
  try {
    await refetchBalance()
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
    companyError.value = t(
      'screen.company.validation.loadingorganization',
      'Loading organization...',
    )
    return
  }

  // Check if token data is still loading
  if (tokenDataLoading.value) {
    companyError.value = t('screen.company.validation.loadingTokens')
    return
  }

  // Check token availability
  if (!canPerformSearch.value) {
    if (!screenModuleEnabled.value) {
      companyError.value = t(
        'screen.company.validation.moduleDisabled',
        'The Screen module is disabled',
      )
    } else {
      companyError.value = t(
        'screen.company.validation.insufficientTokens',
        'Insufficient tokens. You need at least 35 tokens to create a company.',
      )
    }
    return
  }

  if (!validateCompany(company.value)) {
    companyError.value = t('screen.search.fields.companyName.error')
    return
  }

  if (!validateWebsite(website.value)) {
    websiteError.value = t('screen.search.fields.website.error')
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
    if (newCompany.id === undefined) {
      throw new Error('Company ID is undefined')
    }
    await addToFolder({
      folderId,
      item: {
        item_id: newCompany.id.toString(),
        item_type: 'company',
      },
    })
    // Redirect to the newly created company page
    router.push(`/folders/${folderId}/companies/${newCompany.id}`)
  } catch (error: unknown) {
    // Handle any unexpected errors during the search process
    console.error('Error during search:', error)

    // Handle insufficient tokens error
    if (error instanceof InsufficientTokensError) {
      companyError.value = t(
        'screen.company.validation.insufficientTokens',
        'Insufficient tokens. You need at least 35 tokens to create a company.',
      )
      // Refresh token data to get current counts
      await refreshTokenData()
      return
    }

    // Display user-friendly error message
    const message = error instanceof Error ? error.message : undefined
    if (message) {
      // Extract meaningful error message
      if (message.includes('Validation error')) {
        companyError.value = t(
          'screen.company.validation.invalidNameFormat',
          'Company name must contain at least 2 alphabetic characters',
        )
        websiteError.value = t(
          'screen.company.validation.invalidWebsiteFormat',
          'Please enter a valid website URL',
        )
      } else if (message.includes('Invalid input')) {
        companyError.value = t('screen.company.validation.nameRequired')
        websiteError.value = t(
          'screen.company.validation.websiteRequired',
          'Website URL is required',
        )
      } else if (message.includes('unauthorized') || message.includes('401')) {
        // Authentication error - will be handled by navigateTo('/login') in API service
      } else {
        // Generic error
        companyError.value = t(
          'screen.company.validation.createError',
          'An error occurred while creating the company',
        )
      }
    } else {
      companyError.value = t(
        'screen.company.validation.networkError',
        'Network error - please try again',
      )
    }
  }
}
</script>
