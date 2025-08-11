<template>
  <div class="min-h-screen bg-bg3">
    <div class="container mx-auto px-4 py-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center gap-4 mb-4">
          <button
            @click="$router.push('/workspaces')"
            class="text-secondary hover:text-base transition-colors p-2"
          >
            <i class="fa fa-arrow-left"></i>
          </button>
          <div>
            <h1 class="text-3xl font-bold text-base">
              {{ workspace?.name || $t('workspace.detail.title', 'Workspace Details') }}
            </h1>
            <p class="text-secondary mt-2">
              {{
                workspace?.description ||
                $t('workspace.detail.description', 'Workspace information and settings')
              }}
            </p>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="bg-bg1 rounded-lg shadow-sm p-8 text-center">
        <div
          class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"
        ></div>
        <p class="text-secondary">{{ $t('workspace.loading', 'Loading workspace...') }}</p>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"
      >
        <div class="flex items-center gap-2">
          <i class="fa fa-exclamation-triangle"></i>
          <span class="font-medium">Error:</span>
          <span>{{ error.message }}</span>
        </div>
      </div>

      <!-- Workspace Details -->
      <div v-else-if="workspace" class="space-y-6">
        <!-- Basic Info Card -->
        <div class="bg-bg1 rounded-lg shadow-sm p-6">
          <h2 class="text-xl font-semibold mb-4">
            {{ $t('workspace.detail.basicInfo', 'Basic Information') }}
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-secondary mb-1">{{
                $t('workspace.name', 'Name')
              }}</label>
              <p class="text-base font-medium">{{ workspace.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-secondary mb-1">{{
                $t('workspace.slug', 'Slug')
              }}</label>
              <code class="text-sm bg-bg3 px-2 py-1 rounded">{{ workspace.slug }}</code>
            </div>
            <div class="md:col-span-2" v-if="workspace.description">
              <label class="block text-sm font-medium text-secondary mb-1">{{
                $t('workspace.description', 'Description')
              }}</label>
              <p class="text-base">{{ workspace.description }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-secondary mb-1">{{
                $t('workspace.created', 'Created')
              }}</label>
              <p class="text-base">{{ formatDate(workspace.created_at) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-secondary mb-1">{{
                $t('workspace.updated', 'Last Updated')
              }}</label>
              <p class="text-base">{{ formatDate(workspace.updated_at) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-secondary mb-1">{{
                $t('workspace.members', 'Members')
              }}</label>
              <div class="flex items-center gap-2">
                <span
                  class="inline-flex items-center justify-center w-8 h-8 bg-primary/10 text-primary rounded-full text-sm font-medium"
                >
                  {{ workspace.member_count }}
                </span>
                <span class="text-base">{{ workspace.member_count === 1 ? 'member' : 'members' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- User Management Section -->
        <div class="bg-bg1 rounded-lg shadow-sm p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold">
              {{ $t('workspace.detail.members', 'Members') }}
            </h2>
            <button
              @click="showCreateUserModal = true"
              class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/80 transition-colors flex items-center gap-2"
            >
              <i class="fa fa-user-plus"></i>
              {{ $t('user.create.button', 'Add User') }}
            </button>
          </div>

          <!-- Users Loading State -->
          <div v-if="usersLoading" class="text-center p-8">
            <div
              class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"
            ></div>
            <p class="text-secondary">{{ $t('user.loading', 'Loading users...') }}</p>
          </div>

          <!-- Users Error State -->
          <div
            v-else-if="usersError"
            class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"
          >
            <div class="flex items-center gap-2">
              <i class="fa fa-exclamation-triangle"></i>
              <span class="font-medium">Error:</span>
              <span>{{ usersError.message }}</span>
            </div>
          </div>

          <!-- Users List -->
          <div v-else-if="users && users.users.length > 0" class="space-y-3">
            <div
              v-for="user in usersWithDisplayInfo"
              :key="user.id"
              class="flex items-center justify-between p-4 bg-bg2 rounded-lg hover:bg-bg3/50 transition-colors"
            >
              <div class="flex items-center gap-3">
                <!-- User Avatar -->
                <div
                  class="w-10 h-10 bg-primary/10 text-primary rounded-full flex items-center justify-center font-medium"
                >
                  {{ user.initials }}
                </div>

                <!-- User Info -->
                <div>
                  <div class="font-medium">{{ user.displayName }}</div>
                  <div class="text-sm text-secondary">{{ user.email }}</div>
                  <div class="text-xs text-secondary">@{{ user.username }}</div>
                </div>
              </div>

              <!-- User Status & Actions -->
              <div class="flex items-center gap-2">
                <!-- Status Badge -->
                <Badge
                  :variant="getUserStatusVariant(user)"
                  :label="getUserStatusText(user)"
                  size="xs"
                />

                <!-- Actions Dropdown -->
                <div class="relative">
                  <button
                    @click="toggleUserActions(user.id)"
                    class="text-secondary hover:text-base transition-colors p-2"
                  >
                    <i class="fa fa-ellipsis-v"></i>
                  </button>

                  <div
                    v-if="activeUserActions === user.id"
                    class="absolute right-0 mt-2 w-48 bg-bg1 border border-border-2 rounded-lg shadow-lg z-10"
                  >
                    <button
                      @click="resendPasswordReset(user.id)"
                      class="w-full text-left px-4 py-2 text-sm hover:bg-bg2 transition-colors"
                    >
                      <i class="fa fa-key mr-2"></i>
                      {{ $t('user.actions.resetPassword', 'Reset Password') }}
                    </button>
                    <button
                      @click="toggleUserStatus(user.id, !user.enabled)"
                      class="w-full text-left px-4 py-2 text-sm hover:bg-bg2 transition-colors"
                    >
                      <i :class="user.enabled ? 'fa fa-ban' : 'fa fa-check'" class="mr-2"></i>
                      {{
                        user.enabled
                          ? $t('user.actions.disable', 'Disable User')
                          : $t('user.actions.enable', 'Enable User')
                      }}
                    </button>
                    <hr class="border-border-2" />
                    <button
                      @click="confirmDeleteUser(user)"
                      class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
                    >
                      <i class="fa fa-trash mr-2"></i>
                      {{ $t('user.actions.delete', 'Remove User') }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty Users State -->
          <div v-else class="text-center p-8">
            <i class="fa fa-users text-4xl text-secondary/50 mb-4"></i>
            <h3 class="text-lg font-medium text-base mb-2">
              {{ $t('user.empty.title', 'No users found') }}
            </h3>
            <p class="text-secondary mb-6">
              {{ $t('user.empty.description', 'Create your first user to get started') }}
            </p>
            <button
              @click="showCreateUserModal = true"
              class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/80 transition-colors"
            >
              {{ $t('user.create.button', 'Add User') }}
            </button>
          </div>
        </div>

        <!-- Token Management Section -->
        <WorkspaceTokensManager :workspace-id="workspaceId" />

        <div class="bg-bg1 rounded-lg shadow-sm p-6">
          <h2 class="text-xl font-semibold mb-4">
            {{ $t('workspace.detail.settings', 'Settings') }}
          </h2>
          <div class="text-center p-8 text-secondary">
            <i class="fa fa-cog text-4xl mb-4 opacity-50"></i>
            <p>
              {{
                $t(
                  'workspace.detail.settingsPlaceholder',
                  'Workspace settings will be implemented here',
                )
              }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- add User Modal -->
    <CreateUserModal
      v-if="showCreateUserModal"
      :is-loading="isCreatingUser"
      @confirm="handleCreateUser"
      @cancel="showCreateUserModal = false"
    />

    <!-- Delete User Confirmation -->
    <div
      v-if="userToDelete"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
    >
      <div class="bg-bg1 rounded-lg shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-border-2">
          <h2 class="text-xl font-semibold">
            {{ $t('user.delete.title', 'Remove User') }}
          </h2>
        </div>
        <div class="px-6 py-4">
          <p class="text-secondary mb-4">
            {{
              $t(
                'user.delete.description',
                'Are you sure you want to remove this user from the workspace?',
              )
            }}
          </p>
          <div class="bg-bg2 p-3 rounded-lg">
            <div class="font-medium">{{ userToDelete.displayName }}</div>
            <div class="text-sm text-secondary">{{ userToDelete.email }}</div>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-border-2 flex justify-end gap-3">
          <button
            @click="userToDelete = null"
            :disabled="isDeletingUser"
            class="px-4 py-2 text-secondary hover:text-base transition-colors disabled:opacity-50"
          >
            {{ $t('common.cancel', 'Cancel') }}
          </button>
          <button
            @click="handleDeleteUser"
            :disabled="isDeletingUser"
            class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 flex items-center gap-2"
          >
            <div
              v-if="isDeletingUser"
              class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"
            ></div>
            <i v-else class="fa fa-trash"></i>
            {{ $t('user.delete.button', 'Remove User') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workspaces
</route>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { useI18n } from 'vue-i18n'
import { workspaceDetailsQuery } from '@/queries/workspace'
import { workspaceUsersQuery } from '@/queries/user'
import {
  useCreateWorkspaceUser,
  useDeleteWorkspaceUser,
  useToggleUserStatus,
  useResendPasswordReset,
} from '@/mutations/user'
import type { WorkspaceUserCreate, WorkspaceUserListItem } from '@/types/user'
import CreateUserModal from '@/components/user/CreateUserModal.vue'
import WorkspaceTokensManager from '@/components/tokens/WorkspaceTokensManager.vue'
import Badge from '@/components/ui/Badge.vue'

const route = useRoute()
const { t } = useI18n()

const workspaceId = computed(() => parseInt(route.params.workspaceId as string))

// Query for workspace details
const { data: workspace, isLoading, error } = useQuery(workspaceDetailsQuery, () => ({ id: workspaceId.value }), {
  enabled: computed(() => !isNaN(workspaceId.value)),
})

// Query for workspace users
const {
  data: users,
  isLoading: usersLoading,
  error: usersError,
} = useQuery(workspaceUsersQuery, () => ({ workspaceId: workspaceId.value }), {
  enabled: computed(() => !isNaN(workspaceId.value)),
})

// User mutations
const { createUser, isLoading: isCreatingUser } = useCreateWorkspaceUser(workspaceId.value)
const { deleteUser, isLoading: isDeletingUser } = useDeleteWorkspaceUser(workspaceId.value)
const { toggleStatus } = useToggleUserStatus(workspaceId.value)
const { resendReset } = useResendPasswordReset(workspaceId.value)

// UI state
const showCreateUserModal = ref(false)
const userToDelete = ref<WorkspaceUserListItem | null>(null)
const activeUserActions = ref<string | null>(null)

// Computed properties
const usersWithDisplayInfo = computed<WorkspaceUserListItem[]>(() => {
  if (!users.value?.users) return []

  return users.value.users.map((user) => ({
    ...user,
    displayName:
      user.firstName && user.lastName ? `${user.firstName} ${user.lastName}` : user.username,
    initials:
      user.firstName && user.lastName
        ? `${user.firstName[0]}${user.lastName[0]}`.toUpperCase()
        : user.username.slice(0, 2).toUpperCase(),
  }))
})

// Format date helper
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// User status helpers
const getUserStatusVariant = (user: WorkspaceUserListItem) => {
  if (!user.enabled) return 'error'
  if (!user.emailVerified) return 'warning'
  return 'success'
}

const getUserStatusText = (user: WorkspaceUserListItem) => {
  if (!user.enabled) return t('user.status.disabled', 'Disabled')
  if (!user.emailVerified) return t('user.status.pending', 'Pending')
  return t('user.status.active', 'Active')
}

// User management actions
const handleCreateUser = async (userData: WorkspaceUserCreate) => {
  try {
    await createUser(userData)
    showCreateUserModal.value = false
  } catch (error) {
    console.error('Failed to add user:', error)
  }
}

const confirmDeleteUser = (user: WorkspaceUserListItem) => {
  userToDelete.value = user
  activeUserActions.value = null
}

const handleDeleteUser = async () => {
  if (!userToDelete.value) return

  try {
    await deleteUser(userToDelete.value.id)
    userToDelete.value = null
  } catch (error) {
    console.error('Failed to delete user:', error)
  }
}

const toggleUserActions = (userId: string) => {
  activeUserActions.value = activeUserActions.value === userId ? null : userId
}

const toggleUserStatus = async (userId: string, enabled: boolean) => {
  try {
    await toggleStatus(userId, enabled)
    activeUserActions.value = null
  } catch (error) {
    console.error('Failed to toggle user status:', error)
  }
}

const resendPasswordReset = async (userId: string) => {
  try {
    await resendReset(userId)
    activeUserActions.value = null
  } catch (error) {
    console.error('Failed to resend password reset:', error)
  }
}

// Close user actions dropdown when clicking outside
const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as Element
  if (!target.closest('.relative')) {
    activeUserActions.value = null
  }
}

// Add click outside listener
import { onMounted, onUnmounted } from 'vue'

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>
