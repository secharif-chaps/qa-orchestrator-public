<template>
  <div class="flex flex-col gap-4">
    <!-- Page Header -->

    <div class="flex items-end justify-between">
      <div class="flex-1">
        <h1 class="mb-2 text-2xl font-bold">
          {{ $t('admin.users.title', 'User Management') }}
        </h1>
        <p class="text-secondary">
          {{ $t('admin.users.description', 'Manage all users across organizations') }}
        </p>
      </div>
      <div class="flex gap-2">
        <UserFilters
          :search="queryParams.search ?? ''"
          :sort="queryParams.sort"
          :order="queryParams.order"
          @update:search="handleSearchUpdate"
          @update:sort="handleSortUpdate"
          @update:order="handleOrderUpdate"
        />
        <Button
          variant="secondary"
          icon="fa fa-file-import"
          :label="$t('admin.import.title', 'Import Users')"
          @click="router.push('/admin/users/import')"
        />
      </div>
    </div>

    <!-- Filters -->

    <!-- Error Alert -->
    <Alert
      v-if="error"
      variant="danger"
      :title="$t('common.error')"
      :description="String(error)"
      icon="fa-exclamation-circle"
    />

    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="bg-base-100 border-primary-stroke rounded-lg border p-8 text-center shadow-sm"
    >
      <div class="border-primary mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-b-2"></div>
      <p class="text-secondary">
        {{ $t('admin.users.loading', 'Loading users...') }}
      </p>
    </div>

    <!-- Users Table -->
    <UsersTable
      v-else-if="users"
      :users="users.data"
      :has-filters="hasActiveFilters"
      @change-organization="showAssignModal"
      @manage-permissions="showPermissionsModal"
      @disable-user="showDisableModal"
      @enable-user="handleEnableUser"
      @reset-password="showResetPasswordModal"
      @clear-filters="clearFilters"
    />

    <!-- Pagination -->
    <Pagination
      v-model:current-page="currentPage"
      :meta="paginationMeta"
      item-name="users"
      @update-per-page="updatePageSize"
    />

    <!-- User Organization Assignment Modal -->
    <UserOrganizationModal
      v-if="userToAssign"
      :user-id="userToAssign.userId"
      :username="userToAssign.username"
      :organizations="availableOrganizations"
      :is-assigning="isAssigning"
      @confirm="handleAssignOrganization"
      @cancel="userToAssign = null"
    />

    <!-- Role Permissions Modal -->
    <RolePermissionsModal
      v-if="userToManagePermissions"
      :user-id="userToManagePermissions.userId"
      :username="userToManagePermissions.username"
      @confirm="handleUpdatePermissions"
      @close="userToManagePermissions = null"
    />

    <!-- Disable User Modal -->
    <DisableUserModal
      v-if="userToDisable"
      :user-id="userToDisable.userId"
      :username="userToDisable.username"
      :is-loading="isDisabling"
      @confirm="handleDisableUser"
      @close="userToDisable = null"
    />

    <!-- Reset Password Modal -->
    <ResetPasswordModal
      v-if="userToResetPassword"
      :user-id="userToResetPassword.userId"
      :username="userToResetPassword.username"
      @close="userToResetPassword = null"
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
import { useRouter } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { Alert, Button } from '@owlint/feathers-vue'
import Pagination from '@/components/ui/Pagination.vue'
import UserFilters from '@/components/admin/UserFilters.vue'
import UsersTable from '@/components/admin/UsersTable.vue'

import {
  useAssignUserOrganization,
  useUpdateUserPermissions,
  useDisableUser,
  useEnableUser,
} from '@/mutations/admin-users'
import { adminUsersQuery } from '@/queries/admin-users'
import { allOrganizationsQuery } from '@/queries/organization-admin'
import { transformToPaginationMeta } from '@/utils/pagination'
import type { AdminUserListItem, AdminUserQueryParams } from '@/types/admin-user'
import UserOrganizationModal from '@/components/admin/UserOrganizationModal.vue'
import RolePermissionsModal from '@/components/admin/RolePermissionsModal.vue'
import DisableUserModal from '@/components/admin/DisableUserModal.vue'
import ResetPasswordModal from '@/components/admin/ResetPasswordModal.vue'

const router = useRouter()

// Query parameters state
const queryParams = reactive<AdminUserQueryParams>({
  page: 1,
  limit: 10,
  sort: 'created_at',
  order: 'desc',
  search: '',
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
const { updatePermissions } = useUpdateUserPermissions()
const { disableUser, isLoading: isDisabling } = useDisableUser()
const { enableUser } = useEnableUser()

// Modal state - store only userId and username for on-demand loading
interface ModalUserState {
  userId: string
  username: string
}
const userToAssign = ref<ModalUserState | null>(null)
const userToManagePermissions = ref<ModalUserState | null>(null)
const userToDisable = ref<ModalUserState | null>(null)
const userToResetPassword = ref<ModalUserState | null>(null)

// Users data
const users = computed(() => usersResponse.value)

// Available organizations for filter dropdown
const availableOrganizations = computed(() => organizationsResponse.value?.data || [])

// Transform API pagination to PaginationMeta format
const paginationMeta = computed(() => transformToPaginationMeta(users.value?.pagination))

// Current page for v-model binding
const currentPage = computed({
  get: () => queryParams.page,
  set: (value: number) => {
    queryParams.page = value
  },
})

// Check if any filters are active
const hasActiveFilters = computed(() => {
  return queryParams.search !== ''
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
  queryParams.page = 1
}

// Actions
const showAssignModal = (user: AdminUserListItem) => {
  userToAssign.value = { userId: user.user_id, username: user.username }
}

const showPermissionsModal = (user: AdminUserListItem) => {
  userToManagePermissions.value = { userId: user.user_id, username: user.username }
}

const showDisableModal = (user: AdminUserListItem) => {
  userToDisable.value = { userId: user.user_id, username: user.username }
}

const showResetPasswordModal = (user: AdminUserListItem) => {
  userToResetPassword.value = { userId: user.user_id, username: user.username }
}

const handleAssignOrganization = async (organizationId: string) => {
  if (!userToAssign.value) return

  try {
    await assignOrganization({ userId: userToAssign.value.userId, organizationId })
    userToAssign.value = null
  } catch (error) {
    console.error('Failed to assign organization:', error)
  }
}

const handleUpdatePermissions = async (permissions: string[]) => {
  if (!userToManagePermissions.value) return

  try {
    await updatePermissions({ userId: userToManagePermissions.value.userId, permissions })
    userToManagePermissions.value = null
  } catch (error) {
    console.error('Failed to update permissions:', error)
  }
}

const handleDisableUser = async () => {
  if (!userToDisable.value) return

  try {
    await disableUser({ userId: userToDisable.value.userId })
    userToDisable.value = null
  } catch (error) {
    console.error('Failed to disable user:', error)
  }
}

const handleEnableUser = async (user: AdminUserListItem) => {
  try {
    await enableUser({ userId: user.user_id })
  } catch (error) {
    console.error('Failed to enable user:', error)
  }
}
</script>
