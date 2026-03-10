<template>
  <div class="flex h-[calc(100vh-140px)] min-w-[320px] flex-col">
    <!-- Header -->
    <div class="border-sage-800 flex items-center justify-between border-b-2 px-4 py-2 shadow">
      <h2 class="text-headline-2xl">{{ $t('sidebar.foldersSidebar.title', 'Folders') }}</h2>
      <Button
        variant="tertiary"
        size="sm"
        :label="$t('sidebar.foldersSidebar.viewAll', 'View all folders')"
        @click="$router.push('/folders')"
      />
    </div>

    <!-- Search -->
    <div class="px-4 pt-4 pb-2">
      <Searchbar
        id="folders-sidebar-search"
        v-model="searchTerm"
        :placeholder="$t('sidebar.foldersSidebar.search', 'Search a folder...')"
        size="sm"
      />
    </div>

    <!-- Folders List -->
    <div class="relative flex-1 overflow-y-auto px-2 pt-4 pb-12">
      <div
        class="from-sage-950 fixed top-[180px] z-20 h-4 w-full bg-transparent bg-gradient-to-b to-transparent"
      ></div>
      <div
        class="from-sage-950 fixed bottom-16 z-20 h-4 w-full bg-transparent bg-gradient-to-t to-transparent"
      ></div>
      <div v-if="isLoading" class="flex items-center justify-center py-8">
        <i class="fa fa-spinner fa-spin text-sage-400"></i>
      </div>

      <div
        v-else-if="filteredFolders.length === 0"
        class="text-sage-400 px-4 py-8 text-center text-sm"
      >
        {{
          searchTerm
            ? $t('sidebar.foldersSidebar.noFoldersFound', 'No folders found')
            : $t('sidebar.foldersSidebar.noFolders', 'No folders')
        }}
      </div>

      <div v-else class="flex flex-col gap-3">
        <!-- Favorites Section -->
        <div v-if="favoriteFolders.length > 0">
          <div
            class="text-sage-300 group hover:bg-sage-800/50 flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 transition-colors"
            @click="$router.push('/folders')"
          >
            <i class="fa fa-heart text-sm"></i>
            <span class="flex-1 text-sm font-medium">{{
              $t('sidebar.foldersSidebar.favorites', 'Favorites')
            }}</span>
          </div>

          <div class="space-y-4">
            <FolderRow
              v-for="folder in favoriteFolders"
              :key="folder.id"
              :folder="folder"
              :is-expanded="expandedFolders[folder.id] || false"
              :search-term="searchTerm"
              @toggle="toggleFolder(folder.id)"
              @navigate-folder="navigateToFolder"
              @navigate-company="navigateToCompany"
              @add-company="addCompanyToFolder"
            />
          </div>
        </div>

        <!-- Regular Folders -->
        <div v-if="regularFolders.length > 0" class="space-y-3">
          <div
            class="text-sage-300 group hover:bg-sage-800/50 flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 transition-colors"
            @click="$router.push('/folders')"
          >
            <i class="fa fa-folders text-sm"></i>
            <span class="flex-1 text-sm font-medium">{{
              $t('sidebar.foldersSidebar.allFolders', 'Folders')
            }}</span>
          </div>
          <FolderRow
            v-for="folder in regularFolders"
            :key="folder.id"
            :folder="folder"
            :is-expanded="expandedFolders[folder.id] || false"
            :search-term="searchTerm"
            @toggle="toggleFolder(folder.id)"
            @navigate-folder="navigateToFolder"
            @navigate-company="navigateToCompany"
            @add-company="addCompanyToFolder"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { foldersWithItemsQuery } from '@/queries/folders'
import { Button, Searchbar } from '@owlint/feathers-vue'
import FolderRow from './FolderRow.vue'
import type { Folder } from '@/types/folder'

const router = useRouter()

const STORAGE_KEY_EXPANDED = 'folders-sidebar-expanded'
const STORAGE_KEY_SECTIONS = 'folders-sidebar-sections'

// State
const searchTerm = ref('')
const expandedFolders = ref<Record<string, boolean>>({})
const expandedSections = ref({
  favorites: true, // Favorites expanded by default
})

// Fetch folders with items (5 most recent)
const { data: foldersData, isLoading } = useQuery(foldersWithItemsQuery, () => ({
  filters: {
    page: 1,
    size: 5,
    name: '',
  },
}))

// Computed folders lists - access .data from PaginatedResponse
const allFolders = computed<Folder[]>(() => foldersData.value?.data || [])

const favoriteFolders = computed<Folder[]>(() => {
  return allFolders.value.filter((f: Folder) => f.is_favorite)
})

const regularFolders = computed<Folder[]>(() => {
  return allFolders.value.filter((f: Folder) => !f.is_favorite)
})

// Filter folders based on search
const filteredFolders = computed<Folder[]>(() => {
  if (!searchTerm.value.trim()) {
    return allFolders.value
  }

  const query = searchTerm.value.toLowerCase()

  return allFolders.value.filter((folder: Folder) => {
    // Check folder name
    if (folder.name.toLowerCase().includes(query)) {
      return true
    }

    // Check company names in folder items
    if (folder.items) {
      return folder.items.some((item) => item.name.toLowerCase().includes(query))
    }

    return false
  })
})

// Toggle folder expansion
const toggleFolder = (folderId: string) => {
  expandedFolders.value[folderId] = !expandedFolders.value[folderId]
  saveExpandedState()
}

// Navigation handlers
const navigateToFolder = (folderId: string) => {
  router.push(`/folders/${folderId}`)
}

const navigateToCompany = (folderId: string, companyId: string) => {
  router.push(`/folders/${folderId}/companies/${companyId}`)
}

const addCompanyToFolder = (folderId: string) => {
  router.push(`/folders/${folderId}/create/company`)
}

// LocalStorage persistence
const saveExpandedState = () => {
  localStorage.setItem(STORAGE_KEY_EXPANDED, JSON.stringify(expandedFolders.value))
}

const loadExpandedState = () => {
  const stored = localStorage.getItem(STORAGE_KEY_EXPANDED)
  if (stored) {
    try {
      expandedFolders.value = JSON.parse(stored)
    } catch (e) {
      console.error('Failed to parse expanded folders state', e)
    }
  }
}

const loadSectionsState = () => {
  const stored = localStorage.getItem(STORAGE_KEY_SECTIONS)
  if (stored) {
    try {
      expandedSections.value = JSON.parse(stored)
    } catch (e) {
      console.error('Failed to parse sections state', e)
    }
  }
}

// Load saved state on mount
onMounted(() => {
  loadExpandedState()
  loadSectionsState()
})
</script>
