<template>
  <div class="h-[calc(100vh-140px)] flex flex-col min-w-[320px]">
    <!-- Header -->
    <div class="flex items-center justify-between border-b-2 shadow border-sage-800 px-4 py-2">
      <h2 class="text-headline-2xl">{{ $t('sidebar.foldersSidebar.title', 'Folders') }}</h2>
      <RouterLink to="/folders" class="text-xs text-sage-300 hover:text-white transition-colors">
        {{ $t('sidebar.foldersSidebar.viewAll', 'View all folders →') }}
      </RouterLink>
    </div>

    <!-- Search -->
    <div class="px-4 pt-4 pb-2">
      <div class="relative">
        <i class="fa fa-search absolute left-3 top-1/2 -translate-y-1/2 text-sage-400 text-sm"></i>
        <Input
          v-model="searchTerm"
          type="text"
          icon="fa fa-search"
          :placeholder="$t('sidebar.foldersSidebar.search', 'Search a folder...')"
          dark
          size="sm"
        />
      </div>
    </div>

    <!-- Folders List -->
    <div class="flex-1 overflow-y-auto px-2 pt-4 pb-12 relative">
      <div
        class="fixed h-4 w-full bg-transparent bg-gradient-to-b from-sage-950 to-transparent z-20 top-[180px]"
      ></div>
      <div
        class="fixed bottom-16 h-4 w-full bg-transparent bg-gradient-to-t from-sage-950 to-transparent z-20"
      ></div>
      <div v-if="isLoading" class="flex items-center justify-center py-8">
        <i class="fa fa-spinner fa-spin text-sage-400"></i>
      </div>

      <div
        v-else-if="filteredFolders.length === 0"
        class="px-4 py-8 text-center text-sage-400 text-sm"
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
            class="flex items-center gap-2 px-2 py-1.5 text-sage-300 rounded-md cursor-pointer transition-colors group"
          >
            <i class="fa fa-heart text-sm"></i>
            <span class="text-sm font-medium flex-1">{{
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
            class="flex items-center gap-2 px-2 py-1.5 text-sage-300 rounded-md cursor-pointer transition-colors group"
          >
            <i class="fa fa-folders text-sm"></i>
            <span class="text-sm font-medium flex-1">{{
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
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { foldersWithItemsQuery } from '@/queries/folders'
import type { Folder } from '@/types/folder'
import FolderRow from './FolderRow.vue'
import Input from '../ui/Input.vue'

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

// Computed folders lists
const allFolders = computed(() => foldersData.value || [])

const favoriteFolders = computed(() => {
  return allFolders.value.filter((f) => f.is_favorite)
})

const regularFolders = computed(() => {
  return allFolders.value.filter((f) => !f.is_favorite)
})

// Filter folders based on search
const filteredFolders = computed(() => {
  if (!searchTerm.value.trim()) {
    return allFolders.value
  }

  const query = searchTerm.value.toLowerCase()

  return allFolders.value.filter((folder) => {
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

// Toggle section expansion
const toggleSection = (section: 'favorites') => {
  expandedSections.value[section] = !expandedSections.value[section]
  saveSectionsState()
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

const saveSectionsState = () => {
  localStorage.setItem(STORAGE_KEY_SECTIONS, JSON.stringify(expandedSections.value))
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
