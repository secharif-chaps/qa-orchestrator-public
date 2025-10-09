<template>
  <div class="min-h-screen">
    <div class="flex flex-col gap-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="bg-base-100 rounded-lg shadow-sm p-8 text-center">
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-primary-light-content">
          {{ $t('folder.loading', 'Loading folder...') }}
        </p>
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
          v-model:company-filter="companyFilter"
          :folder="folder"
          @edit-folder="$router.push(`/folders/${folder?.id}/edit`)"
          @delete-folder="confirmDelete"
        />

        <!-- Folder Items -->
        <div v-if="filteredItems && filteredItems.length > 0">
          <!-- Grid View -->
          <div
            v-if="viewMode === 'grid'"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-6"
          >
            <!-- Items List -->
            <FolderItemDisplay
              v-model:company-filter="companyFilter"
              v-for="item in filteredItems"
              :key="item.id"
              :item="item"
              :is-archived="companyFilter === 'archived'"
              mode="grid"
              @view-item="$router.push(`/folders/${route.params.folderId}/companies/${$event}`)"
              @remove-item="confirmRemoveItem"
              @delete-company="confirmArchiveCompany"
            />
          </div>

          <!-- Table View -->
          <div v-else class="bg-base-100 rounded-lg overflow-hidden border border-primary-stroke">
            <!-- Add Items Row -->

            <!-- Table Header -->
            <div class="px-6 py-4 border-b border-primary-stroke bg-base-200">
              <div class="grid grid-cols-12 gap-4 text-sm font-medium text-primary-light-content">
                <div class="col-span-4">{{ $t('folder.item.name', 'Item') }}</div>
                <div class="col-span-2">{{ $t('folder.item.type', 'Type') }}</div>
                <div class="col-span-2">{{ $t('folder.item.created', 'Created') }}</div>
                <div class="col-span-2">{{ $t('folder.item.owner', 'Owner') }}</div>
                <div class="col-span-2 text-right">{{ $t('folder.item.actions', 'Actions') }}</div>
              </div>
            </div>

            <!-- Table Body -->
            <div class="divide-y divide-primary-stroke">
              <div
                v-for="item in filteredItems"
                :key="item.id"
                class="px-6 py-4 hover:bg-base-200 transition-colors"
                :class="{
                  'cursor-auto': companyFilter === 'archived',
                  'cursor-pointer': companyFilter !== 'archived',
                }"
                @click="companyFilter !== 'archived' && navigateToItem(item)"
              >
                <div class="grid grid-cols-12 gap-4 items-center">
                  <div class="col-span-4">
                    <div class="flex items-center gap-3">
                      <div
                        class="w-10 h-10 rounded-lg bg-white ring-1 ring-primary-stroke overflow-hidden flex items-center justify-center flex-shrink-0"
                      >
                        <img
                          v-if="item.type === 'company' && getCompanyDomain(item.website)"
                          :src="getLogoUrl(item.website)"
                          :alt="`${item.name} logo`"
                          class="w-full h-full object-contain p-1"
                          :class="{ grayscale: companyFilter === 'archived' }"
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
                          <i class="fas fa-building text-primary-light-content"></i>
                        </div>
                      </div>
                      <div class="flex-1">
                        <h3 class="font-medium">{{ item.name }}</h3>
                      </div>
                    </div>
                  </div>
                  <div class="col-span-2">
                    <Tag variant="primary" :label="item.type" size="sm" />
                  </div>
                  <div class="col-span-2">
                    <span class="text-sm text-primary-light-content">{{
                      formatDate(item.created_at)
                    }}</span>
                  </div>
                  <div class="col-span-2">
                    <span class="text-sm text-primary-light-content">{{
                      item.owner_username || $t('common.na', 'N/A')
                    }}</span>
                  </div>
                  <div class="col-span-2 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <Button
                        variant="tertiary"
                        size="sm"
                        icon="fa fa-external-link-alt"
                        :label="$t('folder.item.view', 'View')"
                        @click.stop="navigateToItem(item)"
                        :hidden="companyFilter === 'archived'"
                      />
                      <Button
                        v-if="item.type === 'company' && canDeleteCompany"
                        variant="tertiary"
                        color="danger"
                        size="sm"
                        :icon="companyFilter === 'archived' ? 'fa fa-undo' : 'fa fa-archive'"
                        icon-only
                        :title="
                          companyFilter === 'archived'
                            ? $t('company.restore.title', 'Restore Company')
                            : $t('company.delete.title', 'Delete Company')
                        "
                        @click.stop="confirmArchiveCompany(item)"
                      />
                      <!-- Deleted Tag -->
                      <span
                        v-if="companyFilter === 'archived'"
                        class="inline-block text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded ml-2"
                      >
                        {{ $t('folder.item.deleted', 'Deleted') }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="bg-base-100 rounded-lg shadow-sm p-12 text-center">
          <i class="fas fa-folder-open text-4xl text-primary-light-content/50 mb-4"></i>
          <h3 class="text-lg font-medium mb-2">
            {{
              searchTerm
                ? $t('folder.empty.noResults', 'No items found')
                : $t('folder.empty.title', 'No items in this folder')
            }}
          </h3>
          <p class="text-primary-light-content mb-6">
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

    <!-- Archive company Modal -->
    <CompanyArchiveModal
      v-model="showArchiveCompanyModal"
      :company-to-archive="companyToArchive"
      @archive-company="handleArchiveCompany"
    />

    <!-- Restore company Modal -->
    <CompanyRestoreModal
      v-model="showRestoreCompanyModal"
      :company-to-restore="companyToArchive"
      @restore-company="handleRestoreCompany"
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
import Tag from '@/components/ui/Tag.vue'
import Button from '@/components/ui/Button.vue'
import FolderDeleteModal from '@/components/folders/FolderDeleteModal.vue'
import CompanyArchiveModal from '@/components/companies/CompanyArchiveModal.vue'
import CompanyRestoreModal from '@/components/companies/CompanyRestoreModal.vue'
import FoldersHeader from '@/components/folders/FoldersHeader.vue'
import type { FolderItem } from '@/types/folder'
import type { Company } from '@/types/company'
import { ref, computed, onMounted, watch } from 'vue'
import { folderByIdQuery } from '@/queries/folders'
import { useQuery } from '@pinia/colada'
import { useRoute, useRouter } from 'vue-router'
import FolderItemDisplay from '@/components/folders/FolderItemDisplay.vue'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { useI18n } from 'vue-i18n'

// Constants
const VIEW_MODE_STORAGE_KEY = 'folder-view-mode'

const route = useRoute('/folders/[folderId]')
const router = useRouter()
const { t: $t } = useI18n()
const { canDeleteCompany } = useCompanyPermissions()

const showDeleteModal = ref(false)
const showArchiveCompanyModal = ref(false)
const showRestoreCompanyModal = ref(false)
const companyToArchive = ref<Company | null>(null)
const searchTerm = ref('')
const viewMode = ref<'table' | 'grid'>('grid')
const companyFilter = ref<'all' | 'archived'>('all')

const {
  data: folder,
  status,
  isLoading,
  refetch,
} = useQuery(folderByIdQuery, () => ({
  id: route.params.folderId as string,
  filters: {
    archived: companyFilter.value === 'archived',
  },
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
  if (!dateString) return $t('common.na', 'N/A')
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

const confirmArchiveCompany = (item: FolderItem) => {
  // Convert FolderItem to Company format for the delete modal
  if (item.type === 'company') {
    companyToArchive.value = {
      id: parseInt(item.id),
      name: item.name,
      website: item.website,
      created_at: item.created_at,
      owner_username: item.owner_username,
    } as Company
    if (companyFilter.value === 'archived') {
      showRestoreCompanyModal.value = true
    } else {
      showArchiveCompanyModal.value = true
    }
  }
}

const handleArchiveCompany = async () => {
  // Refresh the folder items after successful deletion
  await refetch()
  companyToArchive.value = null
}

const handleRestoreCompany = async () => {
  // Refresh the folder items after successful restoration
  await refetch()
  companyToArchive.value = null
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
