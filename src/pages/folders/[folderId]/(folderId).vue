<template>
  <div class="min-h-screen bg-bg3">
    <div class="flex flex-col gap-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="bg-bg1 rounded-lg shadow-sm p-8 text-center">
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-secondary">{{ $t('folder.loading', 'Loading folder...') }}</p>
      </div>

      <!-- Error State -->
      <Alert
        v-else-if="status === 'error'"
        variant="error"
        :title="$t('folder.detail.error.title', 'Error')"
        :message="$t('folder.detail.error.description', 'Failed to load folder')"
        icon="fa fa-exclamation-triangle"
      />

      <!-- Folder Content -->
      <div v-else-if="status === 'success' && folder" class="flex flex-col gap-4">
        <!-- Folder Header with Search and View Mode -->
        <FoldersHeader
          v-model:search-term="searchTerm"
          v-model:view-mode="viewMode"
          :folder="folder"
          @edit-folder="$router.push(`/folders/${folder?.id}/edit`)"
          @delete-folder="confirmDelete"
        />

        <!-- Folder Items -->
        <div v-if="filteredItems && filteredItems.length > 0">
          <!-- Grid View -->
          <div
            v-if="viewMode === 'grid'"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
          >
            <!-- Items List -->
            <FolderItemDisplay
              v-for="item in filteredItems"
              :key="item.id"
              :item="item"
              mode="grid"
              @view-item="$router.push(`/folders/${route.params.folderId}/companies/${$event}`)"
              @remove-item="confirmRemoveItem"
            />
          </div>

          <!-- Table View -->
          <div v-else class="bg-bg1 rounded-lg overflow-hidden border border-border-2">
            <!-- Add Items Row -->

            <!-- Table Header -->
            <div class="px-6 py-4 border-b border-border-2 bg-bg2">
              <div class="grid grid-cols-12 gap-4 text-sm font-medium text-secondary">
                <div class="col-span-4">{{ $t('folder.item.name', 'Item') }}</div>
                <div class="col-span-2">{{ $t('folder.item.type', 'Type') }}</div>
                <div class="col-span-2">{{ $t('folder.item.created', 'Created') }}</div>
                <div class="col-span-2">{{ $t('folder.item.owner', 'Owner') }}</div>
                <div class="col-span-2 text-right">{{ $t('folder.item.actions', 'Actions') }}</div>
              </div>
            </div>

            <!-- Table Body -->
            <div class="divide-y divide-border-2">
              <div
                v-for="item in filteredItems"
                :key="item.id"
                class="px-6 py-4 hover:bg-bg2 transition-colors cursor-pointer"
                @click="navigateToItem(item)"
              >
                <div class="grid grid-cols-12 gap-4 items-center">
                  <div class="col-span-4">
                    <div class="flex items-center gap-3">
                      <div
                        class="w-10 h-10 rounded-lg bg-white ring-1 ring-border-2 overflow-hidden flex items-center justify-center flex-shrink-0"
                      >
                        <img
                          v-if="item.type === 'company' && getCompanyDomain(item.website)"
                          :src="getLogoUrl(item.website)"
                          :alt="`${item.name} logo`"
                          class="w-full h-full object-contain p-1"
                          @error="item.showFallbackIcon = true"
                          v-show="!item.showFallbackIcon"
                        />
                        <div
                          v-show="
                            item.showFallbackIcon ||
                            !getCompanyDomain(item.website) ||
                            item.type !== 'company'
                          "
                          class="w-full h-full flex items-center justify-center bg-primary/10 dark:bg-primary/20"
                        >
                          <i class="fas fa-building text-primary"></i>
                        </div>
                      </div>
                      <div class="flex-1">
                        <h3 class="font-medium">{{ item.name }}</h3>
                      </div>
                    </div>
                  </div>
                  <div class="col-span-2">
                    <Badge variant="primary" :label="item.type" size="sm" />
                  </div>
                  <div class="col-span-2">
                    <span class="text-sm text-secondary">{{ formatDate(item.created_at) }}</span>
                  </div>
                  <div class="col-span-2">
                    <span class="text-sm text-secondary">{{ item.owner_username || 'N/A' }}</span>
                  </div>
                  <div class="col-span-2 text-right">
                    <Button
                      variant="tertiary"
                      size="sm"
                      icon="fa fa-external-link-alt"
                      :label="$t('folder.item.view', 'View')"
                      @click.stop="navigateToItem(item)"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="bg-bg1 rounded-lg shadow-sm p-12 text-center">
          <i class="fas fa-folder-open text-4xl text-secondary/50 mb-4"></i>
          <h3 class="text-lg font-medium mb-2">
            {{
              searchTerm
                ? $t('folder.empty.noResults', 'No items found')
                : $t('folder.empty.title', 'No items in this folder')
            }}
          </h3>
          <p class="text-secondary mb-6">
            {{
              searchTerm
                ? $t('folder.empty.tryDifferentSearch', 'Try a different search term')
                : $t('folder.empty.description', 'Start by adding items to this folder')
            }}
          </p>
          <Button
            v-if="searchTerm"
            @click="searchTerm = ''"
            :label="$t('folder.clearSearch', 'Clear Search')"
            variant="secondary"
          />
        </div>
      </div>
    </div>

    <!-- Delete Folder Modal -->
    <FolderDeleteModal
      v-model="showDeleteModal"
      :folder-to-delete="folder || null"
      @delete-folder="$router.push('/folders')"
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
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import FolderDeleteModal from '@/components/folders/FolderDeleteModal.vue'
import FoldersHeader from '@/components/folders/FoldersHeader.vue'
import type { FolderItem } from '@/types/folder'
import { ref, computed, onMounted, watch } from 'vue'
import { folderByIdQuery } from '@/queries/folders'
import { useQuery } from '@pinia/colada'
import { useRoute, useRouter } from 'vue-router'
import FolderItemDisplay from '@/components/folders/FolderItemDisplay.vue'

// Constants
const VIEW_MODE_STORAGE_KEY = 'folder-view-mode'

const route = useRoute('/folders/[folderId]')
const router = useRouter()

const showDeleteModal = ref(false)
const searchTerm = ref('')
const viewMode = ref<'table' | 'grid'>('grid')

const {
  data: folder,
  status,
  isLoading,
} = useQuery(folderByIdQuery, () => ({
  id: route.params.folderId as string,
}))

// Computed property for filtered items
const filteredItems = computed(() => {
  if (!folder.value?.items) return []

  if (!searchTerm.value.trim()) {
    return folder.value.items
  }

  const query = searchTerm.value.toLowerCase()
  return folder.value.items.filter((item) => item.name.toLowerCase().includes(query))
})

// Helper function to extract domain from website URL
const getCompanyDomain = (website?: string) => {
  if (!website) return null
  try {
    // Remove protocol and www
    let domain = website.replace(/^https?:\/\//, '').replace(/^www\./, '')
    // Remove trailing slash and any path
    domain = domain.split('/')[0]
    return domain
  } catch {
    return null
  }
}

// Helper function to get logo URL from logo.dev
const getLogoUrl = (website?: string) => {
  const domain = getCompanyDomain(website)
  if (!domain) return ''
  return `https://img.logo.dev/${domain}?token=pk_Buf4yyXmRC2HMagyfO0jrg&retina=true`
}

// Methods
const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString()
}

const navigateToItem = (item: FolderItem) => {
  if (item.type === 'company') {
    router.push(`/folders/${route.params.folderId}/companies/${item.id}`)
  }
}

const confirmDelete = () => {
  showDeleteModal.value = true
}

const confirmRemoveItem = (item: FolderItem) => {
  // TODO: Implement remove item confirmation
  console.log('Remove item:', item)
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
