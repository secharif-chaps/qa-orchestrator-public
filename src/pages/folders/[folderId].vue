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
        <!-- Folder Header -->
        <div class="">
          <div class="flex items-end justify-between">
            <div class="flex items-center gap-4">
              <div
                class="w-16 h-16 rounded-lg flex items-center justify-center border border-border-2"
                :class="folderColorClasses"
              >
                <i :class="folderIcon" class="text-3xl"></i>
              </div>
              <div>
                <div class="flex items-center gap-3 mb-2">
                  <h1 class="text-3xl font-bold">{{ folder.name }}</h1>
                  <i
                    v-if="folder.is_favorite"
                    class="fas fa-star text-warning"
                    :title="$t('folder.favorite', 'Favorite folder')"
                  ></i>
                </div>
                <div class="flex items-center gap-2 text-secondary">
                  <span>{{ folder.items?.length || 0 }} items</span>
                  <span>created on {{ formatDate(folder.created_at) }}</span>
                  <span>by {{ folder.owner_username }}</span>
                </div>
                <div
                  v-if="folder.tags && folder.tags.length > 0"
                  class="flex items-center gap-2 mt-3"
                >
                  <Badge
                    v-for="tag in folder.tags"
                    :key="tag"
                    :label="tag"
                    variant="slate"
                    size="sm"
                  />
                </div>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <Button
                variant="tertiary"
                icon="fa fa-edit"
                :label="$t('folder.actions.edit', 'Edit')"
                @click="$router.push(`/folders/${folder.id}/edit`)"
              />
              <Button
                variant="tertiary"
                color="danger"
                icon="fa fa-trash"
                :label="$t('folder.actions.delete', 'Delete')"
                @click="confirmDelete"
              />
            </div>
          </div>
        </div>

        <!-- Folder Items -->
        <div class="grid grid-cols-4 gap-4">
          <!-- Add Items Dropdown -->
          <div
            class="rounded-lg border-2 border-dashed border-border-2 flex items-center justify-center h-full"
          >
            <div class="relative">
              <Button
                variant="secondary"
                icon="fa fa-plus"
                :label="$t('folder.items.add', 'Add Items')"
                @click="showAddItemsDropdown = !showAddItemsDropdown"
              />

              <!-- Backdrop to close dropdown -->
              <div
                v-if="showAddItemsDropdown"
                class="fixed inset-0 z-40"
                @click="showAddItemsDropdown = false"
              ></div>

              <!-- Dropdown Menu -->
              <div
                v-if="showAddItemsDropdown"
                class="absolute right-0 top-full mt-2 w-80 bg-bg1 border border-border-2 rounded-lg shadow-lg z-50"
              >
                <div class="p-2">
                  <!-- Company Screen - Enabled -->
                  <button
                    class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-bg2 rounded-md transition-colors"
                    @click="$router.push(`/folders/${$route.params.folderId}/create/company`)"
                  >
                    <div
                      class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center"
                    >
                      <i class="fas fa-building text-blue-600 dark:text-blue-400 text-sm"></i>
                    </div>
                    <div class="flex-1">
                      <div class="font-medium text-sm">
                        {{ $t('folder.addItems.companyScreen', 'Company Screen') }}
                      </div>
                      <div class="text-xs text-secondary">
                        {{ $t('folder.addItems.companyDescription', 'Add company profiles') }}
                      </div>
                    </div>
                  </button>

                  <!-- Watchfile - Disabled -->
                  <button
                    class="w-full flex items-center gap-3 px-3 py-2 text-left opacity-50 cursor-not-allowed rounded-md"
                    disabled
                  >
                    <div
                      class="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/20 flex items-center justify-center"
                    >
                      <i class="fas fa-eye text-green-600 dark:text-green-400 text-sm"></i>
                    </div>
                    <div class="flex-1">
                      <div class="font-medium text-sm">
                        {{ $t('folder.addItems.watchfile', 'Watchfile') }}
                      </div>
                      <div class="text-xs text-secondary">
                        {{ $t('folder.addItems.watchfileDescription', 'Monitor company changes') }}
                      </div>
                    </div>
                    <Badge variant="slate" size="xs" label="Soon" />
                  </button>

                  <!-- GraphRag - Disabled -->
                  <button
                    class="w-full flex items-center gap-3 px-3 py-2 text-left opacity-50 cursor-not-allowed rounded-md"
                    disabled
                  >
                    <div
                      class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/20 flex items-center justify-center"
                    >
                      <i
                        class="fas fa-project-diagram text-purple-600 dark:text-purple-400 text-sm"
                      ></i>
                    </div>
                    <div class="flex-1">
                      <div class="font-medium text-sm">
                        {{ $t('folder.addItems.graphrag', 'GraphRAG') }}
                      </div>
                      <div class="text-xs text-secondary">
                        {{ $t('folder.addItems.graphragDescription', 'Knowledge graphs') }}
                      </div>
                    </div>
                    <Badge variant="slate" size="xs" label="Soon" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Items List -->

          <CompanyItem
            mode="grid"
            :company="item"
            v-for="item in folder.items.filter((item) => item.type === 'company')"
            :key="item.id"
            class="p-4 hover:bg-bg2 transition-colors cursor-pointer"
            @view-company="$router.push(`/companies/${$event}`)"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 rounded-lg bg-white ring-1 ring-border-2 overflow-hidden flex items-center justify-center flex-shrink-0"
              >
                <img
                  v-if="item.item_type === 'company' && getCompanyDomain(item.website)"
                  :src="getLogoUrl(item.website)"
                  :alt="`${item.name} logo`"
                  class="w-full h-full object-contain p-1"
                  @error="item.showFallbackIcon = true"
                  v-show="!item.showFallbackIcon"
                />
                <div
                  v-show="item.showFallbackIcon || !getCompanyDomain(item.website) || item.item_type !== 'company'"
                  class="w-full h-full flex items-center justify-center bg-primary/10 dark:bg-primary/20"
                >
                  <i class="fas fa-building text-primary"></i>
                </div>
              </div>
              <div class="flex-1">
                <h3 class="font-medium">{{ item.name }}</h3>
                <p class="text-sm text-secondary">
                  Created {{ formatDate(item.created_at_item) }}
                  <span v-if="item.owner_username"> • by {{ item.owner_username }}</span>
                </p>
              </div>
            </div>
          </CompanyItem>

          <!-- Empty State -->
          <div v-if="folder.items && folder.items.length === 0" class="p-12 text-center">
            <i class="fas fa-folder-open text-4xl text-secondary/50 mb-4"></i>
            <h3 class="text-lg font-medium mb-2">
              {{ $t('folder.empty.title', 'No items in this folder') }}
            </h3>
            <p class="text-secondary mb-6">
              {{ $t('folder.empty.description', 'Start by adding items to this folder') }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Folder Modal -->
    <FolderDeleteModal
      v-model="showDeleteModal"
      :folder-to-delete="folder"
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
import type { FolderItem } from '@/types/folder'
import { ref, computed } from 'vue'
import { folderByIdQuery } from '@/queries/folders'
import { useQuery } from '@pinia/colada'
import { useRoute, useRouter } from 'vue-router'
import CompanyCard from '@/components/company/CompanyCard.vue'
import CompanyItem from '@/components/companies/CompanyItem.vue'

const route = useRoute('/folders/[folderId]')
const router = useRouter()

const showAddItemsDropdown = ref(false)
const showDeleteModal = ref(false)

const {
  data: folder,
  status,
  isLoading,
} = useQuery(folderByIdQuery, () => ({
  id: route.params.folderId,
}))

// Compute folder color classes based on the color prop
const folderColorClasses = computed(() => {
  const color = folder.value?.color || 'blue'
  const colorMap: Record<string, string> = {
    blue: 'bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400',
    green: 'bg-green-100 dark:bg-green-900/20 text-green-600 dark:text-green-400',
    yellow: 'bg-yellow-100 dark:bg-yellow-900/20 text-yellow-600 dark:text-yellow-400',
    red: 'bg-red-100 dark:bg-red-900/20 text-red-600 dark:text-red-400',
    purple: 'bg-purple-100 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400',
    gray: 'bg-gray-100 dark:bg-gray-900/20 text-gray-600 dark:text-gray-400',
    orange: 'bg-orange-100 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400',
    pink: 'bg-pink-100 dark:bg-pink-900/20 text-pink-600 dark:text-pink-400',
  }
  return colorMap[color] || colorMap.blue
})

// Compute folder icon
const folderIcon = computed(() => {
  return folder.value?.icon || 'fas fa-folder'
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
  if (item.item_type === 'company') {
    router.push(`/companies/${item.item_id}`)
  }
}

const confirmDelete = () => {
  showDeleteModal.value = true
}

const confirmRemoveItem = (item: FolderItem) => {
  // TODO: Implement remove item confirmation
  console.log('Remove item:', item)
}
</script>
