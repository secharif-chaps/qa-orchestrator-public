<template>
  <div class="max-w-4xl mx-auto flex flex-col gap-6" data-cy="company-search-page">
    <!-- Page Header -->
    <div class="flex flex-col gap-2">
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
              show-company-equivalence
              @refresh="refreshScreenTokens"
            />
          </div>
        </div>
      </div>

      <!-- Folder context description (when folder ID in route) -->
      <p v-if="routeFolderId && folderData" class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('company.create.inFolder', 'Create a new company screen in folder') }}
        <strong>{{ folderData.name }}</strong>
      </p>
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

    <!-- No Folders Alert -->
    <Alert
      v-if="needsFolderSelection && !foldersLoading && (!foldersData || foldersData.length === 0)"
      variant="info"
      :title="$t('company.create.noFolders.title', 'No folders available')"
      :message="
        $t(
          'company.create.noFolders.message',
          'You need to create a folder before creating a company screen',
        )
      "
      icon="fa fa-folder-plus"
    >
      <template #actions>
        <Button
          variant="primary"
          :label="$t('company.create.noFolders.action', 'Create Folder')"
          icon="fa fa-plus"
          @click="navigateToFolderCreate"
        />
      </template>
    </Alert>

    <!-- Search Form Card -->
    <div
      v-if="!needsFolderSelection || (foldersData && foldersData.length > 0)"
      class="bg-base-100 border border-primary-stroke rounded-lg p-6"
      :title="$t('search.companyIdentity')"
    >
      <form @submit.prevent="submit" class="flex flex-col gap-6">
        <!-- Folder Selection (when no folder ID in route) -->
        <div v-if="needsFolderSelection" class="flex flex-col gap-2">
          <label for="folder-select" class="text-sm font-medium">
            {{ $t('company.create.selectFolder', 'Select Folder') }}
            <span class="text-error">*</span>
          </label>
          <Dropdown align="left" width="full" :close-on-select="true">
            <template #trigger="{ isOpen }">
              <button
                type="button"
                class="flex items-center justify-between w-full px-4 py-2 border border-primary-stroke rounded-lg bg-base-100 hover:bg-base-200 transition-colors"
                :class="{ 'ring-2 ring-primary': isOpen }"
              >
                <span v-if="selectedFolderId" class="flex items-center gap-2">
                  <i class="fa fa-folder text-sage-600 dark:text-sage-400"></i>
                  {{ selectedFolder?.name }}
                </span>
                <span v-else class="text-gray-500">
                  {{ $t('company.create.chooseFolderPlaceholder', 'Choose a folder...') }}
                </span>
                <i class="fa fa-chevron-down text-sm" :class="{ 'rotate-180': isOpen }"></i>
              </button>
            </template>

            <template #content="{ close }">
              <div class="max-h-60 overflow-y-auto">
                <button
                  v-for="folder in foldersData"
                  :key="folder.id"
                  type="button"
                  class="w-full flex items-center gap-3 px-4 py-2 text-left hover:bg-base-200 transition-colors"
                  @click="(selectFolder(folder.id), close())"
                >
                  <i class="fa fa-folder text-sage-600 dark:text-sage-400"></i>
                  <span class="flex-1">{{ folder.name }}</span>
                  <i v-if="selectedFolderId === folder.id" class="fa fa-check text-success"></i>
                </button>
              </div>
            </template>
          </Dropdown>
        </div>

        <!-- Form Fields -->
        <div class="flex flex-col gap-4">
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
            :disabled="!targetFolderId"
          />

          <Button
            variant="primary"
            icon="fa fa-search"
            :label="$t('search.actions.launchSearch')"
            :loading="mutationLoading"
            :disabled="!canSubmit"
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
import { Button } from '@owlint/feathers-vue'
import Input from '@/components/ui/Input.vue'
import Alert from '@/components/ui/Alert.vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { useCreateCompany } from '@/mutations/companies'
import { useAddItemToFolder } from '@/mutations/folders'
import { currentOrganizationQuery } from '@/queries/organization'
import { moduleTokensQuery } from '@/queries/tokens'
import { foldersQuery, folderByIdQuery } from '@/queries/folders'
import { InsufficientTokensError } from '@/api/client'
import type { ModuleName } from '@/types/tokens'
import TokenCounter from '@/components/tokens/TokenCounter.vue'
import InsufficientTokensAlert from '@/components/tokens/InsufficientTokensAlert.vue'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

// Form state
const company = ref('')
const website = ref('')
const companyError = ref('')
const websiteError = ref('')
const selectedFolderId = ref<string | null>(null)

// Mutations
const { isLoading: mutationLoading, mutateAsync } = useCreateCompany()
const { mutateAsync: addToFolder } = useAddItemToFolder()

// Get folder ID from route query parameter
const routeFolderId = computed(() => route.query.folderId as string | undefined)

// Determine if folder selection is needed
const needsFolderSelection = computed(() => !routeFolderId.value)

// Target folder ID (from route or selected)
const targetFolderId = computed(() => routeFolderId.value || selectedFolderId.value)

// Fetch current organization
const { data: currentOrganization } = useQuery(currentOrganizationQuery, () => ({}))

// Fetch folders (only when folder selection is needed)
const { data: foldersData, isLoading: foldersLoading } = useQuery(
  foldersQuery,
  () => ({
    filters: {
      page: 1,
      size: 100, // Get all folders
      name: '',
      archived: false,
    },
  }),
  {
    enabled: computed(() => needsFolderSelection.value),
  },
)

// Fetch folder details (when folder ID is in route)
const { data: folderData } = useQuery(folderByIdQuery, () => ({ id: routeFolderId.value || '' }), {
  enabled: computed(() => !!routeFolderId.value),
})

// Selected folder object
const selectedFolder = computed(() => {
  if (!selectedFolderId.value || !foldersData.value) return null
  return foldersData.value.find((f) => f.id === selectedFolderId.value)
})

// Token management
const {
  data: screenTokenData,
  isLoading: tokenDataLoading,
  refetch: refetchTokens,
} = useQuery(
  moduleTokensQuery,
  () => ({
    // Provide safe default when organization not yet loaded
    organizationId: currentOrganization.value?.id ?? '',
    module: 'screen' as ModuleName,
  }),
  {
    enabled: computed(() => !!currentOrganization.value?.id),
  },
)

const screenTokenCount = computed(() => screenTokenData.value?.token_count ?? 0)
const screenModuleEnabled = computed(() => screenTokenData.value?.enabled ?? false)

const canPerformSearch = computed(() => {
  if (!currentOrganization.value?.id || tokenDataLoading.value) {
    return false
  }
  return screenModuleEnabled.value && screenTokenCount.value >= 35
})

const showInsufficientTokenAlert = computed(() => {
  if (!currentOrganization.value?.id || tokenDataLoading.value) {
    return false
  }
  return screenModuleEnabled.value && screenTokenCount.value < 35 && !showTokenAlert.value
})

const showTokenAlert = ref(false)
const isRefreshingTokens = ref(false)

// Form validation
const hasErrors = computed(() => {
  return !!companyError.value || !!websiteError.value
})

const isFormValid = computed(() => {
  return company.value.trim().length > 0 && website.value.trim().length > 0 && !hasErrors.value
})

// Can submit form
const canSubmit = computed(() => {
  // Must have valid form
  if (!isFormValid.value || mutationLoading.value || !canPerformSearch.value) {
    return false
  }

  // Must have target folder (from route or selected)
  if (!targetFolderId.value) {
    return false
  }

  // If folder selection is needed, must have selected a folder
  if (needsFolderSelection.value && !selectedFolderId.value) {
    return false
  }

  return true
})

// Folder selection
function selectFolder(folderId: string) {
  selectedFolderId.value = folderId
}

// Navigation
function navigateToFolderCreate() {
  // Navigate to folder creation page (TODO: update when route is created)
  router.push('/folders/create')
}

function goToCSVUpload() {
  if (!targetFolderId.value) return
  router.push(`/folders/${targetFolderId.value}/create/company-csv`)
}

// Validation functions
const validateWebsite = (url: string) => {
  if (!url.trim()) return false

  try {
    const urlToTest = url.includes('://') ? url : `https://${url}`
    const parsedUrl = new URL(urlToTest)
    return parsedUrl.protocol === 'http:' || parsedUrl.protocol === 'https:'
  } catch {
    return false
  }
}

const validateCompany = (name: string) => {
  return name.trim().length >= 2
}

// Watch for changes to validate inputs
watch([company, website], ([newCompany, newWebsite]) => {
  companyError.value = ''
  websiteError.value = ''

  if (newCompany && !validateCompany(newCompany)) {
    companyError.value = t('search.fields.companyName.error')
  }

  if (newWebsite && !validateWebsite(newWebsite)) {
    websiteError.value = t('search.fields.website.error')
  }
})

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
  console.log('Contact admin for token refill')
}

const dismissTokenAlert = () => {
  showTokenAlert.value = true
}

// Handle form submission
const submit = async () => {
  // Check organization data
  if (!currentOrganization.value?.id) {
    companyError.value = t('company.validation.loadingorganization', 'Loading organization...')
    return
  }

  // Check token data
  if (tokenDataLoading.value) {
    companyError.value = t('company.validation.loadingTokens', 'Loading tokens...')
    return
  }

  // Check token availability
  if (!canPerformSearch.value) {
    if (!screenModuleEnabled.value) {
      companyError.value = t('company.validation.moduleDisabled', 'The Screen module is disabled')
    } else {
      companyError.value = t(
        'company.validation.insufficientTokens',
        'Insufficient tokens. You need at least 35 tokens to create a company.',
      )
    }
    return
  }

  // Validate form
  if (!validateCompany(company.value)) {
    companyError.value = t('search.fields.companyName.error')
    return
  }

  if (!validateWebsite(website.value)) {
    websiteError.value = t('search.fields.website.error')
    return
  }

  // Check target folder
  if (!targetFolderId.value) {
    companyError.value = t('company.validation.folderRequired', 'Please select a folder')
    return
  }

  try {
    const trimmedCompany = company.value.trim()
    const websiteUrl = website.value.includes('://') ? website.value : `https://${website.value}`

    // Create company
    const newCompany = await mutateAsync({
      name: trimmedCompany,
      website: websiteUrl,
    })

    // Add to folder
    await addToFolder({
      folderId: targetFolderId.value,
      item: {
        item_id: newCompany.id.toString(),
        item_type: 'company',
      },
    })

    // Redirect to company page
    router.push(`/folders/${targetFolderId.value}/companies/${newCompany.id}`)
  } catch (error: any) {
    console.error('Error during company creation:', error)

    if (error instanceof InsufficientTokensError) {
      companyError.value = t(
        'company.validation.insufficientTokens',
        'Insufficient tokens. You need at least 35 tokens to create a company.',
      )
      await refreshScreenTokens()
      return
    }

    if (error?.message) {
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
      } else {
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
