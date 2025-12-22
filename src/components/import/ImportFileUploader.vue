<template>
  <div class="flex flex-col gap-4">
    <!-- Sample CSV Download Info -->
    <Alert
      variant="info"
      :title="$t('admin.import.needHelpTitle')"
      :description="$t('admin.import.needHelpMessage')"
      icon="fa-solid fa-circle-info"
    >
      <template #action>
        <Button
          variant="tertiary"
          icon="fa-solid fa-download"
          :label="$t('admin.import.downloadSample')"
          @click="downloadSample"
        />
      </template>
    </Alert>

    <!-- Drop Zone -->
    <div
      :class="[
        'relative flex flex-col items-center justify-center gap-4 p-8',
        'border-2 border-dashed rounded-xl transition-all duration-200',
        isDragging
          ? 'border-primary bg-primary-light/30'
          : 'border-primary-stroke bg-base-100 hover:border-sage-500 ',
        disabled && 'opacity-50 cursor-not-allowed',
      ]"
      @dragover.prevent="handleDragOver"
      @dragleave.prevent="handleDragLeave"
      @drop.prevent="handleDrop"
    >
      <!-- Icon -->
      <div
        :class="[
          'w-16 h-16 rounded-full flex items-center justify-center',
          isDragging ? 'bg-primary text-primary-content' : 'bg-base-200 text-sage-500',
        ]"
      >
        <i
          :class="[
            isParsing ? 'fa-solid fa-spinner animate-spin' : 'fa-solid fa-file-csv',
            'text-2xl',
          ]"
        />
      </div>

      <!-- Text -->
      <div class="text-center">
        <p class="text-base font-medium text-sage-700 dark:text-sage-200">
          {{ $t('admin.import.dropZoneTitle') }}
        </p>
        <p class="text-sm text-sage-500 dark:text-sage-400">
          {{ $t('admin.import.dropZoneSubtitle') }}
        </p>
      </div>

      <!-- Browse Button -->
      <Button
        :label="$t('admin.import.browseFiles')"
        icon="fa-solid fa-folder-open"
        :disabled="disabled || isParsing"
        @click="openFilePicker"
      />

      <!-- Hidden File Input -->
      <input
        ref="fileInputRef"
        type="file"
        accept=".csv,.xlsx"
        class="hidden"
        :disabled="disabled"
        @change="handleFileChange"
      />
    </div>

    <!-- File Info (when file is selected) -->
    <div
      v-if="parsedData"
      class="flex items-center gap-4 p-4 bg-success-light border border-success-stroke rounded-xl"
    >
      <div class="w-10 h-10 rounded-full bg-success flex items-center justify-center">
        <i class="fa-solid fa-check text-success-content" />
      </div>
      <div class="flex-1">
        <p class="font-medium text-success-light-content">{{ parsedData.fileName }}</p>
        <p class="text-sm text-success-light-content/80">
          {{ formatFileSize(parsedData.fileSize) }} • {{ parsedData.rowCount }}
          {{ $t('admin.import.rows') }}
        </p>
      </div>
      <Button
        variant="tertiary"
        color="danger"
        icon="fa-solid fa-times"
        icon-only
        @click="clearFile"
      />
    </div>

    <!-- Error Message -->
    <Alert
      v-if="error"
      variant="danger"
      :title="$t('admin.import.parseError')"
      :message="error"
      icon="fa-solid fa-exclamation-circle"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button, Alert } from '@owlint/feathers-vue'
import { useCsvParser } from '@/composables/useCsvParser'
import { downloadSampleUsersCsv } from '@/utils/downloadSampleCsv'
import type { ParsedFileData } from '@/types/user-import'

interface Props {
  /** Disable the uploader */
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
})

const emit = defineEmits<{
  parsed: [data: ParsedFileData]
  error: [message: string]
  clear: []
}>()

const { t } = useI18n()

// State
const fileInputRef = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const parsedData = ref<ParsedFileData | null>(null)
const error = ref<string | null>(null)
const isParsing = ref(false)

// CSV Parser
const csvParser = useCsvParser()

/**
 * Format file size for display
 */
function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

/**
 * Download sample CSV
 */
function downloadSample(): void {
  downloadSampleUsersCsv()
}

/**
 * Open file picker dialog
 */
function openFilePicker(): void {
  fileInputRef.value?.click()
}

/**
 * Handle drag over event
 */
function handleDragOver(): void {
  if (props.disabled) return
  isDragging.value = true
}

/**
 * Handle drag leave event
 */
function handleDragLeave(): void {
  isDragging.value = false
}

/**
 * Handle file drop
 */
async function handleDrop(event: DragEvent): Promise<void> {
  isDragging.value = false

  if (props.disabled) return

  const files = event.dataTransfer?.files
  if (files && files.length > 0) {
    await processFile(files[0])
  }
}

/**
 * Handle file input change
 */
async function handleFileChange(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const files = input.files

  if (files && files.length > 0) {
    await processFile(files[0])
  }

  // Reset input so same file can be selected again
  input.value = ''
}

/**
 * Process selected file
 */
async function processFile(file: File): Promise<void> {
  error.value = null
  parsedData.value = null
  isParsing.value = true

  try {
    const data = await csvParser.parseFile(file)
    parsedData.value = data
    emit('parsed', data)
  } catch (err) {
    const errorMessage = err instanceof Error ? err.message : 'Failed to parse file'
    error.value = errorMessage
    emit('error', errorMessage)
  } finally {
    isParsing.value = false
  }
}

/**
 * Clear selected file
 */
function clearFile(): void {
  parsedData.value = null
  error.value = null
  csvParser.reset()
  emit('clear')
}

// Watch for external reset
watch(
  () => props.disabled,
  (newDisabled) => {
    if (newDisabled) {
      clearFile()
    }
  },
)
</script>
