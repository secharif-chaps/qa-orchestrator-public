<template>
  <div class="min-h-screen">
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

            <!-- View Mode Toggle -->
            <ButtonGroup v-model="viewMode" :options="viewModeOptions" />

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
        v-if="currentStatus === 'error'"
        variant="error"
        :title="$t('folder.list.error.title', 'Error')"
        :message="$t('folder.list.error.description', 'Failed to load folders')"
        icon="fa fa-exclamation-triangle"
      />

      <!-- Loading State -->
      <div v-if="currentIsLoading" class="bg-bg1 rounded-lg shadow-sm p-8 text-center">
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-secondary">{{ $t('folder.loading', 'Loading folders...') }}</p>
      </div>

      <!-- Folders Content -->
      <div v-else-if="currentStatus === 'success'">
        <!-- Grid View -->
        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Create New Folder Card -->
          <div
            class="rounded-card p-6 border-2 border-dashed border-border-2 hover:border-primary/50 hover:bg-bg2/50 transition-all duration-200 cursor-pointer group flex flex-col items-center justify-center min-h-[280px]"
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
            @restore-folder="confirmRestore"
            @favorite-toggled="handleFavoriteToggled"
          />
        </div>

        <!-- Hierarchical Table View -->
        <div v-else class="bg-bg1 rounded-lg overflow-hidden border border-border-2">
          <!-- Create New Folder Row -->
          <div class="px-6 py-4 border-b border-border-2 bg-bg2/50 hover:bg-bg2 transition-colors cursor-pointer" @click="$router.push('/folders/create')">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-primary/10 dark:bg-primary/20 flex items-center justify-center">
                <i class="fas fa-plus text-primary text-sm"></i>
              </div>
              <div class="flex-1">
                <h3 class="font-medium text-primary">{{ $t('folder.create.title', 'Create New Folder') }}</h3>
                <p class="text-xs text-secondary mt-1">{{ $t('folder.create.description', 'Organize your companies into folders') }}</p>
              </div>
              <i class="fas fa-chevron-right text-secondary"></i>
            </div>
          </div>

          <!-- Table Header -->
          <div class="px-6 py-4 border-b border-border-2 bg-bg2">
            <div class="grid grid-cols-12 gap-4 text-sm font-medium text-secondary">
              <div class="col-span-6">{{ $t('folder.table.name', 'Name') }}</div>
              <div class="col-span-2">{{ $t('folder.table.items', 'Items') }}</div>
              <div class="col-span-2">{{ $t('folder.table.created', 'Created') }}</div>
              <div class="col-span-2 text-right">{{ $t('folder.table.actions', 'Actions') }}</div>
            </div>
          </div>

          <!-- Table Body - Folders with expandable items -->
          <div class="divide-y divide-border-2">
            <FolderHierarchyRow
              v-for="folder in foldersWithItems"
              :key="folder.id"
              :folder="folder"
              @view-folder="$router.push(`/folders/${$event}`)"
              @delete-folder="confirmDelete"
              @restore-folder="confirmRestore"
              @view-item="(event) => $router.push(`/folders/${event.folderId}/companies/${event.itemId}`)"
            />
          </div>
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
      <div v-else class="bg-bg1 rounded-lg shadow-sm p-12 text-center">
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

    <!-- Restore Confirmation Modal -->
    <FolderRestoreModal
      v-model="showRestoreModal"
      :folder-to-restore="folderToRestore"
      @restore-folder="handleRestoreFolder"
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
import Button from '@/components/ui/Button.vue'
import ButtonGroup from '@/components/ui/ButtonGroup.vue'
import FolderItem from '@/components/folders/FolderItem.vue'
import FolderDeleteModal from '@/components/folders/FolderDeleteModal.vue'
import FolderRestoreModal from '@/components/folders/FolderRestoreModal.vue'
import FolderHierarchyRow from '@/components/folders/FolderHierarchyRow.vue'
import Pagination from '@/components/ui/Pagination.vue'
import type { Folder } from '@/types/folder'
import { ref, computed, onMounted, watch } from 'vue'
import { foldersQuery, foldersWithItemsQuery } from '@/queries/folders'
import { useQuery } from '@pinia/colada'
import { useFoldersStore } from '@/stores/folders'

// Constants
const VIEW_MODE_STORAGE_KEY = 'folders-view-mode'

// Grid-friendly page size options (multiples of 3)
const pageSizeOptions = [6, 12, 21, 30]

const foldersStore = useFoldersStore()

// Filter and view state
const folderFilter = ref<'all' | 'favorites' | 'archived'>('all')
const viewMode = ref<'grid' | 'table'>('grid')

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

// View mode options for ButtonGroup
const viewModeOptions = computed(() => [
  {
    value: 'table',
    label: 'Table',
    icon: 'fa fa-list',
    title: 'Table View',
  },
  {
    value: 'grid',
    label: 'Grid',
    icon: 'fa fa-th-large',
    title: 'Grid View',
  },
])

// Query for grid view (folders only)
const { data, status, isLoading, refetch } = useQuery(foldersQuery, () => ({
  filters: {
    page: foldersStore.page,
    size: foldersStore.size,
    name: foldersStore.debouncedName,
    archived: folderFilter.value === 'archived',
  },
}), {
  enabled: () => viewMode.value === 'grid'
})

// Query for table view (folders with items)
const {
  data: dataWithItems,
  status: statusWithItems,
  isLoading: isLoadingWithItems,
  refetch: refetchWithItems
} = useQuery(foldersWithItemsQuery, () => ({
  filters: {
    page: foldersStore.page,
    size: foldersStore.size,
    name: foldersStore.debouncedName,
    archived: folderFilter.value === 'archived',
  },
}), {
  enabled: () => viewMode.value === 'table'
})

// Combined computed properties for different view modes
const currentData = computed(() => viewMode.value === 'grid' ? data.value : dataWithItems.value)
const currentStatus = computed(() => viewMode.value === 'grid' ? status.value : statusWithItems.value)
const currentIsLoading = computed(() => viewMode.value === 'grid' ? isLoading.value : isLoadingWithItems.value)

const folders = computed(() => {
  const allFolders = currentData.value || []

  // Apply client-side filtering for favorites only (archived is handled server-side)
  if (folderFilter.value === 'favorites') {
    return allFolders.filter((folder) => folder.is_favorite)
  }

  return allFolders
})

const foldersWithItems = computed(() => folders.value)

const paginationMeta = computed(() => currentData.value?.meta)

const showDeleteModal = ref(false)
const folderToDelete = ref<Folder | null>(null)

const showRestoreModal = ref(false)
const folderToRestore = ref<Folder | null>(null)

const handleDeleteFolder = async () => {
  // Refresh the folders list after successful deletion
  if (viewMode.value === 'grid') {
    await refetch()
  } else {
    await refetchWithItems()
  }
}

const confirmDelete = (folder: Folder) => {
  folderToDelete.value = folder
  showDeleteModal.value = true
}

const handleRestoreFolder = async () => {
  // Refresh the folders list after successful deletion
  if (viewMode.value === 'grid') {
    await refetch()
  } else {
    await refetchWithItems()
  }
}

const confirmRestore = (folder: Folder) => {
  folderToRestore.value = folder
  showRestoreModal.value = true
}

const handleFavoriteToggled = async (folder: Folder) => {
  // Refresh the folders list after favorite toggle to update the filtered views
  if (viewMode.value === 'grid') {
    await refetch()
  } else {
    await refetchWithItems()
  }
}

const updatePerPage = (newSize: number) => {
  foldersStore.size = newSize
  // Reset to first page when changing page size
  foldersStore.page = 1
}

// Load saved view mode from localStorage
onMounted(() => {
  const savedViewMode = localStorage.getItem(VIEW_MODE_STORAGE_KEY)
  if (savedViewMode === 'grid' || savedViewMode === 'table') {
    viewMode.value = savedViewMode
  }
})

// Watch for view mode changes and save to localStorage
watch(viewMode, (newMode) => {
  localStorage.setItem(VIEW_MODE_STORAGE_KEY, newMode)
})
</script>
