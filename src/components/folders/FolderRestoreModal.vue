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
            <p class="text-sm text-secondary">
              {{
                $t('folder.restore.subtitle', 'This will move the folder back to the active list.')
              }}
            </p>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <p class="text-sm text-secondary mb-4">
          {{
            $t(
              'folder.restore.warning.message',
              'Restoring a folder will make it visible again in the main list.',
            )
          }}
        </p>

        <!-- Folder Details -->
        <div class="mb-6 bg-base-200 rounded-lg p-4">
          <h4 class="font-medium text-base mb-3">
            {{ $t('folder.restore.details', 'Folder Details') }}
          </h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-secondary">{{ $t('folder.name', 'Name') }}:</span>
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
          :loading="isLoading"
          :disabled="isLoading"
          @click="handleRestore"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Folder } from '@/types/folder'
import { Button } from '@owlint/feathers-vue'
import { useRestoreFolder } from '@/mutations/folders'

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

// Use mutation for restoring with cache invalidation
const { restoreFolder, isLoading } = useRestoreFolder()

const handleRestore = async () => {
  if (!props.folderToRestore?.id) return

  try {
    await restoreFolder({
      folderId: props.folderToRestore.id.toString(),
      folderName: props.folderToRestore.name,
    })

    // Emit event for parent
    emit('restore-folder')

    // Close modal
    showRestoreModal.value = false
  } catch (error) {
    // Error toast is shown by the mutation's onError handler
    console.error('Error restoring folder:', error)
  }
}
</script>
