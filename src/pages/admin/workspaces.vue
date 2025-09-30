<template>
  <div class="min-h-screen bg-bg3">
    <div>
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-3xl font-bold">
              {{ $t('workspace.admin.title', 'Workspace Management') }}
            </h1>
            <p class="text-secondary mt-2">
              {{ $t('workspace.admin.description', 'Manage all workspaces in the system') }}
            </p>
          </div>

          <Button
            variant="primary"
            icon="fa fa-plus"
            :label="$t('workspace.create.button', 'Create Workspace')"
            @click="$router.push('/admin/workspaces/create')"
          />
        </div>

        <!-- Search and Filters -->
        <div class="flex items-center justify-between gap-4">
          <!-- Search Input -->
          <div class="flex-1 max-w-md">
            <div class="relative">
              <i
                class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary"
              ></i>
              <Input
                :model-value="queryParams.search"
                @input="searchWorkspaces($event.target.value)"
                type="text"
                :placeholder="$t('workspace.search.placeholder', 'Search workspaces...')"
              />
            </div>
          </div>

          <div class="flex items-center gap-2">
            <!-- Sort Dropdown -->
            <div class="relative">
              <button
                @click.stop="showSortDropdown = !showSortDropdown"
                class="flex items-center gap-2 px-3 py-2 border border-border-2 rounded-lg hover:bg-bg2 transition-colors text-sm font-medium bg-bg1"
              >
                <span class="text-secondary">{{ getSortDisplayText() }}</span>
                <i
                  class="fa fa-chevron-down text-xs transition-transform"
                  :class="{ 'rotate-180': showSortDropdown }"
                ></i>
              </button>

              <div
                v-if="showSortDropdown"
                class="absolute right-0 mt-2 w-64 bg-bg1 border border-border-2 rounded-lg shadow-lg z-50"
                @click.stop
              >
                <div class="p-4 border-b border-border-2">
                  <h3 class="text-sm font-medium text-primary mb-3">
                    {{ $t('workspace.sort.label', 'Sort by:') }}
                  </h3>
                  <div class="space-y-2">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        type="radio"
                        :checked="queryParams.sort === 'created_at'"
                        @change="updateSort('created_at')"
                        class="w-4 h-4 text-primary border-border-2 focus:ring-primary/20"
                      />
                      <span class="text-sm">{{
                        $t('workspace.sort.created', 'Created Date')
                      }}</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        type="radio"
                        :checked="queryParams.sort === 'name'"
                        @change="updateSort('name')"
                        class="w-4 h-4 text-primary border-border-2 focus:ring-primary/20"
                      />
                      <span class="text-sm">{{ $t('workspace.sort.name', 'Name') }}</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        type="radio"
                        :checked="queryParams.sort === 'member_count'"
                        @change="updateSort('member_count')"
                        class="w-4 h-4 text-primary border-border-2 focus:ring-primary/20"
                      />
                      <span class="text-sm">{{
                        $t('workspace.sort.members', 'Member Count')
                      }}</span>
                    </label>
                  </div>
                </div>

                <div class="p-4">
                  <h3 class="text-sm font-medium text-primary mb-3">
                    {{ $t('workspace.sort.order', 'Sort Order:') }}
                  </h3>
                  <div class="space-y-2">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        type="radio"
                        :checked="queryParams.order === 'asc'"
                        @change="updateOrder('asc')"
                        class="w-4 h-4 text-primary border-border-2 focus:ring-primary/20"
                      />
                      <span class="text-sm flex items-center gap-2">
                        <i class="fa fa-sort-amount-up"></i>
                        {{ $t('workspace.sort.ascending', 'Ascending (A-Z)') }}
                      </span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        type="radio"
                        :checked="queryParams.order === 'desc'"
                        @change="updateOrder('desc')"
                        class="w-4 h-4 text-primary border-border-2 focus:ring-primary/20"
                      />
                      <span class="text-sm flex items-center gap-2">
                        <i class="fa fa-sort-amount-down"></i>
                        {{ $t('workspace.sort.descending', 'Descending (Z-A)') }}
                      </span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Error Alert -->
      <div
        v-if="error"
        class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"
      >
        <div class="flex items-center gap-2">
          <i class="fa fa-exclamation-triangle"></i>
          <span class="font-medium">Error:</span>
          <span>{{ error.message }}</span>
        </div>
      </div>

      <!-- Loading State -->
      <div
        v-if="isLoading"
        class="bg-bg1 rounded-lg shadow-sm p-8 text-center border border-border-2"
      >
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-secondary">{{ $t('workspace.loading', 'Loading workspaces...') }}</p>
      </div>

      <!-- Workspaces List -->
      <div
        v-else-if="workspaces"
        class="bg-bg1 rounded-lg shadow-sm overflow-hidden border border-border-2"
      >
        <!-- Table Header -->
        <div class="px-6 py-4 border-b border-border-2 bg-bg2">
          <div class="grid grid-cols-12 gap-4 text-sm font-medium text-secondary">
            <div class="col-span-3">{{ $t('workspace.name', 'Name') }}</div>
            <div class="col-span-2">{{ $t('workspace.slug', 'Slug') }}</div>
            <div class="col-span-3">{{ $t('workspace.table.description', 'Description') }}</div>
            <div class="col-span-2">{{ $t('workspace.created', 'Created') }}</div>
            <div class="col-span-2 text-right">{{ $t('workspace.actions', 'Actions') }}</div>
          </div>
        </div>

        <!-- Table Body -->
        <div class="divide-y divide-border-2">
          <div
            v-for="workspace in workspacesWithMemberCount"
            :key="workspace.id"
            class="px-6 py-4 transition-colors"
          >
            <div class="grid grid-cols-12 gap-4 items-center">
              <!-- Name -->
              <div class="col-span-3 flex items-center gap-2">
                <div class="text-center">
                  <span
                    class="inline-flex items-center justify-center w-8 h-8 bg-primary/10 text-primary rounded-full text-sm font-medium"
                  >
                    {{ workspace.memberCount }}
                  </span>
                </div>
                <div>
                  <div class="font-medium">{{ workspace.name }}</div>
                  <div
                    v-if="currentWorkspace && workspace.id === currentWorkspace.id"
                    class="text-xs text-green-600 mt-1"
                  >
                    <i class="fa fa-check-circle mr-1"></i>
                    {{ $t('workspace.current', 'Current workspace') }}
                  </div>
                </div>
              </div>

              <!-- Slug -->
              <div class="col-span-2">
                <code class="text-sm bg-bg3 px-2 py-1 rounded text-secondary">{{
                  workspace.slug
                }}</code>
              </div>

              <!-- Description -->
              <div class="col-span-3">
                <p class="text-secondary text-sm">
                  {{ workspace.description || $t('workspace.noDescription', 'No description') }}
                </p>
              </div>

              <!-- Created Date -->
              <div class="col-span-2">
                <div class="text-sm text-secondary">
                  {{ formatDate(workspace.created_at) }}
                </div>
              </div>

              <!-- Actions -->
              <div class="col-span-2">
                <div class="flex items-center gap-1 justify-end">
                  <!-- Pick Workspace Button -->
                  <Button
                    @click="showPickModal(workspace)"
                    :disabled="currentWorkspace && workspace.id === currentWorkspace.id"
                    variant="ghost-primary"
                    icon="fa fa-exchange-alt"
                    icon-only
                    size="sm"
                    :title="
                      currentWorkspace && workspace.id === currentWorkspace.id
                        ? $t('workspace.alreadyCurrent', 'This is your current workspace')
                        : $t('workspace.pick', 'Switch to this workspace')
                    "
                  />

                  <Button
                    @click="viewWorkspace(workspace.id)"
                    variant="ghost-primary"
                    icon="fa fa-eye"
                    icon-only
                    size="sm"
                    :title="$t('workspace.view', 'View workspace')"
                  />

                  <Button
                    @click="showDeleteModal(workspace)"
                    :disabled="workspace.id === 1"
                    variant="ghost-primary"
                    color="danger"
                    icon="fa fa-trash"
                    icon-only
                    size="sm"
                    :title="
                      workspace.id === 1
                        ? $t('workspace.cannotDeleteDefault', 'Cannot delete default workspace')
                        : $t('workspace.delete', 'Delete workspace')
                    "
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="workspaces.length === 0 && !isLoading" class="p-12 text-center">
          <i class="fa fa-building text-4xl text-secondary/50 mb-4"></i>
          <h3 class="text-lg font-medium text-base mb-2">
            {{
              queryParams.search
                ? $t('workspace.empty.noResults', 'No workspaces found')
                : $t('workspace.empty.title', 'No workspaces found')
            }}
          </h3>
          <p class="text-secondary mb-6">
            {{
              queryParams.search
                ? $t('workspace.empty.tryDifferentSearch', 'Try a different search term')
                : $t('workspace.empty.description', 'Create your first workspace to get started')
            }}
          </p>
          <Button
            v-if="!queryParams.search"
            @click="$router.push('/workspaces/create')"
            :label="$t('workspace.create.button', 'Create Workspace')"
            variant="primary"
          />
          <Button
            v-else
            @click="((queryParams.search = ''), searchWorkspaces(''))"
            :label="$t('workspace.clearSearch', 'Clear Search')"
            variant="secondary"
          />
        </div>

      </div>

      <Pagination
        v-model:current-page="currentPage"
        :meta="paginationMeta"
        :page-size-options="pageSizeOptions"
        item-name="workspaces"
        @update-per-page="updatePageSize"
      />
    </div>

    <!-- Delete Confirmation Modal -->
    <WorkspaceDeleteModal
      v-if="workspaceToDelete"
      :workspace="workspaceToDelete"
      @confirm="handleDelete"
      @cancel="workspaceToDelete = null"
    />

    <!-- Pick Workspace Confirmation Modal -->
    <WorkspacePickModal
      v-if="workspaceToPick"
      :workspace="workspaceToPick"
      :is-loading="isPicking"
      @confirm="handlePick"
      @cancel="workspaceToPick = null"
    />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workspaces
</route>

<script setup lang="ts">
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue'
import { useQuery } from '@pinia/colada'
import { useRouter } from 'vue-router'
import { allWorkspacesQuery, currentWorkspaceQuery } from '@/queries/workspace'
import { useDeleteWorkspace, usePickWorkspace } from '@/mutations/workspace'
import type { WorkspaceResponse, WorkspaceListItem, WorkspaceQueryParams } from '@/types/workspace'
import WorkspaceDeleteModal from '@/components/workspace/WorkspaceDeleteModal.vue'
import WorkspacePickModal from '@/components/workspace/WorkspacePickModal.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Pagination from '@/components/ui/Pagination.vue'
import type { PaginationMeta } from '@/types/pagination'

const router = useRouter()

// Query parameters state
const queryParams = reactive<WorkspaceQueryParams>({
  page: 1,
  limit: 20,
  sort: 'created_at',
  order: 'desc',
  search: '',
})

// Queries
const {
  data: workspacesResponse,
  isLoading,
  error,
} = useQuery(allWorkspacesQuery, () => queryParams)
const { data: currentWorkspace } = useQuery(currentWorkspaceQuery, () => ({}))

// Mutations
const { deleteWorkspace, isLoading: isDeleting } = useDeleteWorkspace()
const { pickWorkspace, isLoading: isPicking } = usePickWorkspace()

// Modal states
const workspaceToDelete = ref<WorkspaceResponse | null>(null)
const workspaceToPick = ref<WorkspaceResponse | null>(null)

// Transform workspaces to include backward compatible memberCount property
const workspacesWithMemberCount = computed<WorkspaceListItem[]>(() => {
  if (!workspacesResponse.value?.data) return []

  return workspacesResponse.value.data.map((workspace) => ({
    ...workspace,
    memberCount: workspace.member_count, // Map to backward compatible property
  }))
})

// Pagination info
const pagination = computed(() => workspacesResponse.value?.pagination)
const workspaces = computed(() => workspacesResponse.value?.data || [])

// Convert workspace pagination format to PaginationMeta format
const paginationMeta = computed<PaginationMeta | null>(() => {
  if (!pagination.value) return null

  return {
    total: pagination.value.total,
    per_page: pagination.value.limit,
    current_page: pagination.value.page,
    last_page: pagination.value.totalPages,
    from: (pagination.value.page - 1) * pagination.value.limit + 1,
    to: Math.min(pagination.value.page * pagination.value.limit, pagination.value.total),
  }
})

// Current page for v-model binding
const currentPage = computed({
  get: () => queryParams.page,
  set: (value: number) => {
    queryParams.page = value
  },
})

// Page size options
const pageSizeOptions = [10, 20, 50, 100]

// Format date helper
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

// Dropdown state
const showSortDropdown = ref(false)

// Helper methods for dropdown
const getSortDisplayText = () => {
  const sortLabels = {
    created_at: 'Created Date',
    name: 'Name',
    member_count: 'Member Count',
  }
  const orderText = queryParams.order === 'asc' ? 'A-Z' : 'Z-A'
  return `${sortLabels[queryParams.sort]} (${orderText})`
}

const updateSort = (newSort: WorkspaceQueryParams['sort']) => {
  changeSorting(newSort, queryParams.order)
  showSortDropdown.value = false
}

const updateOrder = (newOrder: WorkspaceQueryParams['order']) => {
  if (newOrder !== queryParams.order) {
    changeSorting(queryParams.sort, newOrder)
  }
  showSortDropdown.value = false
}

// Close dropdown when clicking outside
const handleClickOutside = (event: MouseEvent) => {
  if (showSortDropdown.value) {
    showSortDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

// Page size update for Pagination component
const updatePageSize = (limit: number) => {
  queryParams.limit = limit
  queryParams.page = 1 // Reset to first page when changing page size
}

const changeSorting = (
  sort: WorkspaceQueryParams['sort'],
  order: WorkspaceQueryParams['order'] = 'desc',
) => {
  queryParams.sort = sort
  queryParams.order = order
  queryParams.page = 1 // Reset to first page when changing sort
}

const searchWorkspaces = (search: string) => {
  queryParams.search = search
  queryParams.page = 1 // Reset to first page when searching
}


// Actions
const viewWorkspace = (id: number) => {
  router.push(`/admin/workspaces/${id}`)
}

const showDeleteModal = (workspace: WorkspaceResponse) => {
  workspaceToDelete.value = workspace
}

const handleDelete = async (id: number) => {
  try {
    await deleteWorkspace(id)
    workspaceToDelete.value = null
    // Success notification will be handled by the mutation
  } catch (error) {
    console.error('Failed to delete workspace:', error)
    // Error notification will be handled by the mutation
  }
}

const showPickModal = (workspace: WorkspaceResponse) => {
  workspaceToPick.value = workspace
}

const handlePick = async (id: number) => {
  try {
    await pickWorkspace(id)
    workspaceToPick.value = null
    // Success notification and page refresh will be handled by the mutation
  } catch (error) {
    console.error('Failed to pick workspace:', error)
    // Error notification will be handled by the mutation
  }
}
</script>
