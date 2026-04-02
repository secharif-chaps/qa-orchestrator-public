<template>
  <div class="min-h-screen">
    <div class="flex flex-col gap-4">
      <!-- Header -->
      <div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 class="text-2xl font-bold">{{ $t('common.folder.title') }}</h1>
            <p class="text-secondary mt-1">
              {{
                isGlobalView
                  ? $t('common.folder.descriptionGlobal')
                  : $t('common.folder.description')
              }}
            </p>
          </div>

          <!-- Filters and Search -->
          <div class="flex items-center gap-4">
            <!-- Global View Toggle (Managers Only) -->
            <Toggle
              v-if="canManageTeam"
              v-model="globalViewToggle"
              :options="viewScopeOptions"
              variant="pill"
            />

            <!-- Filter Buttons -->
            <Toggle v-model="folderFilter" :options="filterOptions" variant="pill" />

            <!-- View Mode Toggle -->
            <Toggle v-model="viewMode" :options="viewModeOptions" variant="pill" />
          </div>
        </div>
      </div>

      <!-- Error Alert -->
      <Alert
        v-if="currentStatus === 'error'"
        variant="danger"
        :title="$t('common.folder.list.error.title')"
        :description="$t('common.folder.list.error.description')"
        icon="fa fa-exclamation-triangle"
      />

      <!-- Loading State -->
      <div v-if="currentIsLoading" class="bg-base-100 rounded-lg p-8 text-center shadow-sm">
        <div
          class="border-primary mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-b-2"
        ></div>
        <p class="text-secondary">
          {{ $t('common.folder.loading') }}
        </p>
      </div>

      <!-- Folders Content -->
      <div v-else-if="currentStatus === 'success'">
        <!-- Grid View -->
        <div
          v-if="viewMode === 'grid'"
          class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
        >
          <!-- Create New Folder Card (only shown if user can create folders) -->
          <div
            v-if="canCreateFolder"
            class="rounded-card border-primary-stroke hover:border-primary/50 hover:bg-base-200/50 group flex min-h-[280px] cursor-pointer flex-col items-center justify-center border-2 border-dashed p-6 transition-all duration-200"
            @click="$router.push('/folders/create')"
          >
            <div
              class="bg-primary/10 dark:bg-primary/20 group-hover:bg-primary/20 mb-4 flex h-16 w-16 items-center justify-center rounded-lg transition-colors"
            >
              <i class="fas fa-plus text-secondary text-2xl"></i>
            </div>
            <h3
              class="group-hover:text-secondary mb-2 text-center text-lg font-semibold transition-colors"
            >
              {{ $t('common.folder.create.title') }}
            </h3>
            <p class="text-secondary text-center text-sm">
              {{ $t('common.folder.create.description') }}
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
          />
        </div>

        <!-- Hierarchical Table View -->
        <div v-else class="bg-base-100 border-primary-stroke overflow-hidden rounded-lg border">
          <!-- Create New Folder Row (only shown if user can create folders) -->
          <div
            v-if="canCreateFolder"
            class="border-primary-stroke bg-base-200/50 hover:bg-base-200 cursor-pointer border-b px-6 py-4 transition-colors"
            @click="$router.push('/folders/create')"
          >
            <div class="flex items-center gap-3">
              <div
                class="bg-primary/10 dark:bg-primary/20 flex h-8 w-8 items-center justify-center rounded-lg"
              >
                <i class="fas fa-plus text-secondary text-sm"></i>
              </div>
              <div class="flex-1">
                <h3 class="text-secondary font-medium">
                  {{ $t('common.folder.create.title') }}
                </h3>
                <p class="text-secondary mt-1 text-xs">
                  {{ $t('common.folder.create.description') }}
                </p>
              </div>
              <i class="fas fa-chevron-right text-secondary"></i>
            </div>
          </div>

          <!-- Table Header -->
          <div class="border-primary-stroke bg-base-200 border-b px-6 py-4">
            <div
              :class="
                isGlobalView
                  ? 'text-secondary grid grid-cols-14 gap-4 text-sm font-medium'
                  : 'text-secondary grid grid-cols-12 gap-4 text-sm font-medium'
              "
            >
              <div class="col-span-5">{{ $t('common.folder.table.name') }}</div>
              <div v-if="isGlobalView" class="col-span-2">
                {{ $t('common.folder.table.owner') }}
              </div>
              <div class="col-span-2">{{ $t('common.folder.table.items') }}</div>
              <div class="col-span-1">{{ $t('common.folder.table.created') }}</div>
              <div class="col-span-2 text-right">
                {{ $t('common.folder.table.actions') }}
              </div>
            </div>
          </div>

          <!-- Table Body - Folders with expandable items -->
          <div class="divide-primary-stroke divide-y">
            <FolderHierarchyRow
              v-for="folder in foldersWithItems"
              :key="folder.id"
              :folder="folder"
              :global-view="isGlobalView"
              @view-folder="$router.push(`/folders/${$event}`)"
              @delete-folder="confirmDelete"
              @restore-folder="confirmRestore"
              @view-item="
                (event) => $router.push(`/folders/${event.folderId}/companies/${event.itemId}`)
              "
            />
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="paginationMeta" class="mt-6">
          <Pagination
            v-model:current-page="foldersStore.page"
            :meta="paginationMeta"
            :page-size-options="pageSizeOptions"
            :item-name="$t('common.folder.itemName')"
            @update-per-page="updatePerPage"
          />
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="bg-base-100 rounded-lg p-12 text-center shadow-sm">
        <i class="fa fa-folder-open text-secondary/50 mb-4 text-4xl"></i>
        <h3 class="mb-2 text-lg font-medium">
          {{
            foldersStore.filterName
              ? $t('common.folder.empty.noResults')
              : $t('common.folder.emptyList.title')
          }}
        </h3>
        <p class="text-secondary mb-6">
          {{
            foldersStore.filterName
              ? $t('common.folder.empty.tryDifferentSearch')
              : canCreateFolder
                ? $t('common.folder.emptyList.description')
                : $t('common.folder.emptyList.descriptionReadOnly')
          }}
        </p>
        <Button
          v-if="!foldersStore.filterName && canCreateFolder"
          @click="$router.push('/folders/create')"
          :label="$t('common.folder.create.button')"
          variant="primary"
          icon="fa fa-plus"
        />
        <Button
          v-else-if="foldersStore.filterName"
          @click="foldersStore.filterName = ''"
          :label="$t('common.folder.clearSearch')"
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
    - organization.read
</route>

<script setup lang="ts">
import FolderDeleteModal from '@/components/folders/FolderDeleteModal.vue'
import FolderHierarchyRow from '@/components/folders/FolderHierarchyRow.vue'
import FolderItem from '@/components/folders/FolderItem.vue'
import FolderRestoreModal from '@/components/folders/FolderRestoreModal.vue'
import { Alert, Button, Toggle } from '@owlint/feathers-vue'
import Pagination from '@/components/ui/Pagination.vue'
import { foldersWithItemsQuery } from '@/queries/folders'
import { useFoldersStore } from '@/stores/folders'
import { useFolderPermissions } from '@/composables/useFolderPermissions'
import { useTeamPermissions } from '@/composables/useTeamPermissions'
import type { Folder } from '@/types/folder'
import { useQuery } from '@pinia/colada'
import { transformToPaginationMeta } from '@/utils/pagination'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

// Constants
const VIEW_MODE_STORAGE_KEY = 'folders-view-mode'

// Grid-friendly page size options (multiples of 3)
const pageSizeOptions = [6, 12, 21, 30]

const { t: $t } = useI18n()
const foldersStore = useFoldersStore()

// Get folder creation permission (no folder context needed for this)
const { canCreateFolder } = useFolderPermissions()

// Get team management permission for global view toggle
const { canManageTeam } = useTeamPermissions()

// Filter and view state
const folderFilter = ref<'all' | 'favorites' | 'archived'>('all')
const viewMode = ref<'grid' | 'table'>('grid')
// Using 'my' and 'all' strings instead of boolean values to match AcceptableValue type
const globalViewToggle = ref<'my' | 'all'>('my')

// Computed to convert toggle value to boolean for API and template usage
const isGlobalView = computed(() => globalViewToggle.value === 'all')

// Filter options for Toggle
const filterOptions = computed(() => [
  {
    value: 'all',
    icon: 'fas fa-folder',
    label: $t('common.folder.filter.allLabel'),
  },
  {
    value: 'favorites',
    icon: 'fas fa-star',
    label: $t('common.folder.filter.favoritesLabel'),
  },
  {
    value: 'archived',
    icon: 'fas fa-archive',
    label: $t('common.folder.filter.archivedLabel'),
  },
])

// View mode options for Toggle
const viewModeOptions = computed(() => [
  {
    value: 'table',
    label: $t('common.folder.viewMode.table'),
    icon: 'fa fa-list',
  },
  {
    value: 'grid',
    label: $t('common.folder.viewMode.grid'),
    icon: 'fa fa-th-large',
  },
])

// View scope options for global toggle (managers only)
const viewScopeOptions = computed(() => [
  {
    value: 'my',
    label: $t('common.folder.viewScope.myFolders'),
    icon: 'fa fa-user',
  },
  {
    value: 'all',
    label: $t('common.folder.viewScope.allFolders'),
    icon: 'fa fa-users',
  },
])

// Query for grid view (uses same query as table for unified caching)
const { data, status, isLoading, refetch } = useQuery({
  ...foldersWithItemsQuery({
    filters: {
      page: foldersStore.page,
      size: foldersStore.size,
      name: foldersStore.debouncedName,
      archived: folderFilter.value === 'archived',
      favorites: folderFilter.value === 'favorites',
      include_all: isGlobalView.value,
    },
  }),
  enabled: () => viewMode.value === 'grid',
})

// Query for table view (folders with items)
const {
  data: dataWithItems,
  status: statusWithItems,
  isLoading: isLoadingWithItems,
  refetch: refetchWithItems,
} = useQuery({
  ...foldersWithItemsQuery({
    filters: {
      page: foldersStore.page,
      size: foldersStore.size,
      name: foldersStore.debouncedName,
      archived: folderFilter.value === 'archived',
      favorites: folderFilter.value === 'favorites',
      include_all: isGlobalView.value,
    },
  }),
  enabled: () => viewMode.value === 'table',
})

// Combined computed properties for different view modes
const currentData = computed(() => (viewMode.value === 'grid' ? data.value : dataWithItems.value))
const currentStatus = computed(() =>
  viewMode.value === 'grid' ? status.value : statusWithItems.value,
)
const currentIsLoading = computed(() =>
  viewMode.value === 'grid' ? isLoading.value : isLoadingWithItems.value,
)

// Folders are now filtered server-side (both archived and favorites)
// Access the .data property from PaginatedResponse
const folders = computed<Folder[]>(() => currentData.value?.data || [])

const foldersWithItems = computed<Folder[]>(() => folders.value)

const paginationMeta = computed(() => transformToPaginationMeta(currentData.value?.pagination))

const showDeleteModal = ref(false)
const folderToDelete = ref<Folder | null>(null)

const showRestoreModal = ref(false)
const folderToRestore = ref<Folder | null>(null)

function handleDeleteFolder() {
  // Refresh the folders list after successful deletion
  if (viewMode.value === 'grid') {
    refetch()
  } else {
    refetchWithItems()
  }
}

function confirmDelete(folder: Folder) {
  folderToDelete.value = folder
  showDeleteModal.value = true
}

function handleRestoreFolder() {
  // Refresh the folders list after successful deletion
  if (viewMode.value === 'grid') {
    refetch()
  } else {
    refetchWithItems()
  }
}

function confirmRestore(folder: Folder) {
  folderToRestore.value = folder
  showRestoreModal.value = true
}

function updatePerPage(newSize: number) {
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
