<template>
  <div class="max-w-4xl mx-auto space-y-6" data-cy="company-csv-upload-page">
    <!-- Page Header -->
    <div class="space-y-2">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-semibold">{{ $t('csv.upload.title', 'Import Companies from CSV') }}</h1>
          <p class="text-secondary">{{ $t('csv.upload.description', 'Upload a CSV file to create multiple companies at once') }}</p>
        </div>

        <!-- Token Counter -->
        <div v-if="currentWorkspace" class="flex items-center gap-4">
          <div class="text-right">
            <TokenCounter
              module="screen"
              :token-count="screenTokenCount"
              :is-enabled="screenModuleEnabled"
              :is-loading="tokenDataLoading || !currentWorkspace?.id"
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
      :required-tokens="validationResult?.tokens_required || 1"
      @contact-admin="contactAdmin"
      @refresh="refreshScreenTokens"
      @dismiss="dismissTokenAlert"
    />

    <!-- CSV Upload Form -->
    <div class="bg-bg1 border border-border-2 rounded-lg p-6 space-y-6">
      <!-- File Upload -->
      <div class="space-y-4">
        <h2 class="text-lg font-medium">{{ $t('csv.upload.step1', 'Step 1: Upload CSV File') }}</h2>
        
        <div class="space-y-4">
          <!-- File Input -->
          <div class="border-2 border-dashed border-border-2 rounded-lg p-6 text-center">
            <input
              ref="fileInput"
              type="file"
              accept=".csv"
              class="hidden"
              @change="handleFileSelect"
            />
            
            <div v-if="!selectedFile" class="space-y-2">
              <i class="fa fa-upload text-3xl text-secondary"></i>
              <div>
                <p class="text-secondary">{{ $t('csv.upload.dragDrop', 'Drag and drop your CSV file here, or') }}</p>
                <Button
                  variant="tertiary"
                  label="Choose File"
                  @click="$refs.fileInput?.click()"
                />
              </div>
            </div>
            
            <div v-else class="space-y-2">
              <i class="fa fa-file-csv text-3xl text-success"></i>
              <p class="font-medium">{{ selectedFile.name }}</p>
              <p class="text-sm text-secondary">{{ formatFileSize(selectedFile.size) }}</p>
              <Button
                variant="tertiary"
                color="danger"
                icon="fa fa-times"
                label="Remove"
                @click="removeFile"
              />
            </div>
          </div>

          <!-- CSV Format Help -->
          <div class="bg-info/5 border border-info/20 rounded-lg p-4">
            <h3 class="font-medium text-info mb-2">
              <i class="fa fa-info-circle mr-2"></i>
              {{ $t('csv.upload.formatTitle', 'CSV Format Requirements') }}
            </h3>
            <ul class="text-sm text-info space-y-1 ml-6">
              <li>• {{ $t('csv.upload.format1', 'Include a header row with column names') }}</li>
              <li>• {{ $t('csv.upload.format2', 'Company name column: Name, Company, Nom, Entreprise, etc.') }}</li>
              <li>• {{ $t('csv.upload.format3', 'Website column: Website, URL, Site, Domain, etc.') }}</li>
              <li>• {{ $t('csv.upload.format4', 'Use commas to separate columns') }}</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Parse Results -->
      <div v-if="parseResult" class="space-y-4">
        <h2 class="text-lg font-medium">{{ $t('csv.upload.step2', 'Step 2: Review Parsed Data') }}</h2>
        
        <!-- Parse Errors -->
        <div v-if="parseResult.errors.length > 0" class="space-y-2">
          <Alert
            v-for="(error, index) in parseResult.errors"
            :key="index"
            variant="error"
            :message="error"
          />
        </div>

        <!-- Parsed Companies Preview -->
        <div v-if="parseResult.companies.length > 0" class="space-y-4">
          <div class="flex items-center justify-between">
            <p class="text-sm text-secondary">
              {{ $t('csv.upload.companiesFound', 'Found {count} companies in CSV', { count: parseResult.companies.length }) }}
            </p>
            <Button
              variant="secondary"
              label="Validate Data"
              icon="fa fa-check"
              :loading="isValidating"
              :disabled="isValidating || parseResult.companies.length === 0"
              @click="validateCompanies"
            />
          </div>
          
          <!-- Preview Table -->
          <div class="overflow-x-auto border border-border-2 rounded-lg">
            <table class="min-w-full divide-y divide-border-2">
              <thead class="bg-bg2">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">Row</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">Company Name</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider">Website</th>
                </tr>
              </thead>
              <tbody class="bg-bg1 divide-y divide-border-2">
                <tr v-for="company in parseResult.companies.slice(0, 5)" :key="company.row_number">
                  <td class="px-4 py-3 text-sm">{{ company.row_number }}</td>
                  <td class="px-4 py-3 text-sm">{{ company.name || '-' }}</td>
                  <td class="px-4 py-3 text-sm">{{ company.website || '-' }}</td>
                </tr>
              </tbody>
            </table>
            <div v-if="parseResult.companies.length > 5" class="px-4 py-3 text-sm text-secondary bg-bg2">
              {{ $t('csv.upload.moreRows', 'and {count} more rows...', { count: parseResult.companies.length - 5 }) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Validation Results -->
      <div v-if="validationResult" class="space-y-4">
        <h2 class="text-lg font-medium">{{ $t('csv.upload.step3', 'Step 3: Validation Results') }}</h2>
        
        <!-- Token Info -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-success/10 border border-success/20 rounded-lg p-4">
            <div class="text-sm text-success">Valid Companies</div>
            <div class="text-2xl font-bold text-success">{{ validationResult.valid_count }}</div>
          </div>
          <div class="bg-error/10 border border-error/20 rounded-lg p-4">
            <div class="text-sm text-error">Invalid Companies</div>
            <div class="text-2xl font-bold text-error">{{ validationResult.error_count }}</div>
          </div>
          <div class="bg-info/10 border border-info/20 rounded-lg p-4">
            <div class="text-sm text-info">Tokens Required</div>
            <div class="text-2xl font-bold text-info">{{ validationResult.tokens_required }}</div>
          </div>
        </div>

        <!-- Token Sufficiency -->
        <Alert
          v-if="!validationResult.has_sufficient_tokens"
          variant="error"
          title="Insufficient Tokens"
          :message="`You need ${validationResult.tokens_required} tokens but only have ${validationResult.tokens_available} available.`"
          icon="fa fa-coins"
        />

        <!-- Validation Errors -->
        <div v-if="validationResult.errors.length > 0" class="space-y-4">
          <h3 class="font-medium text-error">Validation Errors</h3>
          <div class="space-y-2 max-h-60 overflow-y-auto">
            <div
              v-for="error in validationResult.errors"
              :key="`${error.row_number}-${error.field}`"
              class="bg-error/5 border border-error/20 rounded-lg p-3 text-sm"
            >
              <span class="font-medium">Row {{ error.row_number }}</span>
              - {{ error.field }}: {{ error.error }}
            </div>
          </div>

          <Alert
            variant="warning"
            title="Validation Errors Found"
            message="You can either fix the errors in your CSV file and re-upload, or proceed with import which will skip invalid rows."
          />
        </div>

        <!-- Import Actions -->
        <div v-if="validationResult.valid_count > 0" class="flex items-center gap-4 pt-4">
          <Button
            variant="primary"
            icon="fa fa-upload"
            :label="`Import ${validationResult.valid_count} Companies`"
            :loading="isImporting"
            :disabled="isImporting || !validationResult.has_sufficient_tokens"
            @click="importCompanies"
          />
          
          <Button
            variant="secondary"
            icon="fa fa-edit"
            label="Fix CSV and Re-upload"
            @click="resetUpload"
          />
        </div>
      </div>

      <!-- Import Results -->
      <div v-if="importResult" class="space-y-4">
        <h2 class="text-lg font-medium">{{ $t('csv.upload.results', 'Import Results') }}</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-success/10 border border-success/20 rounded-lg p-4">
            <div class="text-sm text-success">Successful</div>
            <div class="text-2xl font-bold text-success">{{ importResult.successful }}</div>
          </div>
          <div class="bg-error/10 border border-error/20 rounded-lg p-4">
            <div class="text-sm text-error">Failed</div>
            <div class="text-2xl font-bold text-error">{{ importResult.failed }}</div>
          </div>
          <div class="bg-info/10 border border-info/20 rounded-lg p-4">
            <div class="text-sm text-info">Total Processed</div>
            <div class="text-2xl font-bold text-info">{{ importResult.total_rows }}</div>
          </div>
        </div>

        <!-- Import Details -->
        <div v-if="importResult.failed > 0" class="space-y-2 max-h-60 overflow-y-auto">
          <h3 class="font-medium text-error">Failed Imports</h3>
          <div
            v-for="result in importResult.results.filter(r => !r.success)"
            :key="result.row_number"
            class="bg-error/5 border border-error/20 rounded-lg p-3 text-sm"
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
            label="Go to Folder"
            @click="goToFolder"
          />
          
          <Button
            variant="secondary"
            icon="fa fa-upload"
            label="Upload Another CSV"
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
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import TokenCounter from '@/components/tokens/TokenCounter.vue'
import InsufficientTokensAlert from '@/components/tokens/InsufficientTokensAlert.vue'
import { parseCSVAdvanced, type CSVParseResult } from '@/utils/csvParser'
import { validateCSV, importCSV, type CSVValidationResponse, type CSVImportResponse } from '@/api/companies'
import { useAddItemToFolder } from '@/mutations/folders'
import { currentWorkspaceQuery } from '@/queries/workspace'
import { moduleTokensQuery } from '@/queries/tokens'
import type { ModuleName } from '@/types/tokens'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

// File handling
const fileInput = ref<HTMLInputElement>()
const selectedFile = ref<File | null>(null)
const parseResult = ref<CSVParseResult | null>(null)
const validationResult = ref<CSVValidationResponse | null>(null)
const importResult = ref<CSVImportResponse | null>(null)

// Loading states
const isValidating = ref(false)
const isImporting = ref(false)

// Token validation with real backend integration
const { data: currentWorkspace } = useQuery(currentWorkspaceQuery, () => ({}))

// Query for screen module tokens
const {
  data: screenTokenData,
  isLoading: tokenDataLoading,
  refetch: refetchTokens,
} = useQuery(
  moduleTokensQuery,
  () => ({
    workspaceId: currentWorkspace.value?.id || 0,
    module: 'screen' as ModuleName,
  }),
  {
    enabled: computed(() => !!currentWorkspace.value?.id),
  },
)

// Computed properties based on real token data
const screenTokenCount = computed(() => screenTokenData.value?.token_count ?? 0)
const screenModuleEnabled = computed(() => screenTokenData.value?.enabled ?? false)

const showInsufficientTokenAlert = computed(() => {
  // Don't show alert if data is still loading
  if (!currentWorkspace.value?.id || tokenDataLoading.value) {
    return false
  }
  
  if (!validationResult.value) return false
  
  return screenModuleEnabled.value && !validationResult.value.has_sufficient_tokens && !showTokenAlert.value
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
      errors: ['Failed to read CSV file. Please ensure it is a valid CSV format.']
    }
  }
}

// Validation
const validateCompanies = async () => {
  if (!parseResult.value?.companies.length) return
  
  isValidating.value = true
  try {
    validationResult.value = await validateCSV({
      companies: parseResult.value.companies
    })
  } catch (error: any) {
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
      skip_invalid: true
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
              item_type: 'company',
            },
          })
        }
      }
    }
  } catch (error: any) {
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
</script>