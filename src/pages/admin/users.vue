<template>
  <div class="flex flex-col gap-4">

    <!-- Page Header -->

      <div class="flex items-start justify-between">
        <div class="flex-1">
          <h1 class="text-2xl font-bold mb-2">
            {{ $t('admin.users.title', 'User Management') }}
          </h1>
          <p class="text-secondary">
            {{ $t('admin.users.description', 'Manage user workspace assignments') }}
          </p>
        </div>
      </div>

      <!-- Filters -->
      <UserFilters
        :search="queryParams.search ?? ''"
        :workspace-filter="queryParams.workspace_filter ?? null"
        :sort="queryParams.sort"
        :order="queryParams.order"
        :workspaces="availableWorkspaces"
        @update:search="handleSearchUpdate"
        @update:workspace-filter="handleWorkspaceFilterUpdate"
        @update:sort="handleSortUpdate"
        @update:order="handleOrderUpdate"
      />


    <!-- Error Alert -->
    <Alert
      v-if="error"
      variant="error"
      title="Error"
      :message="String(error)"
      dismissible
    />

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="bg-base-100 rounded-lg shadow-sm p-8 text-center border border-primary-stroke"
    >
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
      <p class="text-secondary">
        {{ $t('admin.users.loading', 'Loading users...') }}
      </p>
    </div>

    <!-- Users Table -->
    <UserTable
      v-else-if="users"
      :users="users.data"
      :has-filters="hasActiveFilters"
      @assign-workspace="showAssignModal"
      @clear-filters="clearFilters"
    />

    <!-- Pagination -->
    <Pagination
      v-model:current-page="currentPage"
      :meta="paginationMeta"
      :page-size-options="pageSizeOptions"
      item-name="users"
      @update-per-page="updatePageSize"
    />

    <!-- User Workspace Assignment Modal -->
    <UserWorkspaceModal
      v-if="userToAssign"
      :user="userToAssign"
      :workspaces="availableWorkspaces"
      :is-loading="isAssigning"
      @confirm="handleAssignWorkspace"
      @cancel="userToAssign = null"
    />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workspaces
  requiresAuth: true
  title: 'User Management'
</route>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useQuery } from '@pinia/colada'
import Alert from '@/components/ui/Alert.vue'
import Pagination from '@/components/ui/Pagination.vue'
import UserFilters from '@/components/admin/UserFilters.vue'
import UserTable from '@/components/admin/UserTable.vue'

import { useAssignUserWorkspace } from '@/mutations/admin-users'
import { adminUsersQuery } from '@/queries/admin-users'
import { allWorkspacesQuery } from '@/queries/workspace'
import type { PaginationMeta } from '@/types/pagination'
import type { AdminUserResponse, AdminUserQueryParams } from '@/types/admin-user'
import UserWorkspaceModal from '@/components/admin/UserWorkspaceModal.vue'

// Query parameters state
const queryParams = reactive<AdminUserQueryParams>({
  page: 1,
  limit: 20,
  sort: 'created_at',
  order: 'desc',
  search: '',
  workspace_filter: null,
})

// Debounce timer for search
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

// Queries
const {
  data: usersResponse,
  isLoading,
  error,
} = useQuery(adminUsersQuery, () => ({ params: queryParams }))

const { data: workspacesResponse } = useQuery(allWorkspacesQuery, () => ({
  page: 1,
  limit: 100,
  sort: 'name' as const,
  order: 'asc' as const,
  search: undefined,
}))

// Mutations
const { assignWorkspace, isLoading: isAssigning } = useAssignUserWorkspace()

// Modal state
const userToAssign = ref<AdminUserResponse | null>(null)

// Users data
const users = computed(() => usersResponse.value)

// Available workspaces for filter dropdown
const availableWorkspaces = computed(() => workspacesResponse.value?.data || [])

// Pagination meta is already in the correct format from the backend
const paginationMeta = computed<PaginationMeta | null>(() => {
  if (!users.value?.pagination) return null
  return users.value.pagination
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

// Check if any filters are active
const hasActiveFilters = computed(() => {
  return queryParams.search !== '' || queryParams.workspace_filter !== null
})

// Event handlers
const handleSearchUpdate = (search: string) => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }

  searchDebounceTimer = setTimeout(() => {
    queryParams.search = search
    queryParams.page = 1
  }, 300) // 300ms debounce
}

const handleWorkspaceFilterUpdate = (filter: string | null) => {
  queryParams.workspace_filter = filter
  queryParams.page = 1
}

const handleSortUpdate = (sort: AdminUserQueryParams['sort']) => {
  queryParams.sort = sort
  queryParams.page = 1
}

const handleOrderUpdate = (order: AdminUserQueryParams['order']) => {
  queryParams.order = order
  queryParams.page = 1
}

const updatePageSize = (limit: number) => {
  queryParams.limit = limit
  queryParams.page = 1
}

const clearFilters = () => {
  queryParams.search = ''
  queryParams.workspace_filter = null
  queryParams.page = 1
}

// Actions
const showAssignModal = (user: AdminUserResponse) => {
  userToAssign.value = user
}

const handleAssignWorkspace = async (workspaceId: number) => {
  if (!userToAssign.value) return

  try {
    await assignWorkspace({ userId: userToAssign.value.user_id, workspaceId })
    userToAssign.value = null
  } catch (error) {
    console.error('Failed to assign workspace:', error)
  }
}
</script>
