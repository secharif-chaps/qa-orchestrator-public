<template>
  <div class="flex flex-col gap-6">
    <!-- Success Card -->
    <div
      v-if="results.success_count > 0"
      class="flex items-center gap-4 p-6 bg-success-light border border-success-stroke rounded-xl"
    >
      <div class="w-12 h-12 rounded-full bg-success flex items-center justify-center">
        <i class="fa-solid fa-check text-xl text-success-content" />
      </div>
      <div>
        <p class="text-lg font-semibold text-success-light-content">
          {{ $t('admin.import.successCount', { count: results.success_count }) }}
        </p>
        <p class="text-sm text-success-light-content/80">
          {{ $t('admin.import.successMessage') }}
        </p>
      </div>
    </div>

    <!-- Error Summary -->
    <div
      v-if="results.error_count > 0"
      class="flex flex-col gap-4 p-6 bg-error-light border border-error-stroke rounded-xl"
    >
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-error flex items-center justify-center">
          <i class="fa-solid fa-exclamation-triangle text-xl text-error-content" />
        </div>
        <div>
          <p class="text-lg font-semibold text-error-light-content">
            {{ $t('admin.import.errorCount', { count: results.error_count }) }}
          </p>
          <p class="text-sm text-error-light-content/80">
            {{ $t('admin.import.errorMessage') }}
          </p>
        </div>
      </div>

      <!-- Expandable Error Details -->
      <div class="space-y-2">
        <button
          class="flex items-center gap-2 text-sm text-error-light-content hover:underline"
          @click="showErrors = !showErrors"
        >
          <i
            :class="[
              'fa-solid transition-transform',
              showErrors ? 'fa-chevron-down' : 'fa-chevron-right',
            ]"
          />
          {{ showErrors ? $t('admin.import.hideDetails') : $t('admin.import.showDetails') }}
        </button>

        <div v-if="showErrors" class="space-y-2 max-h-48 overflow-y-auto">
          <div
            v-for="error in failedResults"
            :key="error.row_index"
            class="p-3 bg-error/10 rounded-lg text-sm"
          >
            <p class="font-medium text-error-light-content">
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
      class="flex flex-col gap-4 p-6 bg-info-light border border-info-stroke rounded-xl"
    >
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-info flex items-center justify-center">
          <i class="fa-solid fa-key text-xl text-info-content" />
        </div>
        <div class="flex-1">
          <p class="text-lg font-semibold text-info-light-content">
            {{ $t('admin.import.passwordsGenerated') }}
          </p>
          <p class="text-sm text-info-light-content/80">
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
      <p v-if="autoDownloaded" class="text-sm text-info-light-content/70">
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
import { downloadPasswordsCsv, hasGeneratedPasswords as checkHasPasswords } from '@/utils/downloadPasswordsCsv'
import type { BulkImportResponse } from '@/types/user-import'

interface Props {
  /** Import results from API */
  results: BulkImportResponse
}

const props = defineProps<Props>()

const emit = defineEmits<{
  done: []
}>()

const { t } = useI18n()

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
