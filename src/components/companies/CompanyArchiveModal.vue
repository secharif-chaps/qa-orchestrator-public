<template>
  <!-- Archive Confirmation Modal -->
  <div
    v-if="showArchiveModal && companyToArchive"
    class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50"
  >
    <div class="bg-base-100 rounded-lg shadow-xl max-w-md w-full mx-4">
      <!-- Header -->
      <div class="p-6 border-b border-primary-stroke">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
            <i class="fa fa-box-archive text-red-600"></i>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-base">
              {{ $t('company.archive.title', 'Archive Company') }}
            </h3>
            <p class="text-sm text-secondary">
              {{ $t('company.archive.subtitle', 'This will move the company to the archive.') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <p class="text-sm text-secondary mb-4">
          {{
            $t(
              'company.archive.warning.message',
              'Archiving a company will hide it from the main list. You can restore it later from the archived view.',
            )
          }}
        </p>

        <!-- Company Details -->
        <div class="mb-6 bg-base-200 rounded-lg p-4">
          <h4 class="font-medium text-base mb-3">
            {{ $t('company.archive.details', 'Company Details') }}
          </h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-secondary">{{ $t('company.name', 'Name') }}:</span>
              <span class="font-medium">{{ companyToArchive.name }}</span>
            </div>
            <div v-if="companyToArchive.website" class="flex justify-between">
              <span class="text-secondary">{{ $t('company.website', 'Website') }}:</span>
              <span class="text-xs">{{ companyToArchive.website }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="p-6 border-t border-primary-stroke flex items-center justify-end gap-3">
        <Button
          variant="secondary"
          :label="$t('common.cancel', 'Cancel')"
          @click="showArchiveModal = false"
        />
        <Button
          variant="primary"
          icon="fa fa-box-archive"
          color="danger"
          :label="$t('company.archive.confirm.button', 'Archive Company')"
          :loading="archiveLoading"
          :disabled="archiveLoading"
          @click="archiveCompany"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Company } from '@/types/company'
import Button from '@/components/ui/Button.vue'
import { deleteCompany as apiArchiveCompany } from '@/api/companies'
import { toast } from '@/utils/toast'

const { t } = useI18n()

interface Props {
  companyToArchive?: Company | null
}

const props = defineProps<Props>()

const showArchiveModal = defineModel<boolean>({
  required: true,
  default: false,
})

const emit = defineEmits<{
  'archive-company': []
}>()

const archiveLoading = ref(false)

const archiveCompany = async () => {
  if (!props.companyToArchive?.id) return

  archiveLoading.value = true

  try {
    await apiArchiveCompany(props.companyToArchive.id.toString())

    // Show success toast
    toast.success(
      t('company.archive.success', 'Company "{name}" has been archived successfully', {
        name: props.companyToArchive.name,
      }),
    )

    // Emit event first, then clean up
    emit('archive-company')

    // Small delay to ensure parent component processes the event
    setTimeout(() => {
      showArchiveModal.value = false
    }, 50)
  } catch (err) {
    console.error('Failed to archive company:', err)
    // Show error toast
    toast.error(
      t('company.archive.error', 'Failed to archive company "{name}". Please try again.', {
        name: props.companyToArchive.name,
      }),
    )
  } finally {
    archiveLoading.value = false
  }
}
</script>
