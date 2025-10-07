<template>
  <!-- Team Users Tab -->
  <div class="flex flex-col gap-4">
    <TeamHeader
      :search="queryParams.search"
      :sort="queryParams.sort"
      :order="queryParams.order"
      :status="queryParams.status"
      :page-size="queryParams.limit"
      @create-user="showCreateModal = true"
      @update:search="updateSearch"
      @update:sort="updateSort"
      @toggle-order="toggleOrder"
      @update:status="updateStatus"
      @update:page-size="updatePageSize"
    />

    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
      <div class="flex items-center gap-2">
        <i class="fa fa-exclamation-triangle"></i>
        <span class="font-medium">Error:</span>
        <span>{{ error.message }}</span>
      </div>
    </div>

    <div v-if="isLoading" class="bg-base-100 rounded-lg p-8 text-center border border-primary-stroke">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
      <p class="text-primary-light-content">{{ $t('team.loading', 'Loading users...') }}</p>
    </div>

    <div v-else-if="users" class="bg-base-100 rounded-lg overflow-hidden border border-primary-stroke">
      <div class="px-6 py-4 border-b border-primary-stroke bg-base-200">
        <div class="grid grid-cols-12 gap-4 text-sm font-medium text-primary-light-content">
          <div class="col-span-4">{{ $t('team.table.user', 'User') }}</div>
          <div class="col-span-3">{{ $t('team.table.permissions', 'Permissions') }}</div>
          <div class="col-span-2">{{ $t('team.table.created', 'Created') }}</div>
          <div class="col-span-2">{{ $t('team.table.status', 'Status') }}</div>
          <div class="col-span-1">{{ $t('team.table.actions', 'Actions') }}</div>
        </div>
      </div>

      <div class="divide-y divide-border-2">
        <TeamUserItem
          v-for="user in usersWithDisplayName"
          :key="user.id"
          :user="user"
          @edit-user="editUser"
          @disable-user="disableUser"
          @enable-user="enableUser"
        />
      </div>

      <TeamEmptyState
        v-if="users.length === 0 && !isLoading"
        :type="queryParams.search || queryParams.status !== 'all' ? 'no-results' : 'no-users'"
        :has-search="!!queryParams.search"
        @create-user="showCreateModal = true"
        @clear-search="clearSearch"
      />
    </div>

    <Pagination
      v-model:current-page="currentPage"
      :meta="paginationMeta"
      :page-size-options="pageSizeOptions"
      item-name="users"
      @update-per-page="updatePageSize"
    />

    <TeamUserModal
      v-if="showCreateModal || editingUser"
      :user="editingUser"
      :is-loading="createMutation.isLoading.value || updateMutation.isLoading.value"
      @create-user="handleCreateUser"
      @update-user="handleUpdateUser"
      @cancel="closeModal"
    />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - workspace.read
</route>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { useQuery } from '@pinia/colada'
import { workspaceUsersQuery } from '@/queries/team'
import {
  useCreateWorkspaceUser,
  useUpdateWorkspaceUser,
  useToggleWorkspaceUser,
} from '@/mutations/team'
import type {
  WorkspaceUser,
  WorkspaceUserQueryParams,
  WorkspaceUserListItem,
  CreateWorkspaceUserRequest,
  UpdateWorkspaceUserRequest,
} from '@/types/team'
import TeamHeader from '@/components/team/TeamHeader.vue'
import TeamUserItem from '@/components/team/TeamUserItem.vue'
import TeamUserModal from '@/components/team/TeamUserModal.vue'
import TeamEmptyState from '@/components/team/TeamEmptyState.vue'
import Pagination from '@/components/ui/Pagination.vue'

const queryParams = reactive<WorkspaceUserQueryParams>({
  page: 1,
  limit: 20,
  sort: 'created_at',
  order: 'desc',
  search: '',
  status: 'active',
})

const { data: usersResponse, isLoading, error } = useQuery(workspaceUsersQuery, () => queryParams)

const createMutation = useCreateWorkspaceUser()
const updateMutation = useUpdateWorkspaceUser()
const toggleMutation = useToggleWorkspaceUser()

const showCreateModal = ref(false)
const editingUser = ref<WorkspaceUser | null>(null)

const users = computed(() => usersResponse.value?.data || [])
const paginationMeta = computed(() => usersResponse.value?.meta)

// Current page for v-model binding
const currentPage = computed({
  get: () => queryParams.page,
  set: (value: number) => {
    queryParams.page = value
  },
})

// Page size options
const pageSizeOptions = [10, 20, 50, 100]

const usersWithDisplayName = computed<WorkspaceUserListItem[]>(() => {
  return users.value.map((user) => ({
    ...user,
    display_name: `${user.first_name} ${user.last_name}`.trim() || user.username,
  }))
})

const updateSearch = (search: string) => {
  queryParams.search = search
  queryParams.page = 1
}

const updateSort = (sort: WorkspaceUserQueryParams['sort']) => {
  queryParams.sort = sort
  queryParams.page = 1
}

const toggleOrder = () => {
  queryParams.order = queryParams.order === 'asc' ? 'desc' : 'asc'
  queryParams.page = 1
}

const updateStatus = (status: WorkspaceUserQueryParams['status']) => {
  queryParams.status = status
  queryParams.page = 1
}

const updatePageSize = (limit: number) => {
  queryParams.limit = limit
  queryParams.page = 1
}

const clearSearch = () => {
  queryParams.search = ''
  queryParams.status = 'all'
  queryParams.page = 1
}

const editUser = (user: WorkspaceUser) => {
  editingUser.value = user
}

const closeModal = () => {
  showCreateModal.value = false
  editingUser.value = null
}

const handleCreateUser = async (userData: CreateWorkspaceUserRequest) => {
  try {
    await createMutation.createUser()
    closeModal()
  } catch (error) {
    console.error('Failed to create user:', error)
  }
}

const handleUpdateUser = async ({
  userId,
  updates,
}: {
  userId: number
  updates: UpdateWorkspaceUserRequest
}) => {
  try {
    await updateMutation.updateUser({ userId, updates })
    closeModal()
  } catch (error) {
    console.error('Failed to update user:', error)
  }
}

const disableUser = async (userId: number) => {
  try {
    await toggleMutation.disableUser(userId)
  } catch (error) {
    console.error('Failed to disable user:', error)
  }
}

const enableUser = async (userId: number) => {
  try {
    await toggleMutation.enableUser(userId)
  } catch (error) {
    console.error('Failed to enable user:', error)
  }
}
</script>
