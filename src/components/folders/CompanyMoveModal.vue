<template>
  <Modal
    v-model:displayModal="isOpen"
    :title="$t('folder.moveCompany.title', 'Move Company to Folder')"
    icon="fa-exchange-alt"
    size="2xl"
    @close="handleClose"
  >
    <template #description>
      {{ $t('folder.moveCompany.selectFolder', 'Select a destination folder') }}

      <div class="mt-4 flex flex-col gap-6">
        <!-- Company Info Display -->
        <div v-if="company" class="bg-base-200 border-primary-stroke rounded-lg border p-4">
          <div class="flex items-center gap-3">
            <div
              class="bg-base-100 border-primary-stroke flex h-12 w-12 items-center justify-center rounded-lg border"
            >
              <img
                v-if="companyLogoUrl"
                :src="companyLogoUrl"
                :alt="`${company.name} logo`"
                class="h-10 w-10 object-contain"
              />
              <i v-else class="fa fa-building text-secondary text-2xl"></i>
            </div>
            <div>
              <div class="font-medium">{{ company.name }}</div>
              <div v-if="company.website" class="text-secondary text-xs">{{ company.website }}</div>
            </div>
          </div>
        </div>

        <!-- Search Input -->
        <div class="flex flex-col gap-3">
          <Label id="folder-search">
            {{ $t('folder.moveCompany.searchPlaceholder', 'Search folders...') }}
          </Label>

          <Searchbar
            id="folder-search-input"
            v-model="searchQuery"
            :placeholder="$t('folder.moveCompany.searchPlaceholder', 'Search folders...')"
          />
        </div>

        <!-- Folder List -->
        <div class="flex flex-col gap-3">
          <!-- Loading state -->
          <div v-if="isLoadingFolders" class="text-secondary py-8 text-center">
            <i class="fa fa-spinner fa-spin mr-2"></i>
            {{ $t('common.loading', 'Loading...') }}
          </div>

          <!-- Error state -->
          <div
            v-else-if="folderError"
            class="bg-error-light text-error-light-content border-error-stroke rounded-lg border p-4 text-sm"
          >
            <i class="fa fa-exclamation-triangle mr-2"></i>
            {{ $t('folder.moveCompany.loadError', 'Failed to load folders') }}
          </div>

          <!-- Empty state -->
          <div
            v-else-if="!writableFolders || writableFolders.length === 0"
            class="text-secondary bg-base-200 rounded-lg py-8 text-center"
          >
            <i class="fa fa-folder-open mb-3 text-3xl opacity-50"></i>
            <p>{{ $t('folder.moveCompany.noFolders', 'No writable folders available') }}</p>
          </div>

          <!-- Folder list -->
          <div
            v-else
            class="border-primary-stroke divide-primary-stroke max-h-96 divide-y overflow-y-auto rounded-lg border"
          >
            <button
              v-for="folder in writableFolders"
              :key="folder.id"
              type="button"
              class="hover:bg-base-200 flex w-full items-center justify-between p-4 text-left transition-colors"
              :class="{
                'bg-primary-light border-primary-stroke border-2': selectedFolderId === folder.id,
              }"
              @click="selectFolder(folder.id)"
            >
              <div class="flex items-center gap-3">
                <div
                  class="flex h-10 w-10 items-center justify-center rounded-lg"
                  :style="{ backgroundColor: getFolderColor(folder.color) }"
                >
                  <i :class="folder.icon || 'fa fa-folder'" class="text-lg text-white"></i>
                </div>
                <div>
                  <div class="font-medium">{{ folder.name }}</div>
                  <div class="text-secondary text-xs">
                    {{ folder.items?.length || 0 }} {{ $t('folder.items', 'items') }}
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <!-- Selected tick icon -->
                <i
                  v-if="selectedFolderId === folder.id"
                  class="fa fa-check text-primary text-lg"
                ></i>

                <Tag
                  v-if="folder.is_owner"
                  :label="$t('folder.permissions.owner', 'Owner')"
                  intent="primary"
                  size="xs"
                />
                <Tag
                  v-else-if="folder.share_role === 'writer'"
                  :label="$t('folder.permissions.writer', 'Writer')"
                  intent="secondary"
                  size="xs"
                />
              </div>
            </button>
          </div>
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex gap-3">
        <Button variant="secondary" :label="$t('common.cancel', 'Cancel')" @click="handleClose" />
        <Button
          variant="primary"
          :label="$t('folder.moveCompany.move', 'Move')"
          :disabled="!selectedFolderId || isMoving"
          :loading="isMoving"
          @click="handleMove"
        />
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { refDebounced } from '@vueuse/core'
import { Button, Label, Modal, Searchbar, Tag } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { useI18n } from 'vue-i18n'
import { foldersQuery } from '@/queries/folders'
import type { FolderItem, Folder } from '@/types/folder'

interface Props {
  company: FolderItem | null
  currentFolderId: string
  displayModal: boolean
  companyLogoUrl?: string
}

interface Emits {
  (e: 'update:displayModal', value: boolean): void
  (e: 'move', payload: { destinationFolderId: string; destinationFolderName: string }): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()

const isOpen = computed({
  get: () => props.displayModal,
  set: (value) => emit('update:displayModal', value),
})

const searchQuery = ref('')
const debouncedSearchQuery = refDebounced(searchQuery, 300)
const selectedFolderId = ref<string | null>(null)
const isMoving = ref(false)

// Reset state when modal closes (from parent closing it after successful move)
watch(
  () => props.displayModal,
  (newValue) => {
    if (!newValue) {
      // Modal was closed, reset all state
      searchQuery.value = ''
      selectedFolderId.value = null
      isMoving.value = false
    }
  },
)

const {
  data: foldersData,
  isLoading: isLoadingFolders,
  error: folderError,
} = useQuery(
  foldersQuery,
  () => ({
    filters: {
      page: 1,
      size: 100,
      name: debouncedSearchQuery.value,
      archived: false,
    },
  }),
  {
    enabled: computed(() => props.displayModal),
  },
)

const writableFolders = computed(() => {
  if (!foldersData.value) {
    return []
  }

  // Handle both array response and paginated response
  const folders = Array.isArray(foldersData.value)
    ? foldersData.value
    : foldersData.value.data || []

  const filtered = folders.filter((folder: Folder) => {
    // Exclude current folder
    if (folder.id === props.currentFolderId) return false

    // Include if user is owner or has writer role
    return folder.is_owner === true || folder.share_role === 'writer'
  })

  return filtered
})

function selectFolder(folderId: string) {
  selectedFolderId.value = folderId
}

function handleMove() {
  if (!selectedFolderId.value) {
    return
  }

  const selectedFolder = writableFolders.value.find((f: Folder) => f.id === selectedFolderId.value)

  if (!selectedFolder) {
    return
  }

  isMoving.value = true

  emit('move', {
    destinationFolderId: selectedFolder.id,
    destinationFolderName: selectedFolder.name,
  })
}

function handleClose() {
  isOpen.value = false
  searchQuery.value = ''
  selectedFolderId.value = null
  isMoving.value = false
}

function getFolderColor(color?: string): string {
  const colorMap: Record<string, string> = {
    blue: '#3b82f6',
    green: '#10b981',
    red: '#ef4444',
    yellow: '#f59e0b',
    purple: '#8b5cf6',
    pink: '#ec4899',
    gray: '#6b7280',
  }
  return colorMap[color || 'blue'] || colorMap.blue
}

defineExpose({
  handleClose,
})
</script>
