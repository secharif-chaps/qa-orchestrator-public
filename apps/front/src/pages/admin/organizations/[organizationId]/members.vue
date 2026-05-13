<template>
  <div class="flex flex-col gap-6">
    <!-- User Management Card -->
    <Card>
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h2 class="text-xl font-semibold">
            {{ $t('admin.organization.detail.members') }}
          </h2>
          <p class="text-neutral-black-font mt-1">
            {{ $t('admin.organization.membersDescription') }}
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            variant="secondary"
            icon="fa-file-import"
            :label="$t('admin.import.title')"
            @click="navigateToImport"
          />
          <Button
            variant="primary"
            icon="fa-user-plus"
            :label="$t('settings.user.create.button')"
            @click="showCreateUserModal = true"
          />
        </div>
      </div>

      <!-- Users Loading State -->
      <div v-if="usersLoading" class="p-8 text-center">
        <div class="border-primary mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-b-2"></div>
        <p class="text-neutral-black-font">
          {{ $t('settings.user.loading') }}
        </p>
      </div>

      <!-- Users Error State -->
      <Alert
        v-else-if="usersError"
        variant="danger"
        :title="$t('common.error')"
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
          @enable-user="handleEnableUser"
          @reset-password="showResetPasswordModal"
        />

        <!-- Empty Users State -->
        <div v-else class="p-8 text-center">
          <i class="fa fa-users text-neutral-black-font/50 mb-4 text-4xl"></i>
          <h3 class="mb-2 text-base text-lg font-medium">
            {{ $t('settings.user.empty.title') }}
          </h3>
          <p class="text-neutral-black-font mb-6">
            {{ $t('settings.user.empty.description') }}
          </p>
          <Button
            variant="primary"
            :label="$t('settings.user.create.button')"
            @click="showCreateUserModal = true"
          />
        </div>

        <!-- Pagination -->
        <Pagination
          v-if="users && users.length > 0 && paginationMeta"
          v-model:current-page="currentPage"
          :meta="paginationMeta"
          :item-name="$t('admin.organization.detail.members').toLowerCase()"
          class="mt-4"
          @update-per-page="updatePageSize"
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
      :user-email="userToManagePermissions.email"
      @confirm="handleUpdatePermissions"
      @close="userToManagePermissions = null"
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

<script setup lang="ts">
import { useConfirmModal } from '@/composables/useConfirmModal'
import { Alert, Button } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, inject, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

// Components
import ResetPasswordModal from '@/components/admin/ResetPasswordModal.vue'
import RolePermissionsModal from '@/components/admin/RolePermissionsModal.vue'
import UserOrganizationModal from '@/components/admin/UserOrganizationModal.vue'
import UsersTable from '@/components/admin/UsersTable.vue'
import Card from '@/components/ui/Card.vue'
import Pagination from '@/components/ui/Pagination.vue'
import CreateUserModal from '@/components/user/CreateUserModal.vue'

// Queries & Mutations
import {
  useAssignUserOrganization,
  useDisableUser,
  useEnableUser,
  useUpdateUserPermissions,
} from '@/mutations/admin-users'
import { useCreateOrganizationUser } from '@/mutations/user'
import { allOrganizationsQuery, organizationMembersQuery } from '@/queries/organization-admin'

// Types
import type { AdminUserListItem } from '@/types/admin-user'
import type { OrganizationAdminResponse } from '@/types/organization'
import type { OrganizationUserCreate } from '@/types/user'

// Utils
import { transformToPaginationMeta } from '@/utils/pagination'

const router = useRouter()
const { t } = useI18n()
const { showConfirmModal } = useConfirmModal()

// Inject organization ID and organization object from parent layout
const organizationId = inject<ReturnType<typeof computed<string>>>('organizationId')
const organization = inject<ReturnType<typeof computed<OrganizationAdminResponse>>>('organization')

// Navigate to import page (single import page with org pre-selected via query param)
function navigateToImport(): void {
  if (organizationId?.value) {
    router.push(`/admin/users/import?organizationId=${organizationId.value}`)
  }
}

// Query parameters state for members
const queryParams = reactive({
  page: 1,
  limit: 10,
  search: '',
})

// Query for organization members
const {
  data: usersResponse,
  isLoading: usersLoading,
  error: usersError,
} = useQuery({
  ...organizationMembersQuery({
    organizationId: organizationId?.value || '',
    organizationName: organization?.value?.name || '',
    page: queryParams.page,
    limit: queryParams.limit,
    search: queryParams.search || undefined,
  }),
  enabled: () => !!organizationId?.value && !!organization?.value,
})

// Query for all organizations (for the change organization modal)
const { data: organizationsResponse } = useQuery(() =>
  allOrganizationsQuery({
    page: 1,
    limit: 100,
    sort: 'name' as const,
    order: 'asc' as const,
    search: undefined,
  }),
)

const users = computed(() => usersResponse.value?.data || [])
const availableOrganizations = computed(() => organizationsResponse.value?.data || [])

// Transform API pagination to PaginationMeta format
const paginationMeta = computed(() => transformToPaginationMeta(usersResponse.value?.pagination))

// Extract error message safely
const errorMessage = computed(() => {
  const err = usersError.value
  if (!err) return ''
  if (typeof err === 'string') return err
  if (err instanceof Error) return err.message
  if (typeof err === 'object' && 'message' in err)
    return String((err as { message: unknown }).message)
  return 'An error occurred'
})

// Mutations
const { createUser, isLoading: isCreatingUser } = useCreateOrganizationUser(
  organizationId?.value || '',
)
const { assignOrganization, isLoading: isAssigning } = useAssignUserOrganization()
const { updatePermissions } = useUpdateUserPermissions()
const { disableUser, isLoading: isDisabling } = useDisableUser()
const { enableUser } = useEnableUser()

// Modal state - store userId, username, and email for lazy-loaded modals
interface ModalUserState {
  userId: string
  username: string
  email: string
}

const showCreateUserModal = ref(false)
const userToAssign = ref<ModalUserState | null>(null)
const userToManagePermissions = ref<ModalUserState | null>(null)
const userToResetPassword = ref<ModalUserState | null>(null)

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

// Actions - extract userId, username, and email for modal state
const showAssignModal = (user: AdminUserListItem) => {
  userToAssign.value = { userId: user.user_id, username: user.username, email: user.email }
}

const showPermissionsModal = (user: AdminUserListItem) => {
  userToManagePermissions.value = {
    userId: user.user_id,
    username: user.username,
    email: user.email,
  }
}

const showDisableModal = (user: AdminUserListItem) => {
  showConfirmModal({
    title: t('admin.disableUser.title'),
    titleIcon: 'fa-user-slash',
    message: t('admin.disableUser.confirmText', { username: user.username }),
    confirmLabel: t('admin.disableUser.confirm'),
    cancelLabel: t('admin.disableUser.cancel'),
    confirmVariant: 'accent',
    loading: isDisabling,
    warningSection: {
      title: t('admin.disableUser.actionTitle'),
      message: t('admin.disableUser.actionDescription'),
    },
    onConfirm: () => disableUser({ userId: user.user_id }),
  })
}

const showResetPasswordModal = (user: AdminUserListItem) => {
  userToResetPassword.value = { userId: user.user_id, username: user.username, email: user.email }
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
    await assignOrganization({
      userId: userToAssign.value.userId,
      organizationId: newOrganizationId,
    })
    userToAssign.value = null
  } catch (err) {
    console.error('Failed to assign organization:', err)
  }
}

const handleUpdatePermissions = async (permissions: string[]) => {
  if (!userToManagePermissions.value) return

  try {
    await updatePermissions({ userId: userToManagePermissions.value.userId, permissions })
    userToManagePermissions.value = null
  } catch (err) {
    console.error('Failed to update permissions:', err)
  }
}

const handleEnableUser = async (user: AdminUserListItem) => {
  try {
    await enableUser({ userId: user.user_id })
  } catch (err) {
    console.error('Failed to enable user:', err)
  }
}
</script>
