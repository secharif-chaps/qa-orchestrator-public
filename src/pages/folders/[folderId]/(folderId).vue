<template>
  <div class="min-h-screen">
    <div class="flex flex-col gap-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="bg-base-100 rounded-lg shadow-sm p-8 text-center">
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-secondary">
          {{ $t('folder.loading', 'Loading folder...') }}
        </p>
      </div>

      <!-- Error State -->
      <Alert
        v-else-if="status === 'error'"
        variant="danger"
        :title="$t('folder.detail.error.title', 'Error')"
        :description="$t('folder.detail.error.description', 'Failed to load folder')"
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
              :can-move-items="canMoveItems"
              mode="grid"
              @view-item="$router.push(`/folders/${route.params.folderId}/companies/${$event}`)"
              @remove-item="confirmRemoveItem"
              @delete-company="confirmArchiveCompany"
              @move-company="confirmMoveCompany"
            />
          </div>

          <!-- Table View -->
          <div v-else class="bg-base-100 rounded-lg overflow-hidden border border-primary-stroke">
            <!-- Add Items Row -->

            <!-- Table Header -->
            <div class="px-6 py-4 border-b border-primary-stroke bg-base-200">
              <div class="grid grid-cols-12 gap-4 text-sm font-medium text-secondary">
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
                          <i class="fas fa-building text-secondary"></i>
                        </div>
                      </div>
                      <div class="flex-1">
                        <h3 class="font-medium">{{ item.name }}</h3>
                      </div>
                    </div>
                  </div>
                  <div class="col-span-2">
                    <Tag intent="accent" :label="formatItemType(item.type)" size="sm" />
                  </div>
                  <div class="col-span-2">
                    <span class="text-sm text-secondary">{{ formatDate(item.created_at) }}</span>
                  </div>
                  <div class="col-span-2">
                    <span class="text-sm text-secondary">{{
                      item.owner || $t('common.na', 'N/A')
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
                        v-if="item.type === 'company' && canMoveItems && companyFilter !== 'archived'"
                        variant="tertiary"
                        size="sm"
                        icon="fa fa-exchange-alt"
                        icon-only
                        :title="$t('folder.moveCompany.button', 'Move to Folder')"
                        @click.stop="confirmMoveCompany(item)"
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
        <Alert
          v-else-if="searchTerm === '' && filteredItems && filteredItems.length === 0"
          variant="info"
          icon="fa fa-folder-open"
          class="py-6"
          :title="$t('folder.empty.title', 'Ce dossier est vide')"
          :description="$t('folder.empty.description', 'Ce dossier est vide')"
        >
        </Alert>

        <Alert
          v-else
          variant="info"
          icon="fa fa-folder-open"
          class="py-6"
          :title="$t('folder.empty.noResults', 'Aucun résultat trouvé')"
          :description="
            $t('folder.empty.tryDifferentSearch', 'Essayez avec un autre terme de recherche')
          "
        >
          <template #actions>
            <Button
              variant="secondary"
              :label="$t('folder.clearSearch', 'Effacer la recherche')"
              @click="searchTerm = ''"
            />
          </template>
        </Alert>
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

    <!-- Move Company Modal -->
    <CompanyMoveModal
      v-model:display-modal="showMoveModal"
      :company="companyToMove"
      :current-folder-id="route.params.folderId"
      :company-logo-url="companyToMove ? getLogoUrl(companyToMove.website) : undefined"
      @move="handleMoveCompany"
    />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - organization.read
</route>

<script setup lang="ts">
import CompanyArchiveModal from '@/components/companies/CompanyArchiveModal.vue'
import CompanyRestoreModal from '@/components/companies/CompanyRestoreModal.vue'
import CompanyMoveModal from '@/components/folders/CompanyMoveModal.vue'
import FolderDeleteModal from '@/components/folders/FolderDeleteModal.vue'
import FolderItemDisplay from '@/components/folders/FolderItemDisplay.vue'
import FoldersHeader from '@/components/folders/FoldersHeader.vue'
import { Alert, Button, Tag } from '@owlint/feathers-vue'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { useFolderPermissions } from '@/composables/useFolderPermissions'
import { folderByIdQuery } from '@/queries/folders'
import { useMoveCompanyToFolder } from '@/mutations/folders'
import type { Company } from '@/types/company'
import type { FolderItem } from '@/types/folder'
import { useQuery } from '@pinia/colada'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

// Constants
const VIEW_MODE_STORAGE_KEY = 'folder-view-mode'

const route = useRoute('/folders/[folderId]')
const router = useRouter()
const { t, locale } = useI18n()
const { canDeleteCompany } = useCompanyPermissions()

const showDeleteModal = ref(false)
const showArchiveCompanyModal = ref(false)
const showRestoreCompanyModal = ref(false)
const showMoveModal = ref(false)
const companyToArchive = ref<Company | null>(null)
const companyToMove = ref<FolderItem | null>(null)
const searchTerm = ref('')
const viewMode = ref<'table' | 'grid'>('grid')
const companyFilter = ref<'all' | 'archived'>('all')

const { mutateAsync: moveCompany } = useMoveCompanyToFolder()

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

// Folder permissions based on current folder
const { canMoveItems } = useFolderPermissions(folder)

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
  if (!dateString) return t('common.na')
  const localeCode = locale.value === 'fr-FR' ? 'fr-FR' : 'en-US'
  return new Date(dateString).toLocaleDateString(localeCode)
}

// Helper to format item type
const formatItemType = (type: string): string => {
  if (type === 'company') {
    return t('folder.itemTypes.company')
  }
  return type.charAt(0).toUpperCase() + type.slice(1)
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
      owner_username: item.owner,
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

const confirmMoveCompany = (item: FolderItem) => {
  companyToMove.value = item
  showMoveModal.value = true
}

const handleMoveCompany = async (payload: {
  destinationFolderId: string
  destinationFolderName: string
}) => {
  if (!companyToMove.value) {
    return
  }

  await moveCompany({
    sourceFolderId: route.params.folderId as string,
    destinationFolderId: payload.destinationFolderId,
    companyId: companyToMove.value.id, // Use id (which is the company ID)
    destinationFolderName: payload.destinationFolderName,
  })

  showMoveModal.value = false
  companyToMove.value = null
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
