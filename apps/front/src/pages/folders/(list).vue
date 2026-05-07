<template>
  <div class="min-h-screen">
    <div class="flex flex-col gap-4">
      <FolderListHeader
        v-model:filters-open="filtersOpen"
        v-model:view-mode="viewMode"
        :total-count="totalCount"
      />
      <!-- Error Alert -->
      <Alert
        v-if="currentStatus === 'error'"
        variant="danger"
        :title="$t('common.folder.list.error.title')"
        :description="$t('common.folder.list.error.description')"
        icon="fa fa-exclamation-triangle"
      />

      <!-- Loading State -->
      <FolderListSkeleton v-if="currentIsLoading" :view-mode="viewMode" />

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
            class="rounded-card border-primary-lighter-stroke hover:border-primary/50 hover:bg-primary-lightest/50 group flex min-h-[280px] cursor-pointer flex-col items-center justify-center border-2 border-dashed p-6 transition-all duration-200"
            @click="goToCreate"
          >
            <div
              class="bg-primary/10 dark:bg-primary/20 group-hover:bg-primary/20 mb-4 flex h-16 w-16 items-center justify-center rounded-sm transition-colors"
            >
              <i class="fas fa-plus text-neutral-black-font text-2xl"></i>
            </div>
            <h3
              class="group-hover:text-neutral-black-font mb-2 text-center text-lg font-semibold transition-colors"
            >
              {{ $t('common.folder.create.title') }}
            </h3>
            <p class="text-neutral-black-font text-center text-sm">
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
        <div
          v-else
          class="border-primary-lighter-stroke overflow-hidden rounded-sm border bg-white"
        >
          <!-- Create New Folder Row (only shown if user can create folders) -->
          <div
            v-if="canCreateFolder"
            class="border-primary-lighter-stroke bg-primary-lightest/50 hover:bg-primary-lightest cursor-pointer border-b px-6 py-4 transition-colors"
            @click="goToCreate"
          >
            <div class="flex items-center gap-3">
              <div
                class="bg-primary/10 dark:bg-primary/20 flex h-8 w-8 items-center justify-center rounded-sm"
              >
                <i class="fas fa-plus text-neutral-black-font text-sm"></i>
              </div>
              <div class="flex-1">
                <h3 class="text-neutral-black-font font-medium">
                  {{ $t('common.folder.create.title') }}
                </h3>
                <p class="text-neutral-black-font mt-1 text-xs">
                  {{ $t('common.folder.create.description') }}
                </p>
              </div>
              <i class="fas fa-chevron-right text-neutral-black-font"></i>
            </div>
          </div>

          <!-- Table Header -->
          <div class="border-primary-lighter-stroke bg-primary-lightest border-b px-6 py-4">
            <div
              :class="
                foldersStore.includeAll
                  ? 'text-neutral-black-font grid grid-cols-14 gap-4 text-sm font-medium'
                  : 'text-neutral-black-font grid grid-cols-12 gap-4 text-sm font-medium'
              "
            >
              <div class="col-span-5">{{ $t('common.folder.table.name') }}</div>
              <div v-if="foldersStore.includeAll" class="col-span-2">
                {{ $t('common.folder.table.owner') }}
              </div>
              <div class="col-span-2">
                {{ $t('common.folder.table.items') }}
              </div>
              <div class="col-span-1">
                {{ $t('common.folder.table.created') }}
              </div>
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
              :global-view="foldersStore.includeAll"
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
      <div v-else class="rounded-sm bg-white p-12 text-center shadow-sm">
        <i class="fa fa-folder-open text-neutral-black-font/50 mb-4 text-4xl"></i>
        <h3 class="mb-2 text-lg font-medium">
          {{
            foldersStore.filterName
              ? $t('common.folder.empty.noResults')
              : $t('common.folder.emptyList.title')
          }}
        </h3>
        <p class="text-neutral-black-font mb-6">
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
          variant="primary"
          icon="fa fa-plus"
          :label="$t('common.folder.create.button')"
          @click="goToCreate"
        />
        <Button
          v-else-if="foldersStore.filterName"
          variant="secondary"
          :label="$t('common.folder.clearSearch')"
          @click="foldersStore.filterName = ''"
        />
      </div>
    </div>

    <!-- Filters Drawer -->
    <FolderFiltersDrawer v-model="filtersOpen" :total-count="totalCount" />

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
import FolderFiltersDrawer from '@/components/folders/FolderFiltersDrawer.vue'
import FolderHierarchyRow from '@/components/folders/FolderHierarchyRow.vue'
import FolderItem from '@/components/folders/FolderItem.vue'
import FolderListHeader from '@/components/folders/FolderListHeader.vue'
import FolderListSkeleton from '@/components/folders/FolderListSkeleton.vue'
import FolderRestoreModal from '@/components/folders/FolderRestoreModal.vue'
import Pagination from '@/components/ui/Pagination.vue'
import { useFolderPermissions } from '@/composables/useFolderPermissions'
import { foldersWithItemsQuery } from '@/queries/folders'
import { useFoldersStore } from '@/stores/folders'
import type { Folder } from '@/types/folder'
import { transformToPaginationMeta } from '@/utils/pagination'
import { Alert, Button } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

const VIEW_MODE_STORAGE_KEY = 'folders-view-mode'
const pageSizeOptions = [6, 12, 21, 30]

const router = useRouter()
const foldersStore = useFoldersStore()
const { canCreateFolder } = useFolderPermissions()

const viewMode = ref<'grid' | 'table'>('grid')
const filtersOpen = ref(false)

// Filters live in the store; the getter form below ensures Pinia Colada
// re-runs the query whenever any of these refs change (search, sort, drawer).
// TODO(TAR-1446): when the API ships a dedicated "include archived" flag,
// stop reusing the archived-only param to model hideArchived=false.
const buildQueryFilters = () => ({
  page: foldersStore.page,
  size: foldersStore.size,
  name: foldersStore.debouncedName,
  archived: !foldersStore.hideArchived,
  favorites: foldersStore.favoritesOnly,
  include_all: foldersStore.includeAll,
  sort_by: foldersStore.sortBy,
  sort_order: foldersStore.sortOrder,
})

const { data, status, isLoading, refetch } = useQuery(() => ({
  ...foldersWithItemsQuery({ filters: buildQueryFilters() }),
  enabled: viewMode.value === 'grid',
}))

const {
  data: dataWithItems,
  status: statusWithItems,
  isLoading: isLoadingWithItems,
  refetch: refetchWithItems,
} = useQuery(() => ({
  ...foldersWithItemsQuery({ filters: buildQueryFilters() }),
  enabled: viewMode.value === 'table',
}))

const currentData = computed(() => (viewMode.value === 'grid' ? data.value : dataWithItems.value))
const currentStatus = computed(() =>
  viewMode.value === 'grid' ? status.value : statusWithItems.value,
)
const currentIsLoading = computed(() =>
  viewMode.value === 'grid' ? isLoading.value : isLoadingWithItems.value,
)

const folders = computed<Folder[]>(() => currentData.value?.data || [])
const foldersWithItems = computed<Folder[]>(() => folders.value)
const paginationMeta = computed(() => transformToPaginationMeta(currentData.value?.pagination))
const totalCount = computed(() => currentData.value?.pagination?.total ?? 0)

const showDeleteModal = ref(false)
const folderToDelete = ref<Folder | null>(null)
const showRestoreModal = ref(false)
const folderToRestore = ref<Folder | null>(null)

const refetchCurrent = () => {
  if (viewMode.value === 'grid') {
    refetch()
  } else {
    refetchWithItems()
  }
}

const handleDeleteFolder = () => {
  refetchCurrent()
}

const confirmDelete = (folder: Folder) => {
  folderToDelete.value = folder
  showDeleteModal.value = true
}

const handleRestoreFolder = () => {
  refetchCurrent()
}

const confirmRestore = (folder: Folder) => {
  folderToRestore.value = folder
  showRestoreModal.value = true
}

const updatePerPage = (newSize: number) => {
  foldersStore.size = newSize
  foldersStore.page = 1
}

const goToCreate = () => {
  router.push('/folders/create')
}

onMounted(() => {
  const savedViewMode = localStorage.getItem(VIEW_MODE_STORAGE_KEY)
  if (savedViewMode === 'grid' || savedViewMode === 'table') {
    viewMode.value = savedViewMode
  }
})

watch(viewMode, (newMode) => {
  localStorage.setItem(VIEW_MODE_STORAGE_KEY, newMode)
})
</script>
