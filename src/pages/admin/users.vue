<template>
  <div class="flex flex-col gap-4">

    <!-- Page Header -->

      <div class="flex items-start justify-between">
        <div class="flex-1">
          <h1 class="text-2xl font-bold mb-2">
            {{ $t('admin.users.title', 'User Management') }}
          </h1>
          <p class="text-secondary">
            {{ $t('admin.users.description', 'Manage user organization assignments') }}
          </p>
        </div>
      </div>

      <!-- Filters -->
      <UserFilters
        :search="queryParams.search ?? ''"
        :organization-filter="queryParams.organization_filter ?? null"
        :sort="queryParams.sort"
        :order="queryParams.order"
        :organizations="availableOrganizations"
        @update:search="handleSearchUpdate"
        @update:organization-filter="handleOrganizationFilterUpdate"
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
      @assign-organization="showAssignModal"
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

    <!-- User Organization Assignment Modal -->
    <UserOrganizationModal
      v-if="userToAssign"
      :user="userToAssign"
      :organizations="availableOrganizations"
      :is-loading="isAssigning"
      @confirm="handleAssignOrganization"
      @cancel="userToAssign = null"
    />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.organizations
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

import { useAssignUserOrganization } from '@/mutations/admin-users'
import { adminUsersQuery } from '@/queries/admin-users'
import { allOrganizationsQuery } from '@/queries/organization-admin'
import type { PaginationMeta } from '@/types/pagination'
import type { AdminUserResponse, AdminUserQueryParams } from '@/types/admin-user'
import UserOrganizationModal from '@/components/admin/UserOrganizationModal.vue'

// Query parameters state
const queryParams = reactive<AdminUserQueryParams>({
  page: 1,
  limit: 20,
  sort: 'created_at',
  order: 'desc',
  search: '',
  organization_filter: null,
})

// Debounce timer for search
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

// Queries
const {
  data: usersResponse,
  isLoading,
  error,
} = useQuery(adminUsersQuery, () => ({ params: queryParams }))

const { data: organizationsResponse } = useQuery(allOrganizationsQuery, () => ({
  page: 1,
  limit: 100,
  sort: 'name' as const,
  order: 'asc' as const,
  search: undefined,
}))

// Mutations
const { assignOrganization, isLoading: isAssigning } = useAssignUserOrganization()

// Modal state
const userToAssign = ref<AdminUserResponse | null>(null)

// Users data
const users = computed(() => usersResponse.value)

// Available organizations for filter dropdown
const availableOrganizations = computed(() => organizationsResponse.value?.data || [])

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
  return queryParams.search !== '' || queryParams.organization_filter !== null
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

const handleOrganizationFilterUpdate = (filter: string | null) => {
  queryParams.organization_filter = filter
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
  queryParams.organization_filter = null
  queryParams.page = 1
}

// Actions
const showAssignModal = (user: AdminUserResponse) => {
  userToAssign.value = user
}

const handleAssignOrganization = async (organizationId: string) => {
  if (!userToAssign.value) return

  try {
    await assignOrganization({ userId: userToAssign.value.user_id, organizationId })
    userToAssign.value = null
  } catch (error) {
    console.error('Failed to assign organization:', error)
  }
}
</script>
