<template>
  <div class="mx-auto flex max-w-4xl flex-col gap-6" data-cy="company-csv-upload-page">
    <!-- Page Header -->
    <div class="flex flex-col gap-2">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-semibold">
            {{ $t('csv.upload.title', 'Import Companies from CSV') }}
          </h1>
          <p class="text-secondary">
            {{
              $t('csv.upload.description', 'Upload a CSV file to create multiple companies at once')
            }}
          </p>
        </div>

        <!-- Token Counter -->
        <div v-if="currentOrganization" class="flex items-center gap-4">
          <div class="text-right">
            <TokenCounter
              :token-count="tokenBalance"
              :label="$t('tokens.balance', 'Token Balance')"
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
      :required-tokens="validationResult?.tokens_required || 1"
      @contact-admin="contactAdmin"
      @refresh="refreshTokenData"
      @dismiss="dismissTokenAlert"
    />

    <!-- CSV Upload Form -->
    <div class="bg-base-100 border-primary-stroke flex flex-col gap-6 rounded-lg border p-6">
      <!-- File Upload -->
      <div class="flex flex-col gap-4">
        <h2 class="text-lg font-medium">{{ $t('csv.upload.step1', 'Step 1: Upload CSV File') }}</h2>

        <div class="flex flex-col gap-4">
          <!-- File Input -->
          <div class="border-primary-stroke rounded-lg border-2 border-dashed p-6 text-center">
            <input
              ref="fileInput"
              type="file"
              accept=".csv"
              class="hidden"
              @change="handleFileSelect"
            />

            <div v-if="!selectedFile" class="flex flex-col gap-2">
              <i class="fa fa-upload text-secondary text-3xl"></i>
              <div>
                <p class="text-secondary">
                  {{ $t('csv.upload.dragDrop', 'Drag and drop your CSV file here, or') }}
                </p>
                <Button
                  variant="tertiary"
                  :label="$t('csv.upload.chooseFile', 'Choose File')"
                  @click="fileInput?.click()"
                />
              </div>
            </div>

            <div v-else class="flex flex-col gap-2">
              <i class="fa fa-file-csv text-success text-3xl"></i>
              <p class="font-medium">{{ selectedFile.name }}</p>
              <p class="text-secondary text-sm">
                {{ formatFileSize(selectedFile.size) }}
              </p>
              <Button
                variant="tertiary"
                color="danger"
                icon="fa fa-times"
                :label="$t('csv.upload.remove', 'Remove')"
                @click="removeFile"
              />
            </div>
          </div>

          <!-- CSV Format Help -->
          <div class="bg-info/5 border-info/20 rounded-lg border p-4">
            <h3 class="text-info mb-2 font-medium">
              <i class="fa fa-info-circle mr-2"></i>
              {{ $t('csv.upload.formatTitle', 'CSV Format Requirements') }}
            </h3>
            <ul class="text-info ml-6 flex flex-col gap-1 text-sm">
              <li>{{ $t('csv.upload.format1', 'Include a header row with column names') }}</li>
              <li>
                {{
                  $t(
                    'csv.upload.format2',
                    'Company name column: Name, Company, Nom, Entreprise, etc.',
                  )
                }}
              </li>
              <li>
                {{ $t('csv.upload.format3', 'Website column: Website, URL, Site, Domain, etc.') }}
              </li>
              <li>{{ $t('csv.upload.format4', 'Use commas to separate columns') }}</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Parse Results -->
      <div v-if="parseResult" class="flex flex-col gap-4">
        <h2 class="text-lg font-medium">
          {{ $t('csv.upload.step2', 'Step 2: Review Parsed Data') }}
        </h2>

        <!-- Parse Errors -->
        <div v-if="parseResult.errors.length > 0" class="flex flex-col gap-2">
          <Alert
            v-for="(error, index) in parseResult.errors"
            :key="index"
            variant="danger"
            :description="error"
            icon="fa-exclamation-circle"
          />
        </div>

        <!-- Parsed Companies Preview -->
        <div v-if="parseResult.companies.length > 0" class="flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <p class="text-secondary text-sm">
              {{ $t('csv.upload.companiesFound', { count: parseResult.companies.length }) }}
            </p>
            <Button
              variant="secondary"
              :label="$t('csv.upload.validateData', 'Validate Data')"
              icon="fa fa-check"
              :loading="isValidating"
              :disabled="isValidating || parseResult.companies.length === 0"
              @click="validateCompanies"
            />
          </div>

          <!-- Preview Table -->
          <div class="border-primary-stroke overflow-x-auto rounded-lg border">
            <table class="divide-primary-stroke min-w-full divide-y">
              <thead class="bg-base-200">
                <tr>
                  <th
                    class="text-secondary px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
                  >
                    {{ $t('csv.upload.table.row', 'Row') }}
                  </th>
                  <th
                    class="text-secondary px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
                  >
                    {{ $t('csv.upload.table.companyName', 'Company Name') }}
                  </th>
                  <th
                    class="text-secondary px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
                  >
                    {{ $t('csv.upload.table.website', 'Website') }}
                  </th>
                </tr>
              </thead>
              <tbody class="bg-base-100 divide-primary-stroke divide-y">
                <tr v-for="company in parseResult.companies.slice(0, 5)" :key="company.row_number">
                  <td class="px-4 py-3 text-sm">{{ company.row_number }}</td>
                  <td class="px-4 py-3 text-sm">{{ company.name || '-' }}</td>
                  <td class="px-4 py-3 text-sm">{{ company.website || '-' }}</td>
                </tr>
              </tbody>
            </table>
            <div
              v-if="parseResult.companies.length > 5"
              class="text-secondary bg-base-200 px-4 py-3 text-sm"
            >
              {{ $t('csv.upload.moreRows', { count: parseResult.companies.length - 5 }) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Validation Results -->
      <div v-if="validationResult" class="flex flex-col gap-4">
        <h2 class="text-lg font-medium">
          {{ $t('csv.upload.step3', 'Step 3: Validation Results') }}
        </h2>

        <!-- Token Info -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div class="bg-success/10 border-success/20 rounded-lg border p-4">
            <div class="text-success text-sm">
              {{ $t('csv.upload.validation.validCompaniesLabel', 'Valid Companies') }}
            </div>
            <div class="text-success text-2xl font-bold">{{ validationResult.valid_count }}</div>
          </div>
          <div class="bg-error/10 border-error/20 rounded-lg border p-4">
            <div class="text-error text-sm">
              {{ $t('csv.upload.validation.invalidCompaniesLabel', 'Invalid Companies') }}
            </div>
            <div class="text-error text-2xl font-bold">{{ validationResult.error_count }}</div>
          </div>
          <div class="bg-info/10 border-info/20 rounded-lg border p-4">
            <div class="text-info text-sm">
              {{ $t('csv.upload.tokens.tokensRequiredLabel', 'Tokens Required') }}
            </div>
            <div class="text-info text-2xl font-bold">{{ validationResult.tokens_required }}</div>
          </div>
        </div>

        <!-- Token Sufficiency -->
        <Alert
          v-if="!validationResult.has_sufficient_tokens"
          variant="danger"
          :title="$t('csv.upload.tokens.insufficient', 'Insufficient tokens')"
          :description="
            $t('csv.upload.tokens.insufficientMessage', {
              required: validationResult.tokens_required,
              available: validationResult.tokens_available,
            })
          "
          icon="fa-coins"
        />

        <!-- Validation Errors -->
        <div v-if="validationResult.errors.length > 0" class="flex flex-col gap-4">
          <h3 class="text-error font-medium">
            {{ $t('csv.upload.validation.errorsTitle', 'Validation Errors') }}
          </h3>
          <div class="flex max-h-60 flex-col gap-2 overflow-y-auto">
            <div
              v-for="error in validationResult.errors"
              :key="`${error.row_number}-${error.field}`"
              class="bg-error/5 border-error/20 rounded-lg border p-3 text-sm"
            >
              <span class="font-medium">Row {{ error.row_number }}</span>
              - {{ error.field }}: {{ error.error }}
            </div>
          </div>

          <Alert
            variant="warning"
            :title="$t('csv.upload.validation.errorsFound', 'Validation Errors Found')"
            :message="
              $t(
                'csv.upload.validation.errorsFoundMessage',
                'You can either fix the errors in your CSV file and re-upload, or proceed with import which will skip invalid rows.',
              )
            "
          />
        </div>

        <!-- Import Actions -->
        <div v-if="validationResult.valid_count > 0" class="flex items-center gap-4 pt-4">
          <Button
            variant="primary"
            icon="fa fa-upload"
            :label="
              $t('csv.upload.actions.importCompanies', { count: validationResult.valid_count })
            "
            :loading="isImporting"
            :disabled="isImporting || !validationResult.has_sufficient_tokens"
            @click="importCompanies"
          />

          <Button
            variant="secondary"
            icon="fa fa-edit"
            :label="$t('csv.upload.actions.fixAndReupload', 'Fix CSV and Re-upload')"
            @click="resetUpload"
          />
        </div>
      </div>

      <!-- Import Results -->
      <div v-if="importResult" class="flex flex-col gap-4">
        <h2 class="text-lg font-medium">{{ $t('csv.upload.results', 'Import Results') }}</h2>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div class="bg-success/10 border-success/20 rounded-lg border p-4">
            <div class="text-success text-sm">
              {{ $t('csv.upload.results.successful', 'Successful') }}
            </div>
            <div class="text-success text-2xl font-bold">{{ importResult.successful }}</div>
          </div>
          <div class="bg-error/10 border-error/20 rounded-lg border p-4">
            <div class="text-error text-sm">
              {{ $t('csv.upload.results.failedLabel', 'Failed') }}
            </div>
            <div class="text-error text-2xl font-bold">{{ importResult.failed }}</div>
          </div>
          <div class="bg-info/10 border-info/20 rounded-lg border p-4">
            <div class="text-info text-sm">
              {{ $t('csv.upload.results.totalProcessed', 'Total Processed') }}
            </div>
            <div class="text-info text-2xl font-bold">{{ importResult.total_rows }}</div>
          </div>
        </div>

        <!-- Import Details -->
        <div v-if="importResult.failed > 0" class="flex max-h-60 flex-col gap-2 overflow-y-auto">
          <h3 class="text-error font-medium">
            {{ $t('csv.upload.results.failedImportsTitle', 'Failed Imports') }}
          </h3>
          <div
            v-for="result in importResult.results.filter((r) => !r.success)"
            :key="result.row_number"
            class="bg-error/5 border-error/20 rounded-lg border p-3 text-sm"
          >
            <span class="font-medium">Row {{ result.row_number }}</span>
            - {{ result.name }}: {{ result.error }}
          </div>
        </div>

        <!-- Success Actions -->
        <div class="flex items-center gap-4 pt-4">
          <Button
            variant="primary"
            icon="fa fa-folder"
            :label="$t('csv.upload.actions.goToFolder', 'Go to Folder')"
            @click="goToFolder"
          />

          <Button
            variant="secondary"
            icon="fa fa-upload"
            :label="$t('csv.upload.actions.uploadAnother', 'Upload Another CSV')"
            @click="resetUpload"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - company.create
</route>

<script lang="ts" setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { Alert, Button } from '@owlint/feathers-vue'
import TokenCounter from '@/components/tokens/TokenCounter.vue'
import InsufficientTokensAlert from '@/components/tokens/InsufficientTokensAlert.vue'
import { parseCSVAdvanced, type CSVParseResult } from '@/utils/csvParser'
import {
  validateCSV,
  importCSV,
  type CSVValidationResponse,
  type CSVImportResponse,
} from '@/api/companies'
import { useAddItemToFolder } from '@/mutations/folders'
import { currentOrganizationQuery } from '@/queries/organization'
import { organizationBalanceQuery, organizationModulesQuery } from '@/queries/tokens'

useI18n()
const router = useRouter()
const route = useRoute('/folders/[folderId]/create/company-csv')

// File handling
const fileInput = ref<HTMLInputElement>()
const selectedFile = ref<File | null>(null)
const parseResult = ref<CSVParseResult | null>(null)
const validationResult = ref<CSVValidationResponse | null>(null)
const importResult = ref<CSVImportResponse | null>(null)

// Loading states
const isValidating = ref(false)
const isImporting = ref(false)

// Fetch current organization
const { data: currentOrganization } = useQuery(currentOrganizationQuery, () => ({}))

// Global token balance query
const {
  data: balanceData,
  isLoading: tokenDataLoading,
  refetch: refetchBalance,
} = useQuery({
  ...organizationBalanceQuery({
    organizationId: currentOrganization.value?.id ?? '',
  }),
  enabled: () => !!currentOrganization.value?.id,
})

// Module enablement query (to check if screen module is enabled)
const { data: modulesData } = useQuery({
  ...organizationModulesQuery({
    organizationId: currentOrganization.value?.id ?? '',
  }),
  enabled: () => !!currentOrganization.value?.id,
})

// Computed properties based on global token balance
const tokenBalance = computed(() => balanceData.value?.balance ?? 0)
const screenModuleEnabled = computed(() => {
  if (!modulesData.value?.modules) return false
  const screenModule = modulesData.value.modules.find((m) => m.name === 'screen')
  return screenModule?.enabled ?? false
})

const showInsufficientTokenAlert = computed(() => {
  // Don't show alert if data is still loading
  if (!currentOrganization.value?.id || tokenDataLoading.value) {
    return false
  }

  if (!validationResult.value) return false

  return (
    screenModuleEnabled.value &&
    !validationResult.value.has_sufficient_tokens &&
    !showTokenAlert.value
  )
})

// Token alert state
const showTokenAlert = ref(false)
const isRefreshingTokens = ref(false)

// File handling methods
const handleFileSelect = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file && file.type === 'text/csv') {
    selectedFile.value = file
    parseCSVFile(file)
    // Reset previous results
    validationResult.value = null
    importResult.value = null
  }
}

const removeFile = () => {
  selectedFile.value = null
  parseResult.value = null
  validationResult.value = null
  importResult.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const formatFileSize = (bytes: number): string => {
  if (bytes < 1024) return bytes + ' bytes'
  if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB'
  return Math.round(bytes / (1024 * 1024)) + ' MB'
}

// CSV parsing
const parseCSVFile = async (file: File) => {
  try {
    const content = await file.text()
    parseResult.value = parseCSVAdvanced(content)
  } catch (error) {
    console.error('Error parsing CSV:', error)
    parseResult.value = {
      companies: [],
      errors: ['Failed to read CSV file. Please ensure it is a valid CSV format.'],
    }
  }
}

// Validation
const validateCompanies = async () => {
  if (!parseResult.value?.companies.length) return

  isValidating.value = true
  try {
    validationResult.value = await validateCSV({
      companies: parseResult.value.companies,
    })
  } catch (error: unknown) {
    console.error('Validation error:', error)
    // Handle validation errors appropriately
  } finally {
    isValidating.value = false
  }
}

// Import
const importCompanies = async () => {
  if (!parseResult.value?.companies.length || !validationResult.value) return

  isImporting.value = true
  try {
    importResult.value = await importCSV({
      companies: parseResult.value.companies,
      skip_invalid: true,
    })

    // Add successful companies to the folder
    if (importResult.value.successful > 0) {
      const { mutateAsync: addToFolder } = useAddItemToFolder()
      const folderId = route.params.folderId

      // Add each successful company to the folder
      for (const result of importResult.value.results) {
        if (result.success && result.company_id) {
          await addToFolder({
            folderId,
            item: {
              item_id: result.company_id.toString(),
              type: 'company',
            },
          })
        }
      }
    }
  } catch (error: unknown) {
    console.error('Import error:', error)
    // Handle import errors appropriately
  } finally {
    isImporting.value = false
  }
}

// Navigation
const goToFolder = () => {
  const folderId = route.params.folderId
  router.push(`/folders/${folderId}`)
}

const resetUpload = () => {
  selectedFile.value = null
  parseResult.value = null
  validationResult.value = null
  importResult.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

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
</script>
