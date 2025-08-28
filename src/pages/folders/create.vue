<template>
  <div class="min-h-screen bg-bg3">
    <div class="flex flex-col gap-6 max-w-2xl mx-auto py-8 px-4">
      <!-- Header -->
      <div>
        <div class="flex items-center gap-4 mb-2">
          <div
            class="w-12 h-12 rounded-lg bg-primary/10 dark:bg-primary/20 flex items-center justify-center"
          >
            <i class="fas fa-plus text-primary text-xl"></i>
          </div>
          <div>
            <h1 class="text-2xl font-bold">{{ $t('folder.create.title', 'Create New Folder') }}</h1>
            <p class="text-secondary">
              {{ $t('folder.create.subtitle', 'Organize your companies with a custom folder') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Form -->
      <div class="bg-bg1 rounded-lg p-6 border border-border-2">
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
          <div>
            <label class="block text-sm font-medium mb-2">
              {{ $t('folder.form.icon', 'Icon') }}
            </label>
            <div class="relative">
              <button
                type="button"
                class="w-full flex items-center gap-3 px-4 py-3 bg-bg2 border border-border-2 rounded-lg hover:border-primary/50 transition-colors"
                @click="showIconDropdown = !showIconDropdown"
              >
                <div
                  :class="getSelectedIconColorClasses()"
                  class="w-10 h-10 rounded-lg flex items-center justify-center"
                >
                  <i :class="form.icon" class="text-lg"></i>
                </div>
                <span class="flex-1 text-left">{{ getIconDisplayName(form.icon) }}</span>
                <i class="fas fa-chevron-down text-secondary"></i>
              </button>

              <!-- Backdrop -->
              <div
                v-if="showIconDropdown"
                class="fixed inset-0 z-40"
                @click="showIconDropdown = false"
              ></div>

              <!-- Icon Grid Dropdown -->
              <div
                v-if="showIconDropdown"
                class="absolute top-full mt-2 w-full bg-bg1 border border-border-2 rounded-lg shadow-lg z-50 p-4"
              >
                <div class="grid grid-cols-5 gap-2">
                  <button
                    v-for="icon in availableIcons"
                    :key="icon.class"
                    type="button"
                    class="w-12 h-12 rounded-lg flex items-center justify-center transition-all hover:bg-bg2 border-2"
                    :class="
                      form.icon === icon.class
                        ? 'border-primary bg-primary/10'
                        : 'border-transparent'
                    "
                    @click="selectIcon(icon.class)"
                    :title="icon.name"
                  >
                    <i :class="icon.class" class="text-lg text-secondary"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Color Selection -->
          <div>
            <label class="block text-sm font-medium mb-2">
              {{ $t('folder.form.color', 'Color') }}
            </label>
            <div class="relative">
              <button
                type="button"
                class="w-full flex items-center gap-3 px-4 py-3 bg-bg2 border border-border-2 rounded-lg hover:border-primary/50 transition-colors"
                @click="showColorDropdown = !showColorDropdown"
              >
                <div class="w-6 h-6 rounded-lg" :class="getColorPreviewClasses()"></div>
                <span class="flex-1 text-left capitalize">{{ form.color }}</span>
                <i class="fas fa-chevron-down text-secondary"></i>
              </button>

              <!-- Backdrop -->
              <div
                v-if="showColorDropdown"
                class="fixed inset-0 z-40"
                @click="showColorDropdown = false"
              ></div>

              <!-- Color Grid Dropdown -->
              <div
                v-if="showColorDropdown"
                class="absolute top-full mt-2 w-full bg-bg1 border border-border-2 rounded-lg shadow-lg z-50 p-4"
              >
                <div class="grid grid-cols-6 gap-2">
                  <button
                    v-for="color in availableColors"
                    :key="color"
                    type="button"
                    class="w-10 h-10 rounded-lg border-2 transition-all hover:scale-110"
                    :class="[
                      getColorClasses(color),
                      form.color === color ? 'border-primary' : 'border-transparent',
                    ]"
                    @click="selectColor(color)"
                    :title="color"
                  ></button>
                </div>
              </div>
            </div>
          </div>

          <!-- Tags -->
          <div>
            <label class="block text-sm font-medium mb-2">
              {{ $t('folder.form.tags', 'Tags') }}
              <span class="text-secondary text-xs ml-1"
                >({{ $t('folder.form.tagsOptional', 'optional') }})</span
              >
            </label>
            <Input
              v-model="tagsInput"
              :placeholder="$t('folder.form.tagsPlaceholder', 'Enter tags separated by commas...')"
            />
            <div v-if="form.tags && form.tags.length > 0" class="flex flex-wrap gap-2 mt-2">
              <Badge
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
              class="w-5 h-5 rounded border-border-2 text-primary focus:ring-primary/20"
            />
            <label for="is_favorite" class="text-sm font-medium cursor-pointer">
              {{ $t('folder.form.favorite', 'Mark as favorite') }}
            </label>
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-3 pt-6 border-t border-border-2">
            <Button
              type="button"
              variant="secondary"
              :label="$t('folder.form.cancel', 'Cancel')"
              @click="$router.back()"
              :disabled="isSubmitting"
            />
            <Button
              type="submit"
              variant="primary"
              :label="$t('folder.form.create', 'Create Folder')"
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
import Badge from '@/components/ui/Badge.vue'
import type { FolderCreate } from '@/types/folder'
import { ref, computed, watch } from 'vue'
import { createFolder } from '@/api/folders'
import { useRouter } from 'vue-router'

const router = useRouter()

// Form state
const form = ref<FolderCreate & { is_favorite?: boolean }>({
  name: '',
  icon: 'fa-jelly-duo fa-folder',
  color: 'blue',
  tags: [],
  is_favorite: false,
})

const tagsInput = ref('')
const isSubmitting = ref(false)
const showIconDropdown = ref(false)
const showColorDropdown = ref(false)

// Validation
const errors = ref<Record<string, string>>({})

// Available icons (25 FontAwesome jelly-duo icons)
const availableIcons = [
  { class: 'fa-jelly-duo fa-folder', name: 'Folder' },
  { class: 'fa-jelly-duo fa-fire', name: 'Fire' },
  { class: 'fa-jelly-duo fa-cloud', name: 'Cloud' },
  { class: 'fa-jelly-duo fa-music', name: 'Music' },
  { class: 'fa-jelly-duo fa-heart', name: 'Heart' },
  { class: 'fa-jelly-duo fa-star', name: 'Star' },
  { class: 'fa-jelly-duo fa-bomb', name: 'Bomb' },
  { class: 'fa-jelly-duo fa-droplet', name: 'Droplet' },
  { class: 'fa-jelly-duo fa-user', name: 'User' },
  { class: 'fa-jelly-duo fa-suitcase', name: 'Suitcase' },
  { class: 'fa-jelly-duo fa-bookmark', name: 'Bookmark' },
  { class: 'fa-jelly-duo fa-tree', name: 'Tree' },
  { class: 'fa-jelly-duo fa-lightbulb', name: 'Lightbulb' },
  { class: 'fa-jelly-duo fa-paper-plane', name: 'Paper Plane' },
  { class: 'fa-jelly-duo fa-shield', name: 'Shield' },
  { class: 'fa-jelly-duo fa-crown', name: 'Crown' },
  { class: 'fa-jelly-duo fa-leaf', name: 'Leaf' },
  { class: 'fa-jelly-duo fa-snowflake', name: 'Snowflake' },
  { class: 'fa-jelly-duo fa-sun', name: 'Sun' },
  { class: 'fa-jelly-duo fa-moon', name: 'Moon' },
  { class: 'fa-jelly-duo fa-bolt', name: 'Bolt' },
  { class: 'fa-jelly-duo fa-compass', name: 'Compass' },
  { class: 'fa-jelly-duo fa-flag', name: 'Flag' },
  { class: 'fa-jelly-duo fa-gift', name: 'Gift' },
  { class: 'fa-jelly-duo fa-camera', name: 'Camera' },
]

// Available colors (Tailwind 400 intensity)
const availableColors = [
  'red',
  'orange',
  'amber',
  'yellow',
  'lime',
  'green',
  'emerald',
  'teal',
  'cyan',
  'sky',
  'blue',
  'indigo',
  'violet',
  'purple',
  'fuchsia',
  'pink',
  'rose',
  'gray',
]

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
const selectIcon = (iconClass: string) => {
  form.value.icon = iconClass
  showIconDropdown.value = false
}

const selectColor = (color: string) => {
  form.value.color = color
  showColorDropdown.value = false
}

const removeTag = (tagToRemove: string) => {
  form.value.tags = form.value.tags?.filter((tag) => tag !== tagToRemove) || []
}

const getIconDisplayName = (iconClass: string) => {
  return availableIcons.find((icon) => icon.class === iconClass)?.name || 'Folder'
}

const getColorClasses = (color: string) => {
  const colorMap: Record<string, string> = {
    red: 'bg-red-400',
    orange: 'bg-orange-400',
    amber: 'bg-amber-400',
    yellow: 'bg-yellow-400',
    lime: 'bg-lime-400',
    green: 'bg-green-400',
    emerald: 'bg-emerald-400',
    teal: 'bg-teal-400',
    cyan: 'bg-cyan-400',
    sky: 'bg-sky-400',
    blue: 'bg-blue-400',
    indigo: 'bg-indigo-400',
    violet: 'bg-violet-400',
    purple: 'bg-purple-400',
    fuchsia: 'bg-fuchsia-400',
    pink: 'bg-pink-400',
    rose: 'bg-rose-400',
    gray: 'bg-gray-400',
  }
  return colorMap[color] || colorMap.blue
}

const getColorPreviewClasses = () => {
  return getColorClasses(form.value.color || 'blue')
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
    errors.value.name = 'Folder name is required'
    return false
  }

  if (form.value.name.trim().length < 3) {
    errors.value.name = 'Folder name must be at least 3 characters'
    return false
  }

  if (form.value.name.trim().length > 50) {
    errors.value.name = 'Folder name must be less than 50 characters'
    return false
  }

  return true
}

const handleSubmit = async () => {
  if (!validateForm()) return

  isSubmitting.value = true
  try {
    const { is_favorite, ...folderData } = form.value
    await createFolder({
      ...folderData,
      name: folderData.name.trim(),
    })

    router.push('/folders')
  } catch (error) {
    console.error('Error creating folder:', error)
    // TODO: Show error notification
  } finally {
    isSubmitting.value = false
  }
}
</script>
