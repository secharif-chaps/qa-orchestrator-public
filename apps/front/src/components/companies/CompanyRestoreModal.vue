<template>
  <!-- Restore Confirmation Modal -->
  <div
    v-if="showRestoreModal && companyToRestore"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
  >
    <div class="bg-base-100 mx-4 w-full max-w-md rounded-lg shadow-xl">
      <!-- Header -->
      <div class="border-primary-stroke border-b p-6">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100">
            <i class="fa fa-undo text-green-600"></i>
          </div>
          <div>
            <h3 class="text-base text-lg font-semibold">
              {{ $t('screen.company.restore.title', 'Restore Company') }}
            </h3>
            <p class="text-secondary text-sm">
              {{
                $t(
                  'screen.company.restore.subtitle',
                  'This will move the company back to the active list.',
                )
              }}
            </p>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <p class="text-secondary mb-4 text-sm">
          {{
            $t(
              'screen.company.restore.warning.message',
              'Restoring a company will make it visible again in the main list.',
            )
          }}
        </p>

        <!-- Company Details -->
        <div class="bg-base-200 mb-6 rounded-lg p-4">
          <h4 class="mb-3 text-base font-medium">
            {{ $t('screen.company.restore.details', 'Company Details') }}
          </h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-secondary">{{ $t('screen.company.name', 'Name') }}:</span>
              <span class="font-medium">{{ companyToRestore.name }}</span>
            </div>
            <div v-if="companyToRestore.website" class="flex justify-between">
              <span class="text-secondary">{{ $t('screen.company.website', 'Website') }}:</span>
              <span class="text-xs">{{ companyToRestore.website }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="border-primary-stroke flex items-center justify-end gap-3 border-t p-6">
        <Button
          variant="secondary"
          :label="$t('common.cancel', 'Cancel')"
          @click="showRestoreModal = false"
        />
        <Button
          variant="primary"
          icon="fa fa-undo"
          :label="$t('screen.company.restore.confirm.button', 'Restore Company')"
          :loading="isLoading"
          :disabled="isLoading"
          @click="handleRestore"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Company } from '@/types/company'
import { Button } from '@owlint/feathers-vue'
import { useRestoreCompany } from '@/mutations/companies'

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

// Use mutation for restoring with cache invalidation
const { restoreCompany, isLoading } = useRestoreCompany()

const handleRestore = async () => {
  if (!props.companyToRestore?.id) return

  try {
    await restoreCompany({
      companyId: props.companyToRestore.id.toString(),
      companyName: props.companyToRestore.name,
    })

    // Emit event for parent
    emit('restore-company')

    // Close modal
    showRestoreModal.value = false
  } catch (error) {
    // Error toast is shown by the mutation's onError handler
    console.error('Error restoring company:', error)
  }
}
</script>
