<template>
  <div class="min-h-screen bg-bg3">
    <div class="container mx-auto px-4 py-8">
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
            @click="$router.push('/workspaces/create')"
          />
        </div>

        <!-- Search and Filters -->
        <div class="flex items-center gap-4 bg-bg1 p-4 rounded-lg shadow-sm border border-border-2">
          <!-- Search Input -->
          <div class="flex-1 max-w-md">
            <div class="relative">
              <i
                class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary"
              ></i>
              <input
                v-model="queryParams.search"
                @input="searchWorkspaces(queryParams.search)"
                type="text"
                :placeholder="$t('workspace.search.placeholder', 'Search workspaces...')"
                class="w-full pl-10 pr-4 py-2 border border-border-2 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary"
              />
            </div>
          </div>

          <!-- Sort Options -->
          <div class="flex items-center gap-2">
            <label class="text-sm text-secondary">{{
              $t('workspace.sort.label', 'Sort by:')
            }}</label>
            <select
              v-model="queryParams.sort"
              @change="changeSorting(queryParams.sort, queryParams.order)"
              class="px-3 py-2 border border-border-2 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
            >
              <option value="created_at">{{ $t('workspace.sort.created', 'Created Date') }}</option>
              <option value="name">{{ $t('workspace.sort.name', 'Name') }}</option>
              <option value="member_count">
                {{ $t('workspace.sort.members', 'Member Count') }}
              </option>
            </select>

            <Button
              variant="tertiary"
              :icon="queryParams.order === 'asc' ? 'fa fa-sort-up' : 'fa fa-sort-down'"
              icon-only
              :title="
                queryParams.order === 'asc'
                  ? $t('workspace.sort.desc', 'Sort Descending')
                  : $t('workspace.sort.asc', 'Sort Ascending')
              "
              @click="changeSorting(queryParams.sort, queryParams.order === 'asc' ? 'desc' : 'asc')"
            />
          </div>

          <!-- Page Size Selector -->
          <div class="flex items-center gap-2">
            <label class="text-sm text-secondary">{{
              $t('workspace.pageSize.label', 'Show:')
            }}</label>
            <select
              v-model="queryParams.limit"
              @change="changePageSize(parseInt($event.target.value))"
              class="px-3 py-2 border border-border-2 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
            >
              <option value="10">10</option>
              <option value="20">20</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
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
                    variant="tertiary"
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
                    variant="tertiary"
                    icon="fa fa-eye"
                    icon-only
                    size="sm"
                    :title="$t('workspace.view', 'View workspace')"
                  />

                  <Button
                    @click="showDeleteModal(workspace)"
                    :disabled="workspace.id === 1"
                    variant="tertiary"
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

        <!-- Pagination -->
        <div
          v-if="pagination && pagination.totalPages > 1"
          class="px-6 py-4 border-t border-border-2 bg-bg2"
        >
          <div class="flex items-center justify-between">
            <!-- Results Info -->
            <div class="text-sm text-secondary">
              {{ $t('workspace.pagination.showing', 'Showing') }}
              <span class="font-medium">{{ (pagination.page - 1) * pagination.limit + 1 }}</span>
              {{ $t('workspace.pagination.to', 'to') }}
              <span class="font-medium">{{
                Math.min(pagination.page * pagination.limit, pagination.total)
              }}</span>
              {{ $t('workspace.pagination.of', 'of') }}
              <span class="font-medium">{{ pagination.total }}</span>
              {{ $t('workspace.pagination.results', 'results') }}
            </div>

            <!-- Pagination Controls -->
            <div class="flex items-center gap-2">
              <!-- Previous Button -->
              <button
                @click="goToPage(pagination.page - 1)"
                :disabled="!pagination.hasPrev"
                class="px-3 py-2 text-sm border border-border-2 rounded-lg hover:bg-bg3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i class="fa fa-chevron-left mr-1"></i>
                {{ $t('workspace.pagination.previous', 'Previous') }}
              </button>

              <!-- Page Numbers -->
              <div class="flex items-center gap-1">
                <!-- First page -->
                <button
                  v-if="pagination.page > 3"
                  @click="goToPage(1)"
                  class="px-3 py-2 text-sm border border-border-2 rounded-lg hover:bg-bg3 transition-colors"
                >
                  1
                </button>
                <span v-if="pagination.page > 4" class="px-2 text-secondary">...</span>

                <!-- Current page and neighbors -->
                <template v-for="page in getVisiblePages(pagination)" :key="page">
                  <button
                    @click="goToPage(page)"
                    :class="[
                      'px-3 py-2 text-sm border rounded-lg transition-colors',
                      page === pagination.page
                        ? 'bg-primary text-white border-primary'
                        : 'border-border-2 hover:bg-bg3',
                    ]"
                  >
                    {{ page }}
                  </button>
                </template>

                <!-- Last page -->
                <span v-if="pagination.page < pagination.totalPages - 3" class="px-2 text-secondary"
                  >...</span
                >
                <button
                  v-if="pagination.page < pagination.totalPages - 2"
                  @click="goToPage(pagination.totalPages)"
                  class="px-3 py-2 text-sm border border-border-2 rounded-lg hover:bg-bg3 transition-colors"
                >
                  {{ pagination.totalPages }}
                </button>
              </div>

              <!-- Next Button -->
              <button
                @click="goToPage(pagination.page + 1)"
                :disabled="!pagination.hasNext"
                class="px-3 py-2 text-sm border border-border-2 rounded-lg hover:bg-bg3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ $t('workspace.pagination.next', 'Next') }}
                <i class="fa fa-chevron-right ml-1"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
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
import { ref, computed, reactive } from 'vue'
import { useQuery } from '@pinia/colada'
import { useRouter } from 'vue-router'
import { allWorkspacesQuery, currentWorkspaceQuery } from '@/queries/workspace'
import { useDeleteWorkspace, usePickWorkspace } from '@/mutations/workspace'
import type { WorkspaceResponse, WorkspaceListItem, WorkspaceQueryParams } from '@/types/workspace'
import WorkspaceDeleteModal from '@/components/workspace/WorkspaceDeleteModal.vue'
import WorkspacePickModal from '@/components/workspace/WorkspacePickModal.vue'
import Button from '@/components/ui/Button.vue'

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

// Format date helper
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

// Pagination actions
const goToPage = (page: number) => {
  queryParams.page = page
}

const changePageSize = (limit: number) => {
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

// Helper function to get visible page numbers for pagination
const getVisiblePages = (paginationInfo: NonNullable<typeof pagination.value>) => {
  const current = paginationInfo.page
  const total = paginationInfo.totalPages
  const pages: number[] = []

  // Show current page and 2 neighbors on each side
  const start = Math.max(1, current - 2)
  const end = Math.min(total, current + 2)

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  return pages
}

// Actions
const viewWorkspace = (id: number) => {
  router.push(`/workspaces/${id}`)
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
