<template>
  <div class="flex flex-col gap-6">
    <!-- User Management Card -->
    <Card>
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-xl font-semibold">
            {{ $t('organization.detail.members', 'Members') }}
          </h2>
          <p class="text-secondary mt-1">
            {{ $t('organization.membersDescription', 'Manage users in this organization') }}
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            variant="secondary"
            icon="fa fa-file-import"
            :label="$t('admin.import.title', 'Import Users')"
            @click="navigateToImport"
          />
          <Button
            variant="primary"
            icon="fa fa-user-plus"
            :label="$t('user.create.button', 'Add User')"
            @click="showCreateUserModal = true"
          />
        </div>
      </div>

      <!-- Users Loading State -->
      <div v-if="usersLoading" class="text-center p-8">
        <div
          class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-secondary">
          {{ $t('user.loading', 'Loading users...') }}
        </p>
      </div>

      <!-- Users Error State -->
      <Alert
        v-else-if="usersError"
        variant="danger"
        title="Error"
        :description="errorMessage"
      />

      <!-- Users Table -->
      <template v-else>
        <UsersTable
          v-if="users && users.length > 0"
          :users="users"
          :has-filters="false"
          @change-organization="showAssignModal"
          @manage-permissions="showPermissionsModal"
          @disable-user="showDisableModal"
          @reset-password="showResetPasswordModal"
        />

        <!-- Empty Users State -->
        <div v-else class="text-center p-8">
          <i class="fa fa-users text-4xl text-secondary/50 mb-4"></i>
          <h3 class="text-lg font-medium text-base mb-2">
            {{ $t('user.empty.title', 'No users found') }}
          </h3>
          <p class="text-secondary mb-6">
            {{ $t('user.empty.description', 'Create your first user to get started') }}
          </p>
          <Button
            variant="primary"
            :label="$t('user.create.button', 'Add User')"
            @click="showCreateUserModal = true"
          />
        </div>

        <!-- Pagination -->
        <Pagination
          v-if="users && users.length > 0 && totalUsers > 0"
          v-model:current-page="currentPage"
          :items-per-pages="queryParams.limit"
          :total="totalUsers"
          class="mt-4"
          @update:items-per-pages="updatePageSize"
        />
      </template>
    </Card>

    <!-- Create User Modal -->
    <CreateUserModal
      v-if="showCreateUserModal"
      :is-loading="isCreatingUser"
      @confirm="handleCreateUser"
      @cancel="showCreateUserModal = false"
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

    <!-- Role Permissions Modal -->
    <RolePermissionsModal
      v-if="userToManagePermissions"
      :user="userToManagePermissions"
      @confirm="handleUpdatePermissions"
      @close="userToManagePermissions = null"
    />

    <!-- Disable User Modal -->
    <DisableUserModal
      v-if="userToDisable"
      :user="userToDisable"
      @close="userToDisable = null"
    />

    <!-- Reset Password Modal -->
    <ResetPasswordModal
      v-if="userToResetPassword"
      :user="userToResetPassword"
      @close="userToResetPassword = null"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, inject, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { Alert, Button, Pagination } from '@owlint/feathers-vue'

// Components
import Card from '@/components/ui/Card.vue'
import UsersTable from '@/components/admin/UsersTable.vue'
import CreateUserModal from '@/components/user/CreateUserModal.vue'
import UserOrganizationModal from '@/components/admin/UserOrganizationModal.vue'
import RolePermissionsModal from '@/components/admin/RolePermissionsModal.vue'
import DisableUserModal from '@/components/admin/DisableUserModal.vue'
import ResetPasswordModal from '@/components/admin/ResetPasswordModal.vue'

// Queries & Mutations
import { allOrganizationsQuery } from '@/queries/organization-admin'
import { adminUsersQuery } from '@/queries/admin-users'
import { useCreateOrganizationUser } from '@/mutations/user'
import { useAssignUserOrganization, useUpdateUserPermissions } from '@/mutations/admin-users'

// Types
import type { AdminUserResponse, AdminUserQueryParams } from '@/types/admin-user'
import type { OrganizationUserCreate } from '@/types/user'

const router = useRouter()

// Inject organization ID from parent layout
const organizationId = inject<ReturnType<typeof computed<string>>>('organizationId')

// Navigate to import page (single import page with org pre-selected via query param)
function navigateToImport(): void {
  if (organizationId?.value) {
    router.push(`/admin/users/import?organizationId=${organizationId.value}`)
  }
}

// Query parameters state for users
const queryParams = reactive<AdminUserQueryParams>({
  page: 1,
  limit: 10,
  sort: 'created_at',
  order: 'desc',
  search: '',
  organization_filter: organizationId?.value || '',
})

// Update organization_filter when organizationId changes
watch(() => organizationId?.value, (newId) => {
  if (newId) {
    queryParams.organization_filter = newId
    queryParams.page = 1
  }
})

// Query for organization users
const {
  data: usersResponse,
  isLoading: usersLoading,
  error: usersError,
} = useQuery(adminUsersQuery, () => ({ params: queryParams }), {
  enabled: () => !!organizationId?.value,
})

// Query for all organizations (for the change organization modal)
const { data: organizationsResponse } = useQuery(allOrganizationsQuery, () => ({
  page: 1,
  limit: 100,
  sort: 'name' as const,
  order: 'asc' as const,
  search: undefined,
}))

const users = computed(() => usersResponse.value?.data || [])
const totalUsers = computed(() => usersResponse.value?.pagination?.total ?? 0)
const availableOrganizations = computed(() => organizationsResponse.value?.data || [])

// Extract error message safely
const errorMessage = computed(() => {
  const err = usersError.value
  if (!err) return ''
  if (typeof err === 'string') return err
  if (err instanceof Error) return err.message
  if (typeof err === 'object' && 'message' in err) return String((err as { message: unknown }).message)
  return 'An error occurred'
})

// Mutations
const { createUser, isLoading: isCreatingUser } = useCreateOrganizationUser(organizationId?.value || '')
const { assignOrganization, isLoading: isAssigning } = useAssignUserOrganization()
const { updatePermissions } = useUpdateUserPermissions()

// Modal state
const showCreateUserModal = ref(false)
const userToAssign = ref<AdminUserResponse | null>(null)
const userToManagePermissions = ref<AdminUserResponse | null>(null)
const userToDisable = ref<AdminUserResponse | null>(null)
const userToResetPassword = ref<AdminUserResponse | null>(null)

// Pagination
const currentPage = computed({
  get: () => queryParams.page,
  set: (value: number) => {
    queryParams.page = value
  },
})

const updatePageSize = (limit: number) => {
  queryParams.limit = limit
  queryParams.page = 1
}

// Actions
const showAssignModal = (user: AdminUserResponse) => {
  userToAssign.value = user
}

const showPermissionsModal = (user: AdminUserResponse) => {
  userToManagePermissions.value = user
}

const showDisableModal = (user: AdminUserResponse) => {
  userToDisable.value = user
}

const showResetPasswordModal = (user: AdminUserResponse) => {
  userToResetPassword.value = user
}

const handleCreateUser = async (userData: OrganizationUserCreate) => {
  try {
    await createUser(userData)
    showCreateUserModal.value = false
  } catch (err) {
    console.error('Failed to add user:', err)
  }
}

const handleAssignOrganization = async (newOrganizationId: string) => {
  if (!userToAssign.value) return

  try {
    await assignOrganization({ userId: userToAssign.value.user_id, organizationId: newOrganizationId })
    userToAssign.value = null
  } catch (err) {
    console.error('Failed to assign organization:', err)
  }
}

const handleUpdatePermissions = async (permissions: string[]) => {
  if (!userToManagePermissions.value) return

  try {
    await updatePermissions({ userId: userToManagePermissions.value.user_id, permissions })
    userToManagePermissions.value = null
  } catch (err) {
    console.error('Failed to update permissions:', err)
  }
}
</script>
