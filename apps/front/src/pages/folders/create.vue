<template>
  <div class="min-h-screen">
    <div class="mx-auto flex max-w-2xl flex-col gap-6 px-4 py-8">
      <!-- Header -->
      <div>
        <div class="mb-2 flex items-center gap-4">
          <div
            class="bg-primary/10 dark:bg-primary/20 flex h-12 w-12 items-center justify-center rounded-lg"
          >
            <i class="fas fa-plus text-secondary text-xl"></i>
          </div>
          <div>
            <h1 class="text-2xl font-bold">
              {{ $t('common.folder.create.title') }}
            </h1>
            <p class="text-secondary">
              {{ $t('common.folder.create.subtitle') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Form -->
      <div class="bg-base-100 border-primary-stroke rounded-lg border p-6">
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
              @click="$router.back()"
              :disabled="isSubmitting"
            />
            <Button
              type="submit"
              variant="primary"
              :label="$t('common.folder.form.create')"
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
    - organization.write
</route>

<script setup lang="ts">
import { Button, Input } from '@owlint/feathers-vue'
import Tag from '@/components/ui/Tag.vue'
import IconSelector from '@/components/folders/IconSelector.vue'
import ColorSelector from '@/components/folders/ColorSelector.vue'
import type { FolderCreate } from '@/types/folder'
import { ref, watch } from 'vue'
import { useCreateFolder, useToggleFolderFavorite } from '@/mutations/folders'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const router = useRouter()
const { t: $t } = useI18n()

// Mutation for creating folders with optimistic UI
const { createFolder, isLoading: isSubmitting } = useCreateFolder()
const { toggleFavorite } = useToggleFolderFavorite()

// Form state
const form = ref<FolderCreate & { is_favorite?: boolean }>({
  name: '',
  icon: 'fa-jelly-duo fa-folder',
  color: 'blue',
  tags: [],
  is_favorite: false,
})

const tagsInput = ref('')

// Validation
const errors = ref<Record<string, string>>({})

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

    // Create the folder first
    const createdFolder = await createFolder({
      ...folderData,
      name: folderData.name.trim(),
    })

    // If marked as favorite, toggle the favorite status via dedicated endpoint
    if (is_favorite && createdFolder?.id) {
      await toggleFavorite({ folderId: createdFolder.id, shouldBeFavorite: true })
    }

    // Navigate immediately - folder already appears in cache via optimistic update
    router.push('/folders')
  } catch (error) {
    // Error toast is shown by the mutation's onError handler
    console.error('Error creating folder:', error)
  }
}
</script>
