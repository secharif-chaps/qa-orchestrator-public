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

      <!-- Folder context description (when folder ID in route) -->
      <p v-if="routeFolderId && folderData" class="text-sm text-gray-600 dark:text-gray-400">
        {{ $t('screen.company.create.inFolder') }}
        <strong>{{ folderData.name }}</strong>
      </p>
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

    <!-- Loading State (while fetching folders) -->
    <div
      v-if="needsFolderSelection && foldersLoading"
      class="bg-base-100 border-primary-stroke rounded-lg border p-6"
    >
      <div class="flex items-center justify-center py-8">
        <div class="border-primary h-8 w-8 animate-spin rounded-full border-b-2"></div>
        <p class="text-secondary ml-4">{{ $t('common.folder.loading') }}</p>
      </div>
    </div>

    <!-- No Folders Alert -->
    <div
      v-if="
        !hasFoldersAvailable && needsFolderSelection && !foldersLoading && foldersArray !== null
      "
      class="flex flex-col gap-4"
    >
      <Alert
        variant="info"
        :title="$t('screen.company.create.noFolders.title')"
        :description="
          $t(
            'screen.company.create.noFolders.message',
            'You need to create a folder before creating a company screen',
          )
        "
        icon="fa-folder-plus"
        :action="$t('screen.company.create.noFolders.action')"
        @click="navigateToFolderCreate"
      />
    </div>

    <!-- Search Form Card -->
    <div
      v-if="!needsFolderSelection || hasFoldersAvailable"
      class="bg-base-100 border-primary-stroke rounded-lg border p-6"
      :title="$t('screen.search.companyIdentity')"
    >
      <form @submit.prevent="submit" class="flex flex-col gap-6">
        <!-- Folder Selection (when no folder ID in route) -->
        <div v-if="needsFolderSelection" class="flex flex-col gap-2">
          <label for="folder-select" class="text-sm font-medium">
            {{ $t('screen.company.create.selectFolder') }}
            <span class="text-error">*</span>
          </label>
          <Select
            v-model="selectedFolderOption"
            :options="folderSelectOptions"
            :placeholder="$t('screen.company.create.chooseFolderPlaceholder')"
            :icon="selectedFolderOption?.icon || 'fa fa-folder'"
          >
            <template #items>
              <template v-for="group in folderGroups" :key="group.label">
                <SelectGroup>
                  <SelectLabel>{{ group.label }}</SelectLabel>
                  <SelectItem v-for="option in group.options" :key="option.value" :option="option">
                    <template #icon>
                      <Icon :icon="option.icon ?? 'fa-folder'" :style="{ color: option.color }" />
                    </template>
                    {{ option.label }}
                    <span
                      v-if="option.createdAt && option.ownerUsername"
                      class="text-xs text-gray-600 dark:text-gray-100"
                    >
                      — {{ formatFolderCreationInfo(option) }}
                    </span>
                  </SelectItem>
                </SelectGroup>
              </template>
            </template>
          </Select>
        </div>

        <!-- Form Fields -->
        <div class="flex flex-col gap-4">
          <FormInput
            id="company"
            v-model="company"
            :placeholder="$t('screen.search.fields.companyName.placeholder')"
            :error="companyError"
            data-cy="company-name-input"
            required
            :label="$t('screen.search.fields.companyName.label')"
            icon="fa-building"
            @blur="handleCompanyBlur"
          />

          <FormInput
            id="website"
            v-model="website"
            :placeholder="$t('screen.search.fields.website.placeholder')"
            :error="websiteError"
            data-cy="website-input"
            required
            :label="$t('screen.search.fields.website.label')"
            icon="fa-globe"
            @blur="handleWebsiteBlur"
          />
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-between pt-4">
          <Button
            variant="tertiary"
            icon="fa fa-upload"
            :label="$t('screen.csv.upload.button')"
            @click="goToCSVUpload"
            :disabled="!targetFolderId"
          />

          <Button
            variant="primary"
            icon="fa fa-search"
            :label="$t('screen.search.actions.launchSearch')"
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
import {
  Alert,
  Button,
  Select,
  SelectGroup,
  SelectItem,
  SelectLabel,
  Icon,
} from '@owlint/feathers-vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { useCreateCompany } from '@/mutations/companies'
import { useAddItemToFolder } from '@/mutations/folders'
import { currentOrganizationQuery } from '@/queries/organization'
import { organizationBalanceQuery, organizationModulesQuery } from '@/queries/tokens'
import { foldersQuery, folderByIdQuery } from '@/queries/folders'
import { InsufficientTokensError } from '@/api/client'
import TokenCounter from '@/components/tokens/TokenCounter.vue'
import InsufficientTokensAlert from '@/components/tokens/InsufficientTokensAlert.vue'
import FormInput from '@/components/forms/FormInput.vue'

// Token cost for company creation
const TOKENS_PER_COMPANY = 35

const { t, locale } = useI18n()
const router = useRouter()
const route = useRoute()

// Folder option type (as returned by Select)
interface FolderOption {
  label: string
  value: string
  icon?: string
  color?: string
  isOwner?: boolean
  createdAt?: string
  ownerUsername?: string
}

// Folder group type for grouped Select
interface FolderGroup {
  label: string
  options: FolderOption[]
}

// Form state
const company = ref('')
const website = ref('')
const companyError = ref('')
const websiteError = ref('')

// Store just the folder ID internally
const selectedFolderId = ref<string | null>(null)

// Mutations
const {
  isLoading: mutationLoading,
  mutateAsync,
  organizationId: mutationOrgId,
} = useCreateCompany()
const { mutateAsync: addToFolder } = useAddItemToFolder()

// Fetch current organization
const { data: currentOrganization } = useQuery(() => currentOrganizationQuery())

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

// Get folder ID from route query parameter
const routeFolderId = computed(() => route.query.folderId as string | undefined)

// Determine if folder selection is needed
const needsFolderSelection = computed(() => !routeFolderId.value)

// Fetch folders (only when folder selection is needed)
const { data: foldersData, isLoading: foldersLoading } = useQuery({
  ...foldersQuery({
    filters: {
      page: 1,
      size: 100, // Get all folders
      name: '',
      archived: false,
    },
  }),
  enabled: computed(() => needsFolderSelection.value),
})

// Extract folders array - handle both API response formats:
// 1. Paginated: { data: [...], meta: {...} }
// 2. Direct array: [...]
const foldersArray = computed(() => {
  if (!foldersData.value) return null
  // Check if response is paginated (has .data property)
  if (Array.isArray(foldersData.value)) {
    return foldersData.value // Direct array format
  }
  return foldersData.value.data || null // Paginated format
})

// Transform folders to Select options - { label, value, icon, color, isOwner, createdAt, ownerUsername } format
// Only show folders user owns OR shared folders with write permission
const folderOptions = computed(() => {
  if (!foldersArray.value) return []
  return foldersArray.value
    .filter((folder) => folder.is_owner || folder.share_role === 'writer')
    .map((folder) => ({
      label: folder.name,
      value: folder.id,
      icon: folder.icon,
      color: folder.color,
      isOwner: folder.is_owner,
      createdAt: folder.created_at,
      ownerUsername: folder.owner_username,
    }))
})

// Flat options list for the Select :options prop (required for value tracking)
const folderSelectOptions = computed(() => folderOptions.value)

// Format folder creation info (shown in dropdown options only)
const formatFolderCreationInfo = (option: FolderOption) => {
  if (!option.createdAt || !option.ownerUsername) return ''
  const date = new Date(option.createdAt)
  const formattedDate = date.toLocaleDateString(locale.value, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
  const formattedTime = date.toLocaleTimeString(locale.value, {
    hour: '2-digit',
    minute: '2-digit',
  })
  return t('common.folder.tooltip.createdBy', {
    username: option.ownerUsername,
    date: formattedDate,
    time: formattedTime,
  })
}

// Group folders by ownership for Select groups
const folderGroups = computed<FolderGroup[]>(() => {
  if (!folderOptions.value.length) return []

  const myFolders = folderOptions.value.filter((f) => f.isOwner)
  const sharedFolders = folderOptions.value.filter((f) => !f.isOwner)

  const groups: FolderGroup[] = []

  if (myFolders.length > 0) {
    groups.push({
      label: t('common.folder.groups.mine'),
      options: myFolders,
    })
  }

  if (sharedFolders.length > 0) {
    groups.push({
      label: t('common.folder.groups.shared'),
      options: sharedFolders,
    })
  }

  return groups
})

// Writable computed that maps between folder ID and option object
// This ensures we always return the same object reference from folderOptions
const selectedFolderOption = computed({
  get: () => {
    if (!selectedFolderId.value) return null
    return folderOptions.value.find((opt) => opt.value === selectedFolderId.value) ?? null
  },
  set: (option: FolderOption | null) => {
    selectedFolderId.value = option?.value ?? null
  },
})

// Target folder ID (from route or selected)
const targetFolderId = computed(() => routeFolderId.value || selectedFolderId.value)

// Computed property to check if folders are available (DRY for v-if conditions)
const hasFoldersAvailable = computed(() => {
  return (
    needsFolderSelection.value &&
    !foldersLoading.value &&
    foldersArray.value !== null &&
    foldersArray.value.length > 0
  )
})

// Fetch folder details (when folder ID is in route)
const { data: folderData } = useQuery({
  ...folderByIdQuery({ id: routeFolderId.value || '' }),
  enabled: computed(() => !!routeFolderId.value),
})

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

const tokenBalance = computed(() => balanceData.value?.balance ?? 0)
const screenModuleEnabled = computed(() => {
  if (!modulesData.value?.modules) return false
  const screenModule = modulesData.value.modules.find((m) => m.name === 'screen')
  return screenModule?.enabled ?? false
})

const canPerformSearch = computed(() => {
  if (!currentOrganization.value?.id || tokenDataLoading.value) {
    return false
  }
  return screenModuleEnabled.value && tokenBalance.value >= TOKENS_PER_COMPANY
})

const showInsufficientTokenAlert = computed(() => {
  if (!currentOrganization.value?.id || tokenDataLoading.value) {
    return false
  }
  return (
    screenModuleEnabled.value && tokenBalance.value < TOKENS_PER_COMPANY && !showTokenAlert.value
  )
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

  // Check for spaces and other invalid characters that shouldn't be in a URL
  if (/\s/.test(url)) {
    return false
  }

  // Strict URL pattern validation
  // Must have: optional protocol, optional www, domain name, and TLD (at least 2 chars)
  // Examples: example.com, www.example.com, https://example.com, sub.example.co.uk
  const urlPattern =
    /^(https?:\/\/)?(www\.)?[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}(\/.*)?$/

  if (!urlPattern.test(url)) {
    return false
  }

  // Additional check with URL constructor for protocol validation
  try {
    const urlToTest = url.includes('://') ? url : `https://${url}`
    const parsedUrl = new URL(urlToTest)

    // Must be http or https protocol
    if (parsedUrl.protocol !== 'http:' && parsedUrl.protocol !== 'https:') {
      return false
    }

    // Hostname must have at least one dot (e.g., example.com)
    // This prevents "www" or "localhost" from being valid
    if (!parsedUrl.hostname.includes('.')) {
      return false
    }

    // Hostname must end with a TLD (at least 2 characters after last dot)
    const parts = parsedUrl.hostname.split('.')
    const tld = parts[parts.length - 1]
    if (!tld || tld.length < 2) {
      return false
    }

    return true
  } catch {
    return false
  }
}

const validateCompany = (name: string) => {
  return name.trim().length >= 2
}

// Handle company name blur - validate and show/hide errors
const handleCompanyBlur = () => {
  const trimmed = company.value.trim()

  // Don't show error for empty field (HTML5 required handles this)
  if (!trimmed) {
    companyError.value = ''
    return
  }

  // Validate and set error if invalid
  if (!validateCompany(trimmed)) {
    companyError.value = t('screen.search.fields.companyName.error')
  } else {
    companyError.value = ''
  }
}

// Handle website blur - validate and show/hide errors
const handleWebsiteBlur = () => {
  const trimmed = website.value.trim()

  // Don't show error for empty field (HTML5 required handles this)
  if (!trimmed) {
    websiteError.value = ''
    return
  }

  // Validate and set error if invalid
  if (!validateWebsite(trimmed)) {
    websiteError.value = t('screen.search.fields.website.error')
  } else {
    websiteError.value = ''
  }
}

// Watch locale changes and update error messages
watch(locale, () => {
  // Re-translate error messages if they exist
  if (companyError.value) {
    companyError.value = t('screen.search.fields.companyName.error')
  }
  if (websiteError.value) {
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

// TODO: Implement contact admin functionality
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
    companyError.value = t(
      'screen.company.validation.loadingorganization',
      'Loading organization...',
    )
    return
  }

  // Check token data
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

  // Validate form
  if (!validateCompany(company.value)) {
    companyError.value = t('screen.search.fields.companyName.error')
    return
  }

  if (!validateWebsite(website.value)) {
    websiteError.value = t('screen.search.fields.website.error')
    return
  }

  // Check target folder
  if (!targetFolderId.value) {
    companyError.value = t('screen.company.validation.folderRequired')
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
    if (newCompany.id === undefined) {
      throw new Error('Company ID is undefined')
    }
    await addToFolder({
      folderId: targetFolderId.value as string,
      item: {
        item_id: newCompany.id.toString(),
        item_type: 'company',
      },
    })

    // Redirect to company page
    router.push(`/folders/${targetFolderId.value}/companies/${newCompany.id}`)
  } catch (error: unknown) {
    console.error('Error during company creation:', error)

    if (error instanceof InsufficientTokensError) {
      companyError.value = t(
        'screen.company.validation.insufficientTokens',
        'Insufficient tokens. You need at least 35 tokens to create a company.',
      )
      await refreshTokenData()
      return
    }

    const message = error instanceof Error ? error.message : undefined
    if (message) {
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
      } else {
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

<style scoped>
/* Apply red border only to the specific Input component that has an error */
.flex-col.gap-1:has(> p.text-error) :deep(input) {
  border-color: var(--color-error) !important;
  outline: 1px solid var(--color-error) !important;
}
</style>
