<template>
  <div class="min-h-screen">
    <div class="flex flex-col gap-6 max-w-2xl mx-auto py-8 px-4">
      <!-- Header -->
      <div>
        <div class="flex items-center gap-4 mb-2">
          <div
            class="w-12 h-12 rounded-lg flex items-center justify-center"
            :class="getSelectedIconColorClasses()"
          >
            <i :class="form.icon || 'fas fa-edit'" class="text-xl"></i>
          </div>
          <div>
            <h1 class="text-2xl font-bold">{{ $t('folder.edit.title', 'Edit Folder') }}</h1>
            <p class="text-primary-light-content">
              {{ $t('folder.edit.subtitle', 'Update your folder settings and appearance') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="bg-base-100 rounded-lg shadow-sm p-8 text-center">
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-primary-light-content">
          {{ $t('folder.loading', 'Loading folder...') }}
        </p>
      </div>

      <!-- Error State -->
      <Alert
        v-else-if="status === 'error'"
        variant="error"
        :title="$t('folder.edit.error.title', 'Error')"
        :message="$t('folder.edit.error.description', 'Failed to load folder')"
        icon="fa fa-exclamation-triangle"
      />

      <!-- Form -->
      <div
        v-else-if="folder && status === 'success'"
        class="bg-base-100 rounded-lg p-6 border border-primary-stroke"
      >
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Folder Name -->
          <div>
            <label class="block text-sm font-medium mb-2">
              {{ $t('folder.form.name', 'Folder Name') }}
              <span class="text-red-500">*</span>
            </label>
            <Input
              v-model="form.name"
              :placeholder="$t('folder.form.namePlaceholder', 'Enter folder name...')"
              :error="errors.name"
              required
            />
          </div>

          <!-- Icon Selection -->
          <IconSelector v-model="form.icon" :color="form.color" />

          <!-- Color Selection -->
          <ColorSelector v-model="form.color" />

          <!-- Tags -->
          <div>
            <label class="block text-sm font-medium mb-2">
              {{ $t('folder.form.tags', 'Tags') }}
              <span class="text-primary-light-content text-xs ml-1"
                >({{ $t('folder.form.tagsOptional', 'optional') }})</span
              >
            </label>
            <Input
              v-model="tagsInput"
              :placeholder="$t('folder.form.tagsPlaceholder', 'Enter tags separated by commas...')"
            />
            <div v-if="form.tags && form.tags.length > 0" class="flex flex-wrap gap-2 mt-2">
              <Tag
                v-for="tag in form.tags"
                :key="tag"
                :label="tag"
                variant="slate"
                size="sm"
                dismissible
                @dismiss="removeTag(tag)"
              />
            </div>
          </div>

          <!-- Favorite Toggle -->
          <div class="flex items-center gap-3">
            <input
              id="is_favorite"
              v-model="form.is_favorite"
              type="checkbox"
              class="w-5 h-5 rounded border-primary-stroke text-primary-light-content focus:ring-primary/20"
            />
            <label for="is_favorite" class="text-sm font-medium cursor-pointer">
              {{ $t('folder.form.favorite', 'Mark as favorite') }}
            </label>
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-3 pt-6 border-t border-primary-stroke">
            <Button
              type="button"
              variant="secondary"
              :label="$t('folder.form.cancel', 'Cancel')"
              @click="$router.push(`/folders/${route.params.folderId}`)"
              :disabled="isSubmitting"
            />
            <Button
              type="submit"
              variant="primary"
              :label="$t('folder.form.save', 'Save Changes')"
              :loading="isSubmitting"
            />
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - workspace.write
</route>

<script setup lang="ts">
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import Tag from '@/components/ui/Tag.vue'
import Alert from '@/components/ui/Alert.vue'
import IconSelector from '@/components/folders/IconSelector.vue'
import ColorSelector from '@/components/folders/ColorSelector.vue'
import type { FolderUpdate } from '@/types/folder'
import { ref, computed, watch, onMounted } from 'vue'
import { useUpdateFolder } from '@/mutations/folders'
import { folderByIdQuery } from '@/queries/folders'
import { useQuery } from '@pinia/colada'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

const router = useRouter()
const route = useRoute('/folders/[folderId]/edit')
const { t: $t } = useI18n()

// Query folder data
const {
  data: folder,
  status,
  isLoading,
} = useQuery(folderByIdQuery, () => ({
  id: route.params.folderId as string,
}))

// Form state
const form = ref<FolderUpdate & { is_favorite?: boolean }>({
  name: '',
  icon: 'fa-jelly-duo fa-folder',
  color: 'blue',
  tags: [],
  is_favorite: false,
})

const tagsInput = ref('')
const isSubmitting = ref(false)

// Validation
const errors = ref<Record<string, string>>({})

// Update mutation
const { mutateAsync: updateFolderMutation } = useUpdateFolder()

// Initialize form with folder data when loaded
watch(
  folder,
  (newFolder) => {
    if (newFolder) {
      form.value = {
        name: newFolder.name,
        icon: newFolder.icon || 'fa-jelly-duo fa-folder',
        color: newFolder.color || 'blue',
        tags: newFolder.tags || [],
        is_favorite: newFolder.is_favorite,
      }
    }
  },
  { immediate: true },
)

// Watch tags input for comma-separated values
watch(tagsInput, (newValue) => {
  if (newValue.includes(',')) {
    const tags = newValue
      .split(',')
      .map((tag) => tag.trim())
      .filter((tag) => tag.length > 0)

    form.value.tags = Array.from(new Set([...(form.value.tags || []), ...tags]))
    tagsInput.value = ''
  }
})

// Methods
const removeTag = (tagToRemove: string) => {
  form.value.tags = form.value.tags?.filter((tag) => tag !== tagToRemove) || []
}

const getSelectedIconColorClasses = () => {
  const color = form.value.color || 'blue'
  const colorMap: Record<string, string> = {
    red: 'bg-red-100 dark:bg-red-900/20 text-red-600 dark:text-red-400',
    orange: 'bg-orange-100 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400',
    amber: 'bg-amber-100 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400',
    yellow: 'bg-yellow-100 dark:bg-yellow-900/20 text-yellow-600 dark:text-yellow-400',
    lime: 'bg-lime-100 dark:bg-lime-900/20 text-lime-600 dark:text-lime-400',
    green: 'bg-green-100 dark:bg-green-900/20 text-green-600 dark:text-green-400',
    emerald: 'bg-emerald-100 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400',
    teal: 'bg-teal-100 dark:bg-teal-900/20 text-teal-600 dark:text-teal-400',
    cyan: 'bg-cyan-100 dark:bg-cyan-900/20 text-cyan-600 dark:text-cyan-400',
    sky: 'bg-sky-100 dark:bg-sky-900/20 text-sky-600 dark:text-sky-400',
    blue: 'bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400',
    indigo: 'bg-indigo-100 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400',
    violet: 'bg-violet-100 dark:bg-violet-900/20 text-violet-600 dark:text-violet-400',
    purple: 'bg-purple-100 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400',
    fuchsia: 'bg-fuchsia-100 dark:bg-fuchsia-900/20 text-fuchsia-600 dark:text-fuchsia-400',
    pink: 'bg-pink-100 dark:bg-pink-900/20 text-pink-600 dark:text-pink-400',
    rose: 'bg-rose-100 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400',
    gray: 'bg-gray-100 dark:bg-gray-900/20 text-gray-600 dark:text-gray-400',
  }
  return colorMap[color] || colorMap.blue
}

const validateForm = () => {
  errors.value = {}

  if (!form.value.name?.trim()) {
    errors.value.name = $t('folder.validation.nameRequired', 'Folder name is required')
    return false
  }

  if (form.value.name.trim().length < 3) {
    errors.value.name = $t(
      'folder.validation.nameMinLength',
      'Folder name must be at least 3 characters',
    )
    return false
  }

  if (form.value.name.trim().length > 50) {
    errors.value.name = $t(
      'folder.validation.nameMaxLength',
      'Folder name must be less than 50 characters',
    )
    return false
  }

  return true
}

const handleSubmit = async () => {
  if (!validateForm()) return

  isSubmitting.value = true
  try {
    const { is_favorite, ...folderData } = form.value
    await updateFolderMutation({
      folderId: route.params.folderId,
      folder: {
        ...folderData,
        name: folderData.name?.trim(),
        is_favorite,
      },
    })

    // Redirect back to folder detail page
    router.push(`/folders/${route.params.folderId}`)
  } catch (error) {
    console.error('Error updating folder:', error)
    // TODO: Show error notification
  } finally {
    isSubmitting.value = false
  }
}
</script>
