<template>
  <!-- Restore Confirmation Modal -->
  <div
    v-if="showRestoreModal && folderToRestore"
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
              {{ $t('folder.restore.title', 'Restore Folder') }}
            </h3>
            <p class="text-sm text-primary-light-content">
              {{ $t('folder.restore.subtitle', 'This will move the folder back to the active list.') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <p class="text-sm text-primary-light-content mb-4">
          {{ $t('folder.restore.warning.message', 'Restoring a folder will make it visible again in the main list.') }}
        </p>

        <!-- Folder Details -->
        <div class="mb-6 bg-base-200 rounded-lg p-4">
          <h4 class="font-medium text-base mb-3">
            {{ $t('folder.restore.details', 'Folder Details') }}
          </h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-primary-light-content">{{ $t('folder.name', 'Name') }}:</span>
              <span class="font-medium">{{ folderToRestore.name }}</span>
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
          :label="$t('folder.restore.confirm.button', 'Restore Folder')"
          :loading="restoreLoading"
          :disabled="restoreLoading"
          @click="restoreFolder"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { Folder } from '@/types/folder'
import Button from '@/components/ui/Button.vue'
import { restoreFolder as apiRestoreFolder } from '@/api/folders'
import { toast } from '@/utils/toast'

interface Props {
  folderToRestore: Folder | null
}

const props = defineProps<Props>()

const showRestoreModal = defineModel<boolean>({
  required: true,
  default: false,
})

const emit = defineEmits<{
  'restore-folder': []
}>()

const restoreLoading = ref(false)

const restoreFolder = async () => {
  if (!props.folderToRestore?.id) return

  restoreLoading.value = true

  try {
    await apiRestoreFolder(props.folderToRestore.id.toString())

    // Show success toast
    toast.success(`Folder "${props.folderToRestore.name}" has been restored successfully`)

    // Emit event first, then close modal
    emit('restore-folder')

    setTimeout(() => {
      showRestoreModal.value = false
    }, 50)
  } catch (err) {
    console.error('Failed to restore folder:', err)
    toast.error(`Failed to restore folder "${props.folderToRestore.name}". Please try again.`)
  } finally {
    restoreLoading.value = false
  }
}
</script>
