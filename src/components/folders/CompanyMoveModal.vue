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

    <div class="flex flex-col gap-6 mt-4">
      <!-- Company Info Display -->
      <div v-if="company" class="p-4 bg-base-200 rounded-lg border border-primary-stroke">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 flex items-center justify-center bg-base-100 rounded-lg border border-primary-stroke">
            <img
              v-if="!company.showFallbackIcon && company.website"
              :src="`https://img.logo.dev/${getDomainFromUrl(company.website)}?token=pk_X-WVIfJTT_CnLpNPWEautQ&size=60`"
              :alt="`${company.name} logo`"
              class="w-10 h-10 object-contain"
              @error="company.showFallbackIcon = true"
            />
            <i v-else class="fa fa-building text-2xl text-secondary"></i>
          </div>
          <div>
            <div class="font-medium">{{ company.name }}</div>
            <div v-if="company.website" class="text-xs text-secondary">{{ company.website }}</div>
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
        <div v-if="isLoadingFolders" class="py-8 text-center text-secondary">
          <i class="fa fa-spinner fa-spin mr-2"></i>
          {{ $t('common.loading', 'Loading...') }}
        </div>

        <!-- Error state -->
        <div
          v-else-if="folderError"
          class="p-4 bg-error-light text-error-light-content border border-error-stroke rounded-lg text-sm"
        >
          <i class="fa fa-exclamation-triangle mr-2"></i>
          {{ $t('folder.moveCompany.loadError', 'Failed to load folders') }}
        </div>

        <!-- Empty state -->
        <div
          v-else-if="!writableFolders || writableFolders.length === 0"
          class="py-8 text-center text-secondary bg-base-200 rounded-lg"
        >
          <i class="fa fa-folder-open text-3xl mb-3 opacity-50"></i>
          <p>{{ $t('folder.moveCompany.noFolders', 'No writable folders available') }}</p>
        </div>

        <!-- Folder list -->
        <div
          v-else
          class="border border-primary-stroke rounded-lg divide-y divide-primary-stroke max-h-96 overflow-y-auto"
        >
          <button
            v-for="folder in writableFolders"
            :key="folder.id"
            type="button"
            class="w-full p-4 text-left hover:bg-base-200 transition-colors flex items-center justify-between"
            :class="{
              'bg-primary-light border-2 border-primary-stroke': selectedFolderId === folder.id,
            }"
            @click="selectFolder(folder.id)"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 flex items-center justify-center rounded-lg"
                :style="{ backgroundColor: getFolderColor(folder.color) }"
              >
                <i :class="folder.icon || 'fa fa-folder'" class="text-white text-lg"></i>
              </div>
              <div>
                <div class="font-medium">{{ folder.name }}</div>
                <div class="text-xs text-secondary">
                  {{ folder.items_count || 0 }} {{ $t('folder.items', 'items') }}
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
        <Button
          variant="secondary"
          :label="$t('common.cancel', 'Cancel')"
          @click="handleClose"
        />
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
import { Button, Label, Modal, Searchbar, Tag } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { useI18n } from 'vue-i18n'
import { foldersQuery } from '@/queries/folders'
import type { FolderItem, Folder } from '@/types/folder'

interface Props {
  company: FolderItem | null
  currentFolderId: string
  displayModal: boolean
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
const debouncedSearchQuery = ref('')
const selectedFolderId = ref<string | null>(null)
const isMoving = ref(false)

let searchTimeout: ReturnType<typeof setTimeout> | null = null

watch(searchQuery, (newQuery) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    debouncedSearchQuery.value = newQuery
  }, 300)
})

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
    console.warn('No folder selected for move')
    return
  }

  const selectedFolder = writableFolders.value.find(
    (f: Folder) => f.id === selectedFolderId.value,
  )

  if (!selectedFolder) {
    console.error('Selected folder not found:', selectedFolderId.value)
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
  debouncedSearchQuery.value = ''
  selectedFolderId.value = null
  isMoving.value = false
}

function getDomainFromUrl(url: string): string {
  try {
    const urlObj = new URL(url.startsWith('http') ? url : `https://${url}`)
    return urlObj.hostname
  } catch {
    return url
  }
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
