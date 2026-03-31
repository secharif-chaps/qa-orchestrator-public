<template>
  <!-- Restore Confirmation Modal -->
  <div
    v-if="showRestoreModal && folderToRestore"
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
              {{ $t('common.folder.restore.title') }}
            </h3>
            <p class="text-secondary text-sm">
              {{
                $t(
                  'common.folder.restore.subtitle',
                  'This will move the folder back to the active list.',
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
              'common.folder.restore.warning.message',
              'Restoring a folder will make it visible again in the main list.',
            )
          }}
        </p>

        <!-- Folder Details -->
        <div class="bg-base-200 mb-6 rounded-lg p-4">
          <h4 class="mb-3 text-base font-medium">
            {{ $t('common.folder.restore.details') }}
          </h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-secondary">{{ $t('common.folder.name') }}:</span>
              <span class="font-medium">{{ folderToRestore.name }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="border-primary-stroke flex items-center justify-end gap-3 border-t p-6">
        <Button
          variant="secondary"
          :label="$t('common.cancel')"
          @click="showRestoreModal = false"
        />
        <Button
          variant="primary"
          icon="fa fa-undo"
          :label="$t('common.folder.restore.confirm.button')"
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
