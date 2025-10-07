<template>
  <!-- Restore Confirmation Modal -->
  <div
    v-if="showRestoreModal && companyToRestore"
    class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50"
  >
    <div class="bg-base-100 rounded-lg shadow-xl max-w-md w-full mx-4">
      <!-- Header -->
      <div class="p-6 border-b border-primary-stroke">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
            <i class="fa fa-undo text-green-600"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-base">
              {{ $t('company.restore.title', 'Restore Company') }}
            </h3>
            <p class="text-sm text-primary-light-content">
              {{ $t('company.restore.subtitle', 'This will move the company back to the active list.') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <p class="text-sm text-primary-light-content mb-4">
          {{ $t('company.restore.warning.message', 'Restoring a company will make it visible again in the main list.') }}
        </p>

        <!-- Company Details -->
        <div class="mb-6 bg-base-200 rounded-lg p-4">
          <h4 class="font-medium text-base mb-3">
            {{ $t('company.restore.details', 'Company Details') }}
          </h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-primary-light-content">{{ $t('company.name', 'Name') }}:</span>
              <span class="font-medium">{{ companyToRestore.name }}</span>
            </div>
            <div v-if="companyToRestore.website" class="flex justify-between">
              <span class="text-primary-light-content">{{ $t('company.website', 'Website') }}:</span>
              <span class="text-xs">{{ companyToRestore.website }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="p-6 border-t border-primary-stroke flex items-center justify-end gap-3">
        <Button
          variant="secondary"
          :label="$t('common.cancel', 'Cancel')"
          @click="showRestoreModal = false"
        />
        <Button
          variant="primary"
          icon="fa fa-undo"
          :label="$t('company.restore.confirm.button', 'Restore Company')"
          :loading="restoreLoading"
          :disabled="restoreLoading"
          @click="restoreCompany"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { Company } from '@/types/company'
import Button from '@/components/ui/Button.vue'
import { restoreCompany as apiRestoreCompany } from '@/api/companies'
import { toast } from '@/utils/toast'

interface Props {
  companyToRestore: Company | null
}

const props = defineProps<Props>()

const showRestoreModal = defineModel<boolean>({
  required: true,
  default: false,
})

const emit = defineEmits<{
  'restore-company': []
}>()

const restoreLoading = ref(false)

const restoreCompany = async () => {
  if (!props.companyToRestore?.id) return

  restoreLoading.value = true

  try {
    await apiRestoreCompany(props.companyToRestore.id.toString())

    // Show success toast
    toast.success(`Company "${props.companyToRestore.name}" has been restored successfully`)

    // Emit event first, then close modal
    emit('restore-company')

    setTimeout(() => {
      showRestoreModal.value = false
    }, 50)
  } catch (err) {
    console.error('Failed to restore company:', err)
    toast.error(`Failed to restore company "${props.companyToRestore.name}". Please try again.`)
  } finally {
    restoreLoading.value = false
  }
}
</script>
