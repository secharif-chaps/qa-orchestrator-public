<template>
  <div
    v-if="modelValue"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    @click.self="$emit('update:modelValue', false)"
  >
    <div class="mx-4 w-full max-w-112 rounded-sm bg-white p-6 shadow-xl">
      <!-- Header -->
      <div class="mb-4 flex items-center gap-3">
        <div
          class="flex h-12 w-12 items-center justify-center rounded-sm bg-red-100 dark:bg-red-900/20"
        >
          <i class="fas fa-trash text-xl text-red-600 dark:text-red-400"></i>
        </div>
        <div>
          <h3 class="text-lg font-semibold">
            {{ $t('common.folder.delete.title') }}
          </h3>
          <p class="text-neutral-black-font text-sm">
            {{ $t('common.folder.delete.subtitle') }}
          </p>
        </div>
      </div>

      <!-- Warning Message -->
      <div class="mb-6">
        <p class="text-neutral-black-font mb-3">
          {{ $t('common.folder.delete.message') }}
        </p>

        <div class="bg-primary-lightest border-primary-lighter-stroke rounded-sm border p-4">
          <div class="flex items-center gap-3">
            <div
              class="flex h-8 w-8 items-center justify-center rounded-sm"
              :class="folderColorClasses"
            >
              <i :class="folderIcon" class="text-sm"></i>
            </div>
            <div>
              <div class="font-medium">{{ folderToDelete?.name }}</div>
              <div class="text-neutral-black-font text-sm">
                {{
                  $t('common.folder.itemCount', {
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
          :title="$t('common.folder.delete.warning.title')"
          :description="$t('common.folder.delete.warning.message')"
          class="mt-4"
        />
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button
          variant="secondary"
          :label="$t('common.folder.delete.cancel')"
          @click="$emit('update:modelValue', false)"
          :disabled="isDeleting"
        />
        <Button
          variant="primary"
          color="danger"
          :label="$t('common.folder.delete.confirm')"
          :loading="isDeleting"
          @click="handleDelete"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useDeleteFolder } from '@/mutations/folders'
import type { Folder } from '@/types/folder'
import { Alert, Button } from '@owlint/feathers-vue'
import { computed } from 'vue'

interface Props {
  folderToDelete: Folder | null
}

const props = defineProps<Props>()

const modelValue = defineModel<boolean>({ required: true })

const emit = defineEmits<{
  deleteFolder: []
}>()

// Mutation for deleting folders with optimistic UI
const { deleteFolder, isLoading: isDeleting } = useDeleteFolder()

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

  try {
    // Close modal immediately - folder disappears via optimistic update
    modelValue.value = false

    await deleteFolder(props.folderToDelete.id)

    // Emit event for parent (no longer needed for refetch, but kept for compatibility)
    emit('deleteFolder')
  } catch (error) {
    // Error toast is shown by the mutation's onError handler
    console.error('Error deleting folder:', error)
  }
}
</script>
