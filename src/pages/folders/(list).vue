<template>
  <div class="min-h-screen bg-bg3">
    <div class="flex flex-col gap-4">
      <!-- Header -->
      <div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 class="text-2xl font-bold">{{ $t('folder.title', 'Folders') }}</h1>
            <p class="text-secondary mt-1">
              {{ $t('folder.description', 'Organize your companies into folders') }}
            </p>
          </div>

          <!-- Filters and Search -->
          <div class="flex items-center gap-4">
            <!-- Filter Buttons -->
            <ButtonGroup v-model="folderFilter" :options="filterOptions" />

            <!-- Search Input -->
            <!-- <Input
              v-model="foldersStore.filterName"
              :placeholder="$t('folder.search', 'Search folders...')"
              icon="fa fa-search"
              clearable
              class="w-full sm:w-96"
            /> -->
          </div>
        </div>
      </div>

      <!-- Error Alert -->
      <Alert
        v-if="status === 'error'"
        variant="error"
        :title="$t('folder.list.error.title', 'Error')"
        :message="$t('folder.list.error.description', 'Failed to load folders')"
        icon="fa fa-exclamation-triangle"
      />

      <!-- Loading State -->
      <div v-if="isLoading" class="bg-bg1 rounded-lg shadow-sm p-8 text-center">
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-secondary">{{ $t('folder.loading', 'Loading folders...') }}</p>
      </div>

      <!-- Folders Grid -->
      <div v-else-if="status === 'success'">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Create New Folder Card -->
          <div
            class="rounded-lg p-6 border-2 border-dashed border-border-2 hover:border-primary/50 hover:bg-bg2/50 transition-all duration-200 cursor-pointer group flex flex-col items-center justify-center min-h-[280px]"
            @click="$router.push('/folders/create')"
          >
            <div
              class="w-16 h-16 rounded-lg bg-primary/10 dark:bg-primary/20 flex items-center justify-center mb-4 group-hover:bg-primary/20 transition-colors"
            >
              <i class="fas fa-plus text-primary text-2xl"></i>
            </div>
            <h3
              class="text-lg font-semibold text-center mb-2 group-hover:text-primary transition-colors"
            >
              {{ $t('folder.create.title', 'Create New Folder') }}
            </h3>
            <p class="text-sm text-secondary text-center">
              {{ $t('folder.create.description', 'Organize your companies into folders') }}
            </p>
          </div>

          <!-- Existing Folders -->
          <FolderItem
            v-for="folder in folders"
            :key="folder.id"
            :folder="folder"
            @view-folder="$router.push(`/folders/${$event}`)"
            @delete-folder="confirmDelete"
          />
        </div>

        <!-- Pagination -->
        <div v-if="paginationMeta" class="mt-6">
          <Pagination
            v-model:current-page="foldersStore.page"
            :meta="paginationMeta"
            :page-size-options="pageSizeOptions"
            item-name="folders"
            @update-per-page="updatePerPage"
          />
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="status === 'success'" class="bg-bg1 rounded-lg shadow-sm p-12 text-center">
        <i class="fa fa-folder-open text-4xl text-secondary/50 mb-4"></i>
        <h3 class="text-lg font-medium mb-2">
          {{
            foldersStore.filterName
              ? $t('folder.empty.noResults', 'No folders found')
              : $t('folder.empty.title', 'No folders yet')
          }}
        </h3>
        <p class="text-secondary mb-6">
          {{
            foldersStore.filterName
              ? $t('folder.empty.tryDifferentSearch', 'Try a different search term')
              : $t(
                  'folder.empty.description',
                  'Create your first folder to organize your companies',
                )
          }}
        </p>
        <Button
          v-if="!foldersStore.filterName"
          @click="$router.push('/folders/create')"
          :label="$t('folder.create.button', 'Create Folder')"
          variant="primary"
          icon="fa fa-plus"
        />
        <Button
          v-else
          @click="foldersStore.filterName = ''"
          :label="$t('folder.clearSearch', 'Clear Search')"
          variant="secondary"
        />
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <FolderDeleteModal
      v-model="showDeleteModal"
      :folder-to-delete="folderToDelete"
      @delete-folder="handleDeleteFolder"
    />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - workspace.read
</route>

<script setup lang="ts">
import Alert from '@/components/ui/Alert.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import ButtonGroup from '@/components/ui/ButtonGroup.vue'
import FolderItem from '@/components/folders/FolderItem.vue'
import FolderDeleteModal from '@/components/folders/FolderDeleteModal.vue'
import Pagination from '@/components/ui/Pagination.vue'
import type { Folder } from '@/types/folder'
import { ref, computed } from 'vue'
import { foldersQuery } from '@/queries/folders'
import { useQuery } from '@pinia/colada'
import { useFoldersStore } from '@/stores/folders'

// Grid-friendly page size options (multiples of 3)
const pageSizeOptions = [6, 12, 21, 30]

const foldersStore = useFoldersStore()

// Filter state
const folderFilter = ref<'all' | 'favorites' | 'archived'>('all')

// Filter options for ButtonGroup
const filterOptions = computed(() => [
  {
    value: 'all',
    icon: 'fas fa-folder',
    title: 'All folders',
    label: 'All',
  },
  {
    value: 'favorites',
    icon: 'fas fa-star',
    title: 'Favorite folders',
    label: 'Favorites',
  },
  {
    value: 'archived',
    icon: 'fas fa-archive',
    title: 'Archived folders',
    label: 'Archived',
  },
])

const { data, status, isLoading, refetch } = useQuery(foldersQuery, () => ({
  filters: {
    page: foldersStore.page,
    size: foldersStore.size,
    name: foldersStore.debouncedName,
    archived: folderFilter.value === 'archived',
  },
}))

const folders = computed(() => {
  const allFolders = data.value || []

  // Apply client-side filtering for favorites only (archived is handled server-side)
  if (folderFilter.value === 'favorites') {
    return allFolders.filter((folder) => folder.is_favorite)
  }

  return allFolders
})

const paginationMeta = computed(() => data.value?.meta)

const showDeleteModal = ref(false)
const folderToDelete = ref<Folder | null>(null)

const handleDeleteFolder = async () => {
  // Refresh the folders list after successful deletion
  await refetch()
}

const confirmDelete = (folder: Folder) => {
  folderToDelete.value = folder
  showDeleteModal.value = true
}

const updatePerPage = (newSize: number) => {
  foldersStore.size = newSize
  // Reset to first page when changing page size
  foldersStore.page = 1
}
</script>
