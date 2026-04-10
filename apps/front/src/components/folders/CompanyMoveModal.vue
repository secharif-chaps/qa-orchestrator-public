<template>
  <Modal
    v-model:displayModal="isOpen"
    :title="$t('common.folder.moveCompany.title')"
    icon="fa-exchange-alt"
    size="2xl"
    @close="handleClose"
  >
    <template #description>
      {{ $t('common.folder.moveCompany.selectFolder') }}

      <div class="mt-4 flex flex-col gap-6">
        <!-- Company Info Display -->
        <div
          v-if="company"
          class="bg-primary-lightest border-primary-lighter-stroke rounded-sm border p-4"
        >
          <div class="flex items-center gap-3">
            <div
              class="border-primary-lighter-stroke flex h-12 w-12 items-center justify-center rounded-sm border bg-white"
            >
              <img
                v-if="companyLogoUrl"
                :src="companyLogoUrl"
                :alt="`${company.name} logo`"
                class="h-10 w-10 object-contain"
              />
              <i v-else class="fa fa-building text-neutral-black-font text-2xl"></i>
            </div>
            <div>
              <div class="font-medium">{{ company.name }}</div>
              <div v-if="company.website" class="text-neutral-black-font text-xs">
                {{ company.website }}
              </div>
            </div>
          </div>
        </div>

        <!-- Search Input -->
        <div class="flex flex-col gap-3">
          <Label id="folder-search">
            {{ $t('common.folder.moveCompany.searchPlaceholder') }}
          </Label>

          <Searchbar
            id="folder-search-input"
            v-model="searchQuery"
            :placeholder="$t('common.folder.moveCompany.searchPlaceholder')"
          />
        </div>

        <!-- Folder List -->
        <div class="flex flex-col gap-3">
          <!-- Loading state -->
          <div v-if="isLoadingFolders" class="text-neutral-black-font py-8 text-center">
            <i class="fa fa-spinner fa-spin mr-2"></i>
            {{ $t('common.loading') }}
          </div>

          <!-- Error state -->
          <div
            v-else-if="folderError"
            class="bg-error-light text-error-light-content border-error-stroke rounded-sm border p-4 text-sm"
          >
            <i class="fa fa-exclamation-triangle mr-2"></i>
            {{ $t('common.folder.moveCompany.loadError') }}
          </div>

          <!-- Empty state -->
          <div
            v-else-if="!writableFolders || writableFolders.length === 0"
            class="text-neutral-black-font bg-primary-lightest rounded-sm py-8 text-center"
          >
            <i class="fa fa-folder-open mb-3 text-3xl opacity-50"></i>
            <p>{{ $t('common.folder.moveCompany.noFolders') }}</p>
          </div>

          <!-- Folder list -->
          <div
            v-else
            class="border-primary-lighter-stroke divide-primary-stroke max-h-96 divide-y overflow-y-auto rounded-sm border"
          >
            <button
              v-for="folder in writableFolders"
              :key="folder.id"
              type="button"
              class="hover:bg-primary-lightest flex w-full items-center justify-between p-4 text-left transition-colors"
              :class="{
                'bg-primary-light border-primary-lighter-stroke border-2':
                  selectedFolderId === folder.id,
              }"
              @click="selectFolder(folder.id)"
            >
              <div class="flex items-center gap-3">
                <div
                  class="flex h-10 w-10 items-center justify-center rounded-sm"
                  :style="{ backgroundColor: getFolderColor(folder.color) }"
                >
                  <i :class="folder.icon || 'fa fa-folder'" class="text-lg text-white"></i>
                </div>
                <div>
                  <div class="font-medium">{{ folder.name }}</div>
                  <div class="text-neutral-black-font text-xs">
                    {{ folder.items?.length || 0 }} {{ $t('common.folder.items') }}
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
                  :label="$t('common.folder.permissions.owner')"
                  intent="success"
                  size="xs"
                />
                <Tag
                  v-else-if="folder.share_role === 'writer'"
                  :label="$t('common.folder.permissions.writer')"
                  intent="info"
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
        <Button variant="secondary" :label="$t('common.cancel')" @click="handleClose" />
        <Button
          variant="primary"
          :label="$t('common.folder.moveCompany.move')"
          :disabled="!selectedFolderId || isMoving"
          :loading="isMoving"
          @click="handleMove"
        />
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { foldersQuery } from '@/queries/folders'
import type { Folder, FolderItem } from '@/types/folder'
import { Button, Label, Modal, Searchbar, Tag } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { refDebounced } from '@vueuse/core'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

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

useI18n()

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
} = useQuery(() =>
  foldersQuery({
    filters: {
      page: 1,
      size: 100,
      name: debouncedSearchQuery.value,
      archived: false,
    },
  }),
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
