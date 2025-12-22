<template>
  <div class="flex flex-col gap-6">
    <!-- Stepper -->
    <Stepper
      v-model="currentStep"
      :steps="steps"
      size="md"
      :linear="true"
    />

    <!-- Step Content -->

      <!-- Step 1: Upload -->
      <div v-if="currentStep === 1" class="flex flex-col gap-6">
        <!-- Organization Selector (only for global import) -->
        <div v-if="showOrganizationSelector">
          <label
            for="organization-select"
            class="block text-sm font-medium text-sage-700 dark:text-sage-200 mb-2"
          >
            {{ $t('admin.import.selectOrganization') }}
            <span class="text-error">*</span>
          </label>
          <select
            id="organization-select"
            v-model="selectedOrganizationId"
            class="w-full max-w-md px-3 py-2 bg-base-100 border border-primary-stroke rounded-lg text-sm text-sage-700 dark:text-sage-200 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
          >
            <option value="" disabled>
              {{ $t('admin.import.selectOrganizationPlaceholder') }}
            </option>
            <option
              v-for="org in organizations"
              :key="org.id"
              :value="org.id"
            >
              {{ org.name }}
            </option>
          </select>
        </div>

        <ImportFileUploader
          :disabled="isLoading"
          @parsed="handleFileParsed"
          @error="handleFileError"
          @clear="handleFileClear"
        />
      </div>

      <!-- Step 2: Map Columns -->
      <div v-else-if="currentStep === 2 && parsedData">
        <ImportColumnMapper
          :mappings="columnMappings"
          :generate-passwords="generatePasswords"
          :headers="parsedData.headers"
          @update:mappings="columnMappings = $event"
          @update:generate-passwords="generatePasswords = $event"
        />
      </div>

      <!-- Step 3: Review -->
      <div v-else-if="currentStep === 3">
        <ImportPreview
          :users="transformedUsers"
          :generate-passwords="generatePasswords"
          :duplicates="duplicates"
          :validation-errors="validationErrors"
        />
      </div>

      <!-- Step 4: Results -->
      <div v-else-if="currentStep === 4 && importResults">
        <ImportResults
          :results="importResults"
          @done="handleDone"
        />
      </div>


    <!-- Navigation Buttons -->
    <div
      v-if="currentStep < 4"
      class="flex justify-between items-center pt-4 border-t border-primary-stroke"
    >
      <Button
        variant="tertiary"
        :label="$t('common.cancel')"
        @click="handleCancel"
      />

      <div class="flex gap-3">
        <Button
          v-if="currentStep > 1"
          variant="secondary"
          :label="$t('common.back')"
          icon="fa-solid fa-arrow-left"
          @click="goBack"
        />

        <Button
          v-if="currentStep < 3"
          variant="primary"
          :label="$t('common.next')"
          icon-right="fa-solid fa-arrow-right"
          :disabled="!canProceed"
          @click="goNext"
        />

        <Button
          v-else-if="currentStep === 3"
          variant="primary"
          :label="$t('admin.import.importButton')"
          icon="fa-solid fa-upload"
          :loading="isImporting"
          :disabled="!canProceed || isImporting"
          @click="handleImport"
        />
      </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <Modal
      v-model:display-modal="showCancelModal"
      :title="$t('admin.import.cancelTitle')"
    >
      <p class="text-sage-600 dark:text-sage-300">
        {{ $t('admin.import.cancelMessage') }}
      </p>

      <template #footer>
        <div class="flex justify-end gap-3">
          <Button
            variant="secondary"
            :label="$t('common.cancel')"
            @click="showCancelModal = false"
          />
          <Button
            variant="primary"
            color="danger"
            :label="$t('admin.import.confirmCancel')"
            @click="confirmCancel"
          />
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { Modal, Button } from '@owlint/feathers-vue'
import Stepper from '@/components/ui/Stepper.vue'
import ImportFileUploader from './ImportFileUploader.vue'
import ImportColumnMapper from './ImportColumnMapper.vue'
import ImportPreview from './ImportPreview.vue'
import ImportResults from './ImportResults.vue'
import { useColumnMapper } from '@/composables/useColumnMapper'
import { useImportUsers } from '@/mutations/user-import'

import type {
  ParsedFileData,
  UserImportRow,
  ColumnMapping,
  BulkImportResponse,
  ValidationError,
  DuplicateInfo,
} from '@/types/user-import'
import type { StepperStep } from '@/components/ui/Stepper.vue'

interface Organization {
  id: string
  name: string
}

interface Props {
  /** Organization ID (if in org context, no selector shown) */
  organizationId?: string
  /** Available organizations for selector */
  organizations?: Organization[]
  /** URL to navigate back to on cancel/done */
  backUrl: string
}

const props = withDefaults(defineProps<Props>(), {
  organizationId: undefined,
  organizations: () => [],
})

const { t } = useI18n()
const router = useRouter()

// Mutations
const { importUsersAsync, isLoading: isImporting } = useImportUsers()

// Column mapper
const columnMapper = useColumnMapper()

// State
const currentStep = ref(1)
const parsedData = ref<ParsedFileData | null>(null)
const columnMappings = ref<ColumnMapping[]>([])
const generatePasswords = ref(true)
const selectedOrganizationId = ref<string | undefined>(props.organizationId)
const importResults = ref<BulkImportResponse | null>(null)
const showCancelModal = ref(false)
const isLoading = ref(false)

/**
 * Stepper steps configuration
 */
const steps = computed<StepperStep[]>(() => [
  {
    value: 1,
    title: t('admin.import.steps.upload'),
    icon: 'fa-solid fa-upload',
    completed: currentStep.value > 1,
  },
  {
    value: 2,
    title: t('admin.import.steps.map'),
    icon: 'fa-solid fa-columns',
    completed: currentStep.value > 2,
  },
  {
    value: 3,
    title: t('admin.import.steps.review'),
    icon: 'fa-solid fa-eye',
    completed: currentStep.value > 3,
  },
  {
    value: 4,
    title: t('admin.import.steps.results'),
    icon: 'fa-solid fa-check-circle',
    completed: false,
  },
])

/**
 * Whether to show organization selector
 */
const showOrganizationSelector = computed(() => {
  return !props.organizationId && props.organizations.length > 0
})

/**
 * Effective organization ID (from props or selected)
 */
const effectiveOrganizationId = computed(() => {
  return props.organizationId || selectedOrganizationId.value
})

/**
 * Transform parsed data to user objects
 */
const transformedUsers = computed<UserImportRow[]>(() => {
  if (!parsedData.value) return []
  return columnMapper.transformRows(parsedData.value)
})

/**
 * Validate users and find duplicates
 */
const validationErrors = computed<ValidationError[]>(() => {
  const errors: ValidationError[] = []

  transformedUsers.value.forEach((user, idx) => {
    if (!user.username || user.username.trim() === '') {
      errors.push({ row: idx, field: 'username', message: t('admin.import.errors.usernameRequired') })
    }
    if (!user.email || user.email.trim() === '') {
      errors.push({ row: idx, field: 'email', message: t('admin.import.errors.emailRequired') })
    } else if (!isValidEmail(user.email)) {
      errors.push({ row: idx, field: 'email', message: t('admin.import.errors.emailInvalid') })
    }
  })

  return errors
})

/**
 * Find duplicate emails within the import
 */
const duplicates = computed<DuplicateInfo[]>(() => {
  const seen = new Map<string, number>()
  const dups: DuplicateInfo[] = []

  transformedUsers.value.forEach((user, idx) => {
    const email = user.email?.toLowerCase()
    if (email) {
      if (seen.has(email)) {
        dups.push({
          email: user.email,
          username: user.username,
          rowIndex: idx,
          type: 'internal',
        })
      } else {
        seen.set(email, idx)
      }
    }
  })

  return dups
})

/**
 * Check if can proceed to next step
 */
const canProceed = computed(() => {
  switch (currentStep.value) {
    case 1:
      // Need file parsed and organization selected (if required)
      return parsedData.value !== null && (effectiveOrganizationId.value !== undefined)
    case 2:
      // Need all required fields mapped
      return columnMapper.isValid.value
    case 3:
      // Need at least one valid user
      const validCount = transformedUsers.value.length - validationErrors.value.length - duplicates.value.length
      return validCount > 0
    default:
      return true
  }
})

/**
 * Validate email format
 */
function isValidEmail(email: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

/**
 * Handle file parsed
 */
function handleFileParsed(data: ParsedFileData): void {
  parsedData.value = data

  // Auto-detect column mappings
  columnMapper.autoDetectMappings(data.headers)
  columnMappings.value = columnMapper.mappings.value

  // Auto-set password generation based on detection
  generatePasswords.value = !columnMapper.hasPasswordColumn.value
}

/**
 * Handle file error
 */
function handleFileError(message: string): void {
  parsedData.value = null
}

/**
 * Handle file clear
 */
function handleFileClear(): void {
  parsedData.value = null
  columnMappings.value = []
  columnMapper.reset()
}

/**
 * Go to next step
 */
function goNext(): void {
  if (canProceed.value && currentStep.value < 4) {
    currentStep.value++
  }
}

/**
 * Go to previous step
 */
function goBack(): void {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

/**
 * Handle import button click
 */
async function handleImport(): Promise<void> {
  if (!effectiveOrganizationId.value) return

  try {
    const result = await importUsersAsync({
      organization_id: effectiveOrganizationId.value,
      users: transformedUsers.value,
      generate_passwords: generatePasswords.value,
    })

    importResults.value = result
    currentStep.value = 4
  } catch (error) {
    // Error is handled by mutation
    console.error('Import failed:', error)
  }
}

/**
 * Handle cancel button click
 */
function handleCancel(): void {
  if (parsedData.value) {
    showCancelModal.value = true
  } else {
    router.push(props.backUrl)
  }
}

/**
 * Confirm cancel and navigate back
 */
function confirmCancel(): void {
  showCancelModal.value = false
  router.push(props.backUrl)
}

/**
 * Handle done button click
 */
function handleDone(): void {
  router.push(props.backUrl)
}

// Sync column mappings with composable
watch(columnMappings, (newMappings) => {
  columnMapper.mappings.value = newMappings
}, { deep: true })
</script>
