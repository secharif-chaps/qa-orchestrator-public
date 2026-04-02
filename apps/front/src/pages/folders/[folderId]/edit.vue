<template>
  <div class="min-h-screen">
    <div class="mx-auto flex max-w-2xl flex-col gap-6 px-4 py-8">
      <!-- Header -->
      <div>
        <div class="mb-2 flex items-center gap-4">
          <div
            class="flex h-12 w-12 items-center justify-center rounded-lg"
            :class="getSelectedIconColorClasses()"
          >
            <i :class="form.icon || 'fas fa-edit'" class="text-xl"></i>
          </div>
          <div>
            <h1 class="text-2xl font-bold">{{ $t('common.folder.edit.title') }}</h1>
            <p class="text-secondary">
              {{ $t('common.folder.edit.subtitle') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="bg-base-100 rounded-lg p-8 text-center shadow-sm">
        <div
          class="border-primary mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-b-2"
        ></div>
        <p class="text-secondary">
          {{ $t('common.folder.loading') }}
        </p>
      </div>

      <!-- Error State -->
      <Alert
        v-else-if="status === 'error'"
        variant="danger"
        :title="$t('common.folder.edit.error.title')"
        :description="$t('common.folder.edit.error.description')"
        icon="fa-exclamation-triangle"
      />

      <!-- Form -->
      <div
        v-else-if="folder && status === 'success'"
        class="bg-base-100 border-primary-stroke rounded-lg border p-6"
      >
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Folder Name -->
          <div>
            <label class="mb-2 block text-sm font-medium">
              {{ $t('common.folder.form.name') }}
              <span class="text-red-500">*</span>
            </label>
            <Input
              id="folder-name"
              v-model="form.name"
              :placeholder="$t('common.folder.form.namePlaceholder')"
              :error="errors.name"
              required
            />
          </div>

          <!-- Icon Selection -->
          <IconSelector v-model="form.icon" :color="form.color ?? 'blue'" />

          <!-- Color Selection -->
          <ColorSelector v-model="form.color" />

          <!-- Tags -->
          <div>
            <label class="mb-2 block text-sm font-medium">
              {{ $t('common.folder.form.tags') }}
              <span class="text-secondary ml-1 text-xs"
                >({{ $t('common.folder.form.tagsOptional') }})</span
              >
            </label>
            <Input
              id="folder-tags"
              v-model="tagsInput"
              :placeholder="$t('common.folder.form.tagsPlaceholder')"
            />
            <div v-if="form.tags && form.tags.length > 0" class="mt-2 flex flex-wrap gap-2">
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
              class="border-primary-stroke text-secondary focus:ring-primary/20 h-5 w-5 rounded"
            />
            <label for="is_favorite" class="cursor-pointer text-sm font-medium">
              {{ $t('common.folder.form.favorite') }}
            </label>
          </div>

          <!-- Actions -->
          <div class="border-primary-stroke flex justify-end gap-3 border-t pt-6">
            <Button
              type="button"
              variant="secondary"
              :label="$t('common.folder.form.cancel')"
              @click="$router.push(`/folders/${route.params.folderId}`)"
              :disabled="isMutating"
            />
            <Button
              type="submit"
              variant="primary"
              :label="$t('common.folder.form.save')"
              :loading="isMutating"
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
    - organization.write
</route>

<script setup lang="ts">
import { Alert, Button, Input } from '@owlint/feathers-vue'
import Tag from '@/components/ui/Tag.vue'
import IconSelector from '@/components/folders/IconSelector.vue'
import ColorSelector from '@/components/folders/ColorSelector.vue'
import type { FolderUpdate } from '@/types/folder'
import { ref, watch } from 'vue'
import { useUpdateFolder, useToggleFolderFavorite } from '@/mutations/folders'
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
} = useQuery(() =>
  folderByIdQuery({
    id: route.params.folderId as string,
  }),
)

// Form state
const form = ref<FolderUpdate & { is_favorite?: boolean }>({
  name: '',
  icon: 'fa-jelly-duo fa-folder',
  color: 'blue',
  tags: [],
  is_favorite: false,
})

const tagsInput = ref('')

// Validation
const errors = ref<Record<string, string>>({})

// Update mutation with optimistic UI
const { updateFolder: updateFolderMutation, isLoading: isMutating } = useUpdateFolder()
const { toggleFavorite } = useToggleFolderFavorite()

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
    errors.value.name = $t('common.folder.validation.nameRequired')
    return false
  }

  if (form.value.name.trim().length < 3) {
    errors.value.name = $t('common.folder.validation.nameMinLength')
    return false
  }

  if (form.value.name.trim().length > 50) {
    errors.value.name = $t('common.folder.validation.nameMaxLength')
    return false
  }

  return true
}

const handleSubmit = async () => {
  if (!validateForm()) return

  try {
    const { is_favorite, ...folderData } = form.value

    // Update folder metadata (name, icon, color, tags)
    await updateFolderMutation({
      folderId: route.params.folderId,
      folder: {
        ...folderData,
        name: folderData.name?.trim(),
      },
    })

    // If favorite status changed, toggle it via dedicated endpoint
    if (folder.value && is_favorite !== folder.value.is_favorite) {
      await toggleFavorite({
        folderId: route.params.folderId as string,
        shouldBeFavorite: !!is_favorite,
      })
    }

    // Redirect back to folder detail page - changes already visible via optimistic update
    router.push(`/folders/${route.params.folderId}`)
  } catch (error) {
    // Error toast is shown by the mutation's onError handler
    console.error('Error updating folder:', error)
  }
}
</script>
