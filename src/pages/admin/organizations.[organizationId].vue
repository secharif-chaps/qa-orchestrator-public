<template>
  <div class="flex flex-col gap-6">
    <!-- Loading State -->
    <div v-if="isLoading" class="bg-base-100 rounded-lg shadow-sm p-8 text-center">
      <div
        class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
      ></div>
      <p class="text-secondary">
        {{ $t('organization.loading', 'Loading organization...') }}
      </p>
    </div>

    <!-- Error State -->
    <Alert
      v-else-if="error"
      variant="error"
      title="Error"
      :message="error.message"
    />

    <!-- Organization Details -->
    <template v-else-if="organization">
      <!-- Basic Info Card -->
      <Card>
        <h2 class="text-xl font-semibold mb-4">
          {{ $t('organization.detail.basicInfo', 'Basic Information') }}
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-secondary mb-1">{{
              $t('organization.name', 'Name')
            }}</label>
            <p class="text-base font-medium">{{ organization.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-secondary mb-1">{{
              $t('organization.id', 'ID')
            }}</label>
            <code class="text-sm bg-base-300 px-2 py-1 rounded">{{ organization.id }}</code>
          </div>
          <div class="md:col-span-2" v-if="organization.description">
            <label class="block text-sm font-medium text-secondary mb-1">{{
              $t('organization.description', 'Description')
            }}</label>
            <p class="text-base">{{ organization.description }}</p>
          </div>
          <div v-if="organization.created_at">
            <label class="block text-sm font-medium text-secondary mb-1">{{
              $t('organization.created', 'Created')
            }}</label>
            <p class="text-base">{{ formatDate(organization.created_at) }}</p>
          </div>
          <div v-if="organization.updated_at">
            <label class="block text-sm font-medium text-secondary mb-1">{{
              $t('organization.updated', 'Last Updated')
            }}</label>
            <p class="text-base">{{ formatDate(organization.updated_at) }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-secondary mb-1">{{
              $t('organization.members', 'Members')
            }}</label>
            <div class="flex items-center gap-2">
              <span
                class="inline-flex items-center justify-center w-8 h-8 bg-primary/10 text-secondary rounded-full text-sm font-medium"
              >
                {{ usersResponse?.pagination?.total || 0 }}
              </span>
              <span class="text-base">{{ (usersResponse?.pagination?.total || 0) === 1 ? 'member' : 'members' }}</span>
            </div>
          </div>
        </div>
      </Card>

      <!-- User Management Section -->
      <Card>
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold">
            {{ $t('organization.detail.members', 'Members') }}
          </h2>
          <Button
            variant="primary"
            icon="fa fa-user-plus"
            :label="$t('user.create.button', 'Add User')"
            @click="showCreateUserModal = true"
          />
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
          variant="error"
          title="Error"
          :message="usersError.message"
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
            v-if="users && users.length > 0"
            v-model:current-page="currentPage"
            :meta="paginationMeta"
            :page-size-options="pageSizeOptions"
            item-name="users"
            class="mt-4"
            @update-per-page="updatePageSize"
          />
        </template>
      </Card>

      <!-- Token Management Section -->
      <OrganizationTokensManager :organization-id="organizationId" />
    </template>

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

<route lang="yaml">
meta:
  permissions:
    - admin.organizations
</route>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useQuery } from '@pinia/colada'
import { useRoute } from 'vue-router'

// Components
import Alert from '@/components/ui/Alert.vue'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Pagination from '@/components/ui/Pagination.vue'
import UsersTable from '@/components/admin/UsersTable.vue'
import OrganizationTokensManager from '@/components/tokens/OrganizationTokensManager.vue'
import CreateUserModal from '@/components/user/CreateUserModal.vue'
import UserOrganizationModal from '@/components/admin/UserOrganizationModal.vue'
import RolePermissionsModal from '@/components/admin/RolePermissionsModal.vue'
import DisableUserModal from '@/components/admin/DisableUserModal.vue'
import ResetPasswordModal from '@/components/admin/ResetPasswordModal.vue'

// Queries & Mutations
import { organizationByIdQuery, allOrganizationsQuery } from '@/queries/organization-admin'
import { adminUsersQuery } from '@/queries/admin-users'
import { useCreateOrganizationUser } from '@/mutations/user'
import { useAssignUserOrganization, useUpdateUserPermissions } from '@/mutations/admin-users'

// Types
import type { AdminUserResponse, AdminUserQueryParams } from '@/types/admin-user'
import type { OrganizationUserCreate } from '@/types/user'
import type { PaginationMeta } from '@/types/pagination'

const route = useRoute()

const organizationId = computed(() => route.params.organizationId as string)

// Query parameters state for users
const queryParams = reactive<AdminUserQueryParams>({
  page: 1,
  limit: 10,
  sort: 'created_at',
  order: 'desc',
  search: '',
  organization_filter: organizationId.value, // Filter by current organization
})

// Update organization_filter when organizationId changes
import { watch } from 'vue'
watch(organizationId, (newId) => {
  queryParams.organization_filter = newId
  queryParams.page = 1
})

// Query for organization details
const {
  data: organization,
  isLoading,
  error,
} = useQuery(organizationByIdQuery, () => ({ id: organizationId.value }), {
  enabled: computed(() => !!organizationId.value),
})

// Query for organization users using admin users endpoint with organization filter
const {
  data: usersResponse,
  isLoading: usersLoading,
  error: usersError,
} = useQuery(adminUsersQuery, () => ({ params: queryParams }), {
  enabled: computed(() => !!organizationId.value),
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
const availableOrganizations = computed(() => organizationsResponse.value?.data || [])

// Mutations
const { createUser, isLoading: isCreatingUser } = useCreateOrganizationUser(organizationId.value)
const { assignOrganization, isLoading: isAssigning } = useAssignUserOrganization()
const { updatePermissions } = useUpdateUserPermissions()

// Modal state
const showCreateUserModal = ref(false)
const userToAssign = ref<AdminUserResponse | null>(null)
const userToManagePermissions = ref<AdminUserResponse | null>(null)
const userToDisable = ref<AdminUserResponse | null>(null)
const userToResetPassword = ref<AdminUserResponse | null>(null)

// Pagination
const paginationMeta = computed<PaginationMeta | null>(() => {
  if (!usersResponse.value?.pagination) return null

  const p = usersResponse.value.pagination
  const currentPage = p.page ?? p.current_page ?? 1
  const perPage = p.limit ?? p.per_page ?? 10
  const total = p.total ?? 0
  const lastPage = p.total_pages ?? p.last_page ?? 1

  return {
    current_page: currentPage,
    per_page: perPage,
    total,
    last_page: lastPage,
    from: (currentPage - 1) * perPage + 1,
    to: Math.min(currentPage * perPage, total),
  }
})

const currentPage = computed({
  get: () => queryParams.page,
  set: (value: number) => {
    queryParams.page = value
  },
})

const pageSizeOptions = [10, 20, 50, 100]

function updatePageSize(limit: number) {
  queryParams.limit = limit
  queryParams.page = 1
}

// Format date helper
function formatDate(dateString: string) {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Actions
function showAssignModal(user: AdminUserResponse) {
  userToAssign.value = user
}

function showPermissionsModal(user: AdminUserResponse) {
  userToManagePermissions.value = user
}

function showDisableModal(user: AdminUserResponse) {
  userToDisable.value = user
}

function showResetPasswordModal(user: AdminUserResponse) {
  userToResetPassword.value = user
}

async function handleCreateUser(userData: OrganizationUserCreate) {
  try {
    await createUser(userData)
    showCreateUserModal.value = false
  } catch (err) {
    console.error('Failed to add user:', err)
  }
}

async function handleAssignOrganization(newOrganizationId: string) {
  if (!userToAssign.value) return

  try {
    await assignOrganization({ userId: userToAssign.value.user_id, organizationId: newOrganizationId })
    userToAssign.value = null
    // User will be removed from the list since they're now in a different organization
  } catch (err) {
    console.error('Failed to assign organization:', err)
  }
}

async function handleUpdatePermissions(permissions: string[]) {
  if (!userToManagePermissions.value) return

  try {
    await updatePermissions({ userId: userToManagePermissions.value.user_id, permissions })
    userToManagePermissions.value = null
  } catch (err) {
    console.error('Failed to update permissions:', err)
  }
}
</script>
