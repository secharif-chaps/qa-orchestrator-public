<template>
  <div class="min-h-screen bg-bg3">
    <div class="container mx-auto px-4 py-8">
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

      <div
        v-if="isLoading"
        class="bg-bg1 rounded-lg shadow-sm p-8 text-center border border-border-2"
      >
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-secondary">{{ $t('team.loading', 'Loading users...') }}</p>
      </div>

      <div
        v-else-if="users"
        class="bg-bg1 rounded-lg shadow-sm overflow-hidden border border-border-2"
      >
        <div class="px-6 py-4 border-b border-border-2 bg-bg2">
          <div class="grid grid-cols-12 gap-4 text-sm font-medium text-secondary">
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

        <div
          v-if="pagination && pagination.totalPages > 1"
          class="px-6 py-4 border-t border-border-2 bg-bg2"
        >
          <div class="flex items-center justify-between">
            <div class="text-sm text-secondary">
              {{ $t('team.pagination.showing', 'Showing') }}
              <span class="font-medium">{{ (pagination.page - 1) * pagination.limit + 1 }}</span>
              {{ $t('team.pagination.to', 'to') }}
              <span class="font-medium">{{
                Math.min(pagination.page * pagination.limit, pagination.total)
              }}</span>
              {{ $t('team.pagination.of', 'of') }}
              <span class="font-medium">{{ pagination.total }}</span>
              {{ $t('team.pagination.results', 'results') }}
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="goToPage(pagination.page - 1)"
                :disabled="!pagination.hasPrev"
                class="px-3 py-2 text-sm border border-border-2 rounded-lg hover:bg-bg3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i class="fa fa-chevron-left mr-1"></i>
                {{ $t('team.pagination.previous', 'Previous') }}
              </button>

              <div class="flex items-center gap-1">
                <button
                  v-if="pagination.page > 3"
                  @click="goToPage(1)"
                  class="px-3 py-2 text-sm border border-border-2 rounded-lg hover:bg-bg3 transition-colors"
                >
                  1
                </button>
                <span v-if="pagination.page > 4" class="px-2 text-secondary">...</span>

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

              <button
                @click="goToPage(pagination.page + 1)"
                :disabled="!pagination.hasNext"
                class="px-3 py-2 text-sm border border-border-2 rounded-lg hover:bg-bg3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ $t('team.pagination.next', 'Next') }}
                <i class="fa fa-chevron-right ml-1"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

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
const pagination = computed(() => usersResponse.value?.pagination)

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

const goToPage = (page: number) => {
  queryParams.page = page
}

const clearSearch = () => {
  queryParams.search = ''
  queryParams.status = 'all'
  queryParams.page = 1
}

const getVisiblePages = (paginationInfo: NonNullable<typeof pagination.value>) => {
  const current = paginationInfo.page
  const total = paginationInfo.totalPages
  const pages: number[] = []

  const start = Math.max(1, current - 2)
  const end = Math.min(total, current + 2)

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  return pages
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
