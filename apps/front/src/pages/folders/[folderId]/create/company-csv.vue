<template>
  <div class="mx-auto flex max-w-224 flex-col gap-6" data-cy="company-csv-upload-page">
    <!-- Page Header -->
    <div class="flex flex-col gap-2">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-semibold">
            {{ $t('screen.csv.upload.title') }}
          </h1>
          <p class="text-neutral-black-font">
            {{ $t('screen.csv.upload.description') }}
          </p>
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
      :required-tokens="validationResult?.tokens_required || 1"
      @contact-admin="contactAdmin"
      @refresh="refreshTokenData"
      @dismiss="dismissTokenAlert"
    />

    <!-- CSV Upload Form -->
    <div class="border-primary-lighter-stroke flex flex-col gap-6 rounded-sm border bg-white p-6">
      <!-- File Upload -->
      <div class="flex flex-col gap-4">
        <h2 class="text-lg font-medium">
          {{ $t('screen.csv.upload.step1') }}
        </h2>

        <div class="flex flex-col gap-4">
          <!-- File Input -->
          <div
            class="border-primary-lighter-stroke rounded-sm border-2 border-dashed p-6 text-center"
          >
            <input
              ref="fileInput"
              type="file"
              accept=".csv"
              class="hidden"
              @change="handleFileSelect"
            />

            <div v-if="!selectedFile" class="flex flex-col gap-2">
              <i class="fa fa-upload text-neutral-black-font text-3xl"></i>
              <div>
                <p class="text-neutral-black-font">
                  {{ $t('screen.csv.upload.dragDrop') }}
                </p>
                <Button
                  variant="tertiary"
                  :label="$t('screen.csv.upload.chooseFile')"
                  @click="fileInput?.click()"
                />
              </div>
            </div>

            <div v-else class="flex flex-col gap-2">
              <i class="fa fa-file-csv text-success text-3xl"></i>
              <p class="font-medium">{{ selectedFile.name }}</p>
              <p class="text-neutral-black-font text-sm">
                {{ formatFileSize(selectedFile.size) }}
              </p>
              <Button
                variant="tertiary"
                color="danger"
                icon="fa fa-times"
                :label="$t('screen.csv.upload.remove')"
                @click="removeFile"
              />
            </div>
          </div>

          <!-- CSV Format Help -->
          <div class="bg-info/5 border-info/20 rounded-sm border p-4">
            <h3 class="text-info mb-2 font-medium">
              <i class="fa fa-info-circle mr-2"></i>
              {{ $t('screen.csv.upload.formatTitle') }}
            </h3>
            <ul class="text-info ml-6 flex flex-col gap-1 text-sm">
              <li>
                {{ $t('screen.csv.upload.format1') }}
              </li>
              <li>
                {{ $t('screen.csv.upload.format2') }}
              </li>
              <li>
                {{ $t('screen.csv.upload.format3') }}
              </li>
              <li>{{ $t('screen.csv.upload.format4') }}</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Parse Results -->
      <div v-if="parseResult" class="flex flex-col gap-4">
        <h2 class="text-lg font-medium">
          {{ $t('screen.csv.upload.step2') }}
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

        <!-- Validation error alert -->
        <Alert
          v-if="validationError"
          variant="danger"
          :description="validationError"
          icon="fa-exclamation-circle"
        />

        <!-- Parsed Companies Preview -->
        <div v-if="parseResult.companies.length > 0" class="flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <p class="text-neutral-black-font text-sm">
              {{
                $t('screen.csv.upload.companiesFoundCount', { count: parseResult.companies.length })
              }}
            </p>
            <Button
              variant="secondary"
              :label="$t('screen.csv.upload.validateData')"
              icon="fa fa-check"
              :loading="isValidating"
              :disabled="isValidating || parseResult.companies.length === 0"
              @click="validateCompanies"
            />
          </div>

          <!-- Preview Table -->
          <div class="border-primary-lighter-stroke overflow-x-auto rounded-sm border">
            <table class="divide-primary-stroke min-w-full divide-y">
              <thead class="bg-primary-lightest">
                <tr>
                  <th
                    class="text-neutral-black-font px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
                  >
                    {{ $t('screen.csv.upload.table.row') }}
                  </th>
                  <th
                    class="text-neutral-black-font px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
                  >
                    {{ $t('screen.csv.upload.table.companyName') }}
                  </th>
                  <th
                    class="text-neutral-black-font px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
                  >
                    {{ $t('screen.csv.upload.table.website') }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-primary-stroke divide-y bg-white">
                <tr v-for="company in parseResult.companies.slice(0, 5)" :key="company.row_number">
                  <td class="px-4 py-3 text-sm">{{ company.row_number }}</td>
                  <td class="px-4 py-3 text-sm">{{ company.name || '-' }}</td>
                  <td class="px-4 py-3 text-sm">{{ company.website || '-' }}</td>
                </tr>
              </tbody>
            </table>
            <div
              v-if="parseResult.companies.length > 5"
              class="text-neutral-black-font bg-primary-lightest px-4 py-3 text-sm"
            >
              {{ $t('screen.csv.upload.moreRows', { count: parseResult.companies.length - 5 }) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Validation Results -->
      <div v-if="validationResult" class="flex flex-col gap-4">
        <h2 class="text-lg font-medium">
          {{ $t('screen.csv.upload.step3') }}
        </h2>

        <!-- Token Info -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div class="bg-success/10 border-success/20 rounded-sm border p-4">
            <div class="text-success text-sm">
              {{ $t('screen.csv.upload.validation.validCompaniesLabel') }}
            </div>
            <div class="text-success text-2xl font-bold">{{ validationResult.valid_count }}</div>
          </div>
          <div class="bg-error/10 border-error/20 rounded-sm border p-4">
            <div class="text-error text-sm">
              {{ $t('screen.csv.upload.validation.invalidCompaniesLabel') }}
            </div>
            <div class="text-error text-2xl font-bold">{{ validationResult.error_count }}</div>
          </div>
          <div class="bg-info/10 border-info/20 rounded-sm border p-4">
            <div class="text-info text-sm">
              {{ $t('screen.csv.upload.tokens.tokensRequiredLabel') }}
            </div>
            <div class="text-info text-2xl font-bold">{{ validationResult.tokens_required }}</div>
          </div>
        </div>

        <!-- Token Sufficiency -->
        <Alert
          v-if="!validationResult.has_sufficient_tokens"
          variant="danger"
          :title="$t('screen.csv.upload.tokens.insufficient')"
          :description="
            $t('screen.csv.upload.tokens.insufficientMessage', {
              required: validationResult.tokens_required,
              available: validationResult.tokens_available,
            })
          "
          icon="fa-coins"
        />

        <!-- Validation Errors -->
        <div v-if="validationResult.errors.length > 0" class="flex flex-col gap-4">
          <h3 class="text-error font-medium">
            {{ $t('screen.csv.upload.validation.errorsTitle') }}
          </h3>
          <div class="flex max-h-60 flex-col gap-2 overflow-y-auto">
            <div
              v-for="error in validationResult.errors"
              :key="`${error.row_number}-${error.field}`"
              class="bg-error/5 border-error/20 rounded-sm border p-3 text-sm"
            >
              <span class="font-medium">Row {{ error.row_number }}</span>
              - {{ error.field }}: {{ error.error }}
            </div>
          </div>

          <Alert
            variant="warning"
            :title="$t('screen.csv.upload.validation.errorsFound')"
            :message="$t('screen.csv.upload.validation.errorsFoundMessage')"
          />
        </div>

        <!-- Import Actions -->
        <div v-if="validationResult.valid_count > 0" class="flex items-center gap-4 pt-4">
          <Button
            variant="primary"
            icon="fa fa-upload"
            :label="
              $t('screen.csv.upload.actions.importCompanies', {
                count: validationResult.valid_count,
              })
            "
            :loading="isImporting"
            :disabled="isImporting || !validationResult.has_sufficient_tokens"
            @click="importCompanies"
          />

          <Button
            variant="secondary"
            icon="fa fa-edit"
            :label="$t('screen.csv.upload.actions.fixAndReupload')"
            @click="resetUpload"
          />
        </div>
      </div>

      <!-- Import Results -->
      <div v-if="importResult" class="flex flex-col gap-4">
        <h2 class="text-lg font-medium">{{ $t('screen.csv.upload.results') }}</h2>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div class="bg-success/10 border-success/20 rounded-sm border p-4">
            <div class="text-success text-sm">
              {{ $t('screen.csv.upload.results.successful') }}
            </div>
            <div class="text-success text-2xl font-bold">{{ importResult.successful }}</div>
          </div>
          <div class="bg-error/10 border-error/20 rounded-sm border p-4">
            <div class="text-error text-sm">
              {{ $t('screen.csv.upload.results.failedLabel') }}
            </div>
            <div class="text-error text-2xl font-bold">{{ importResult.failed }}</div>
          </div>
          <div class="bg-info/10 border-info/20 rounded-sm border p-4">
            <div class="text-info text-sm">
              {{ $t('screen.csv.upload.results.totalProcessed') }}
            </div>
            <div class="text-info text-2xl font-bold">{{ importResult.total_rows }}</div>
          </div>
        </div>

        <!-- Import Details -->
        <div v-if="importResult.failed > 0" class="flex max-h-60 flex-col gap-2 overflow-y-auto">
          <h3 class="text-error font-medium">
            {{ $t('screen.csv.upload.results.failedImportsTitle') }}
          </h3>
          <div
            v-for="result in importResult.results.filter((r) => !r.success)"
            :key="result.row_number"
            class="bg-error/5 border-error/20 rounded-sm border p-3 text-sm"
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
            :label="$t('screen.csv.upload.actions.goToFolder')"
            @click="goToFolder"
          />

          <Button
            variant="secondary"
            icon="fa fa-upload"
            :label="$t('screen.csv.upload.actions.uploadAnother')"
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
import {
  importCSV,
  validateCSV,
  type CSVImportResponse,
  type CSVValidationResponse,
} from '@/api/companies'
import InsufficientTokensAlert from '@/components/tokens/InsufficientTokensAlert.vue'
import TokenCounter from '@/components/tokens/TokenCounter.vue'
import { useAddItemToFolder } from '@/mutations/folders'
import { currentOrganizationQuery } from '@/queries/organization'
import { organizationBalanceQuery, organizationModulesQuery } from '@/queries/tokens'
import { parseCSVAdvanced, type CSVParseResult } from '@/utils/csvParser'
import { Alert, Button } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const { t } = useI18n()
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

// Error states
const validationError = ref<string | null>(null)

// Folder mutation — must be at setup level, not inside async functions
const { mutateAsync: addToFolder } = useAddItemToFolder()

// Fetch current organization
const { data: currentOrganization } = useQuery(() => currentOrganizationQuery())

// Global token balance query
const {
  data: balanceData,
  isLoading: tokenDataLoading,
  refetch: refetchBalance,
} = useQuery(() =>
  organizationBalanceQuery({
    organizationId: currentOrganization.value?.id ?? '',
  }),
)

// Module enablement query (to check if screen module is enabled)
const { data: modulesData } = useQuery(() =>
  organizationModulesQuery({
    organizationId: currentOrganization.value?.id ?? '',
  }),
)

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
const CSV_MIME_TYPES = ['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel']

const handleFileSelect = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  const isCsv = file && (CSV_MIME_TYPES.includes(file.type) || file.name.endsWith('.csv'))
  if (isCsv) {
    selectedFile.value = file
    parseCSVFile(file)
    // Reset previous results
    validationResult.value = null
    validationError.value = null
    importResult.value = null
  }
}

const removeFile = () => {
  selectedFile.value = null
  parseResult.value = null
  validationResult.value = null
  validationError.value = null
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
  validationError.value = null
  try {
    validationResult.value = await validateCSV({
      companies: parseResult.value.companies,
    })
  } catch (error: unknown) {
    console.error('Validation error:', error)
    validationError.value = t('screen.csv.upload.validationFailed')
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
      const folderId = route.params.folderId

      // Add each successful company to the folder
      for (const result of importResult.value.results) {
        if (result.success && result.company_id) {
          await addToFolder({
            folderId,
            item: {
              item_id: result.company_id.toString(),
              item_type: 'company',
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
  validationError.value = null
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
