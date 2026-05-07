<template>
  <div class="flex flex-col gap-6">
    <!-- Success Card -->
    <div
      v-if="results.success_count > 0"
      class="bg-success-light border-success-stroke flex items-center gap-4 rounded-md border p-6"
    >
      <div class="bg-success flex h-12 w-12 items-center justify-center rounded-full">
        <i class="fa-solid fa-check text-success-content text-xl" />
      </div>
      <div>
        <p class="text-success-light-content text-lg font-semibold">
          {{ $t('admin.import.successCount', { count: results.success_count }) }}
        </p>
        <p class="text-success-light-content/80 text-sm">
          {{ $t('admin.import.successMessage') }}
        </p>
      </div>
    </div>

    <!-- Error Summary -->
    <div
      v-if="results.error_count > 0"
      class="bg-error-light border-error-stroke flex flex-col gap-4 rounded-md border p-6"
    >
      <div class="flex items-center gap-4">
        <div class="bg-error flex h-12 w-12 items-center justify-center rounded-full">
          <i class="fa-solid fa-exclamation-triangle text-error-content text-xl" />
        </div>
        <div>
          <p class="text-error-light-content text-lg font-semibold">
            {{ $t('admin.import.errorCount', { count: results.error_count }) }}
          </p>
          <p class="text-error-light-content/80 text-sm">
            {{ $t('admin.import.errorMessage') }}
          </p>
        </div>
      </div>

      <!-- Expandable Error Details -->
      <div class="space-y-2">
        <Button
          variant="tertiary"
          size="sm"
          :icon="showErrors ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"
          :label="showErrors ? $t('admin.import.hideDetails') : $t('admin.import.showDetails')"
          class="text-error-light-content hover:text-error"
          @click="showErrors = !showErrors"
        />

        <div v-if="showErrors" class="max-h-48 space-y-2 overflow-y-auto">
          <div
            v-for="error in failedResults"
            :key="error.row_index"
            class="bg-error/10 rounded-sm p-3 text-sm"
          >
            <p class="text-error-light-content font-medium">
              Row {{ error.row_index + 1 }}: {{ error.username }} ({{ error.email }})
            </p>
            <p class="text-error-light-content/80">{{ error.error_message }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Password Download Section -->
    <div
      v-if="hasGeneratedPasswords"
      class="bg-info-light border-info-stroke flex flex-col gap-4 rounded-md border p-6"
    >
      <div class="flex items-center gap-4">
        <div class="bg-info flex h-12 w-12 items-center justify-center rounded-full">
          <i class="fa-solid fa-key text-info-content text-xl" />
        </div>
        <div class="flex-1">
          <p class="text-info-light-content text-lg font-semibold">
            {{ $t('admin.import.passwordsGenerated') }}
          </p>
          <p class="text-info-light-content/80 text-sm">
            {{ $t('admin.import.passwordsMessage') }}
          </p>
        </div>
      </div>

      <div class="flex gap-3">
        <Button
          variant="primary"
          :label="$t('admin.import.downloadPasswords')"
          icon="fa-solid fa-download"
          @click="handleDownloadPasswords"
        />
      </div>

      <!-- Auto-download notice -->
      <p v-if="autoDownloaded" class="text-info-light-content/70 text-sm">
        <i class="fa-solid fa-check mr-1" />
        {{ $t('admin.import.autoDownloaded') }}
      </p>
    </div>

    <!-- Done Button -->
    <div class="flex justify-end pt-4">
      <Button
        variant="primary"
        :label="$t('common.done')"
        icon="fa-solid fa-check"
        @click="$emit('done')"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@owlint/feathers-vue'
import {
  downloadPasswordsCsv,
  hasGeneratedPasswords as checkHasPasswords,
} from '@/utils/downloadPasswordsCsv'
import type { BulkImportResponse } from '@/types/user-import'

interface Props {
  /** Import results from API */
  results: BulkImportResponse
}

const props = defineProps<Props>()

defineEmits<{
  done: []
}>()

useI18n()

// State
const showErrors = ref(false)
const autoDownloaded = ref(false)

/**
 * Check if any passwords were generated
 */
const hasGeneratedPasswords = computed(() => {
  return checkHasPasswords(props.results.results)
})

/**
 * Get failed results for display
 */
const failedResults = computed(() => {
  return props.results.results.filter((r) => !r.success)
})

/**
 * Handle password CSV download
 */
function handleDownloadPasswords(): void {
  downloadPasswordsCsv(props.results.results)
}

/**
 * Auto-download passwords on mount if they were generated
 */
onMounted(() => {
  if (hasGeneratedPasswords.value) {
    // Small delay to ensure the UI is rendered first
    setTimeout(() => {
      downloadPasswordsCsv(props.results.results)
      autoDownloaded.value = true
    }, 500)
  }
})
</script>
