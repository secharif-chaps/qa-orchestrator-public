<template>
  <div
    v-if="modelValue"
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    @click.self="$emit('update:modelValue', false)"
  >
    <div class="bg-base-100 rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
      <!-- Header -->
      <div class="flex items-center gap-3 mb-4">
        <div
          class="w-12 h-12 rounded-lg bg-red-100 dark:bg-red-900/20 flex items-center justify-center"
        >
          <i class="fas fa-trash text-red-600 dark:text-red-400 text-xl"></i>
        </div>
        <div>
          <h3 class="text-lg font-semibold">{{ $t('folder.delete.title', 'Delete Folder') }}</h3>
          <p class="text-sm text-secondary">
            {{ $t('folder.delete.subtitle', 'This action cannot be undone') }}
          </p>
        </div>
      </div>

      <!-- Warning Message -->
      <div class="mb-6">
        <p class="text-secondary mb-3">
          {{ $t('folder.delete.message', 'Are you sure you want to delete this folder?') }}
        </p>

        <div class="bg-base-200 border border-primary-stroke rounded-lg p-4">
          <div class="flex items-center gap-3">
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center"
              :class="folderColorClasses"
            >
              <i :class="folderIcon" class="text-sm"></i>
            </div>
            <div>
              <div class="font-medium">{{ folderToDelete?.name }}</div>
              <div class="text-sm text-secondary">
                {{
                  $t('folder.itemCount', '{count} items', {
                    count: folderToDelete?.items?.length || folderToDelete?.items_count || 0,
                  })
                }}
              </div>
            </div>
          </div>
        </div>

        <Alert
          v-if="folderToDelete?.items && folderToDelete.items.length > 0"
          variant="warning"
          :title="$t('folder.delete.warning.title', 'Items will not be deleted')"
          :message="
            $t(
              'folder.delete.warning.message',
              'Companies in this folder will remain accessible but will no longer be organized in this folder.',
            )
          "
          class="mt-4"
        />
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button
          variant="secondary"
          :label="$t('folder.delete.cancel', 'Cancel')"
          @click="$emit('update:modelValue', false)"
          :disabled="isDeleting"
        />
        <Button
          variant="primary"
          color="danger"
          :label="$t('folder.delete.confirm', 'Delete Folder')"
          :loading="isDeleting"
          @click="handleDelete"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Alert from '@/components/ui/Alert.vue'
import Button from '@/components/ui/Button.vue'
import type { Folder } from '@/types/folder'
import { computed, ref } from 'vue'
import { deleteFolder } from '@/api/folders'
import { toast } from '@/utils/toast'

interface Props {
  folderToDelete: Folder | null
}

const props = defineProps<Props>()

const modelValue = defineModel<boolean>({ required: true })

const emit = defineEmits<{
  deleteFolder: []
}>()

const isDeleting = ref(false)

// Compute folder color classes based on the color prop
const folderColorClasses = computed(() => {
  const color = props.folderToDelete?.color || 'blue'
  const colorMap: Record<string, string> = {
    blue: 'bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400',
    green: 'bg-green-100 dark:bg-green-900/20 text-green-600 dark:text-green-400',
    yellow: 'bg-yellow-100 dark:bg-yellow-900/20 text-yellow-600 dark:text-yellow-400',
    red: 'bg-red-100 dark:bg-red-900/20 text-red-600 dark:text-red-400',
    purple: 'bg-purple-100 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400',
    gray: 'bg-gray-100 dark:bg-gray-900/20 text-gray-600 dark:text-gray-400',
    orange: 'bg-orange-100 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400',
    pink: 'bg-pink-100 dark:bg-pink-900/20 text-pink-600 dark:text-pink-400',
  }
  return colorMap[color] || colorMap.blue
})

// Compute folder icon
const folderIcon = computed(() => {
  return props.folderToDelete?.icon || 'fas fa-folder'
})

const handleDelete = async () => {
  if (!props.folderToDelete) return

  isDeleting.value = true
  try {
    await deleteFolder(props.folderToDelete.id)

    // Show success toast
    toast.success(`folder "${props.folderToDelete.name}" has been deleted successfully`)

    // Emit event first, then clean up
    emit('deleteFolder')

    // Small delay to ensure parent component processes the event
    setTimeout(() => {
      modelValue.value = false
    }, 50)
  } catch (error) {
    console.error('Error deleting folder:', error)
    // TODO: Show error toast/notification
  } finally {
    isDeleting.value = false
  }
}
</script>
