<template>
  <div class="min-h-screen">
    <!-- Full folder layout: header + tabs for folder content pages -->
    <template v-if="isFolderContentRoute">
      <div class="flex flex-col gap-4">
        <!-- Loading State -->
        <div v-if="isLoading" class="bg-base-100 rounded-lg p-8 text-center shadow-sm">
          <div
            class="border-primary mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-b-2"
          ></div>
          <p class="text-secondary">
            {{ $t('common.folder.loading', 'Loading folder...') }}
          </p>
        </div>

        <!-- Error State -->
        <Alert
          v-else-if="status === 'error'"
          variant="danger"
          :title="$t('common.folder.detail.error.title', 'Error')"
          :description="$t('common.folder.detail.error.description', 'Failed to load folder')"
          icon="fa fa-exclamation-triangle"
        />

        <!-- Folder Content -->
        <template v-else-if="status === 'success' && folder">
          <!-- Folder Header with Search and View Mode -->
          <FoldersHeader
            v-model:search-term="searchTerm"
            v-model:view-mode="viewMode"
            v-model:company-filter="companyFilter"
            :folder="folder"
            @edit-folder="$router.push(`/folders/${folder?.id}/edit`)"
            @delete-folder="confirmDelete"
          />

          <div>
            <!-- Tab Bar: only when stream feature flag is enabled -->
            <FolderTabBar
              class="relative top-px mx-6"
              v-if="isStreamEnabled"
              :folder-id="route.params.folderId as string"
              :show-streams-tab="isStreamEnabled"
            />

            <!-- Tab Content -->
            <div class="bg-base-200 border-primary-lighter-stroke rounded-xl border p-6">
              <RouterView
                :folder="folder"
                v-model:search-term="searchTerm"
                :view-mode="viewMode"
                :company-filter="companyFilter"
                @refetch="refetch"
              />
            </div>
          </div>
        </template>
      </div>

      <!-- Delete Folder Modal -->
      <FolderDeleteModal
        v-model="showDeleteModal"
        :folder-to-delete="folder || null"
        @delete-folder="$router.push('/folders')"
      />
    </template>

    <!-- Pass-through for nested pages (company detail, create, edit) -->
    <RouterView v-else />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - organization.read
</route>

<script setup lang="ts">
import FolderDeleteModal from '@/components/folders/FolderDeleteModal.vue'
import FoldersHeader from '@/components/folders/FoldersHeader.vue'
import FolderTabBar from '@/components/folders/FolderTabBar.vue'
import { useStreamModule } from '@/composables/useStreamModule'
import { folderByIdQuery } from '@/queries/folders'
import { Alert } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

const VIEW_MODE_STORAGE_KEY = 'folder-view-mode'

const route = useRoute('/folders/[folderId]')
const { isStreamEnabled } = useStreamModule()

const showDeleteModal = ref(false)
const searchTerm = ref('')
const viewMode = ref<'table' | 'grid'>('grid')
const companyFilter = ref<'all' | 'archived'>('all')

// Determine if we're on a folder content tab (items or streams list).
// Stream detail/create pages render their own layout with a back button.
const isFolderContentRoute = computed(() => {
  const path = route.path
  const folderId = route.params.folderId as string
  const folderPath = `/folders/${folderId}`
  return path === folderPath || path === `${folderPath}/streams`
})

// Only fetch folder data when on folder content routes
const {
  data: folder,
  status,
  isLoading,
  refetch,
} = useQuery(() =>
  folderByIdQuery({
    id: route.params.folderId as string,
    filters: {
      archived: companyFilter.value === 'archived',
    },
  }),
)

const confirmDelete = () => {
  showDeleteModal.value = true
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
