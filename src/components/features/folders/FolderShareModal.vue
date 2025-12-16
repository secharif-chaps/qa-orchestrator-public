<template>
  <Modal
    v-model:displayModal="isOpen"
    :title="$t('folder.share.title', 'Share Folder')"
    icon="fa-users"
    size="2xl"
    @close="handleClose"
  >
    <template #description>
      {{ $t('folder.share.description', 'Share this folder with other users in your organization') }}

    <div class="flex flex-col gap-6 mt-4">
      <!-- User Search Section -->
      <div class="flex flex-col gap-3">
        <Label id="user-search">
          {{ $t('folder.share.searchLabel', 'Add people') }}
        </Label>

        <div class="flex gap-2">
          <div class="flex-1">
            <Searchbar
              id="user-search-input"
              v-model="searchQuery"
              :placeholder="$t('folder.share.searchPlaceholder', 'Search by username or email...')"
              @keydown.enter="handleAddUser"
            />
          </div>
        </div>

        <!-- Search Results Dropdown -->
        <div
          v-if="searchQuery.length >= 2 && ((searchResults?.length ?? 0) > 0 || isSearching)"
          class="relative"
        >
          <div
            class="absolute top-0 left-0 right-0 z-10 bg-base-100 border border-primary-stroke rounded-lg shadow-lg max-h-60 overflow-y-auto"
          >
            <!-- Loading state -->
            <div v-if="isSearching" class="p-4 text-center text-secondary">
              <i class="fa fa-spinner fa-spin mr-2"></i>
              {{ $t('folder.share.searching', 'Searching...') }}
            </div>

            <!-- Results -->
            <div v-else-if="(searchResults?.length ?? 0) > 0" class="py-2">
              <button
                v-for="user in searchResults"
                :key="user.user_id"
                type="button"
                class="w-full px-4 py-2 text-left hover:bg-base-200 transition-colors flex items-center justify-between"
                :class="{ 'opacity-50': isUserAlreadyShared(user.user_id) }"
                :disabled="isUserAlreadyShared(user.user_id)"
                @click="selectUser(user)"
              >
                <div class="flex items-center gap-3">
                  <Avatar :label="user.username" color="sage" size="sm" />
                  <div>
                    <div class="font-medium">{{ user.username }}</div>
                    <div v-if="user.email" class="text-xs text-secondary">{{ user.email }}</div>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <Tag
                    v-if="!user.has_write_permission"
                    :label="$t('folder.share.readOnly', 'Read only')"
                    variant="secondary"
                    size="xs"
                  />
                  <Tag
                    v-if="isUserAlreadyShared(user.user_id)"
                    :label="$t('folder.share.alreadyShared', 'Already shared')"
                    intent="info"
                    size="xs"
                  />
                </div>
              </button>
            </div>

            <!-- No results -->
            <div v-else-if="!searchError" class="p-4 text-center text-secondary">
              {{ $t('folder.share.noResults', 'No users found') }}
            </div>
          </div>
        </div>

        <!-- Search Error Display -->
        <div
          v-if="searchError"
          class="p-3 bg-error-light text-error-light-content border border-error-stroke rounded-lg text-sm"
        >
          <i class="fa fa-exclamation-triangle mr-2"></i>
          {{ $t('folder.share.searchError', 'Failed to search users. You may not have permission to share folders.') }}
        </div>

        <!-- Selected User (pending add) -->
        <div
          v-if="selectedUser"
          class="p-4 bg-base-100 rounded-lg border border-primary-stroke"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <Avatar :label="selectedUser.username" color="sage" size="sm" />
              <div>
                <div class="font-medium">{{ selectedUser.username }}</div>
                <div v-if="selectedUser.email" class="text-xs text-secondary">
                  {{ selectedUser.email }}
                </div>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <!-- Role Selection with Toggle -->
              <Toggle
                v-model="selectedRole"
                variant="pill"
                :options="getRoleOptionsForUser(selectedUser)"
              />

              <Button
                variant="primary"
                size="sm"
                icon="fa fa-plus"
                :label="$t('folder.share.add', 'Add')"
                :loading="isAddingShare"
                @click="handleAddUser"
              />

              <Button
                variant="tertiary"
                size="sm"
                icon="fa fa-times"
                icon-only
                :title="$t('common.cancel', 'Cancel')"
                @click="clearSelectedUser"
              />
            </div>
          </div>

          <!-- Writer disabled explanation -->
          <div
            v-if="!selectedUser.has_write_permission"
            class="mt-2 text-xs text-secondary flex items-center gap-1"
          >
            <i class="fa fa-info-circle"></i>
            {{
              $t(
                'folder.share.writerDisabledNote',
                'Writer role is disabled because this user only has read permissions in the organization.',
              )
            }}
          </div>
        </div>
      </div>

      <!-- Current Shares Section -->
      <div class="flex flex-col gap-3">
        <Label id="current-shares">
          {{ $t('folder.share.currentShares', 'People with access') }}
        </Label>

        <!-- Loading shares -->
        <div v-if="isLoadingShares" class="py-4 text-center text-secondary">
          <i class="fa fa-spinner fa-spin mr-2"></i>
          {{ $t('folder.share.loadingShares', 'Loading...') }}
        </div>

        <!-- Shares list -->
        <div
          v-else-if="shares && shares.length > 0"
          class="border border-primary-stroke rounded-lg divide-y divide-primary-stroke"
        >
          <div
            v-for="share in shares"
            :key="share.user_id"
            class="p-3 flex items-center justify-between"
          >
            <div class="flex items-center gap-3">
              <Avatar :label="share.user_username" color="sage" size="sm" />
              <div>
                <div class="font-medium">{{ share.user_username }}</div>
                <div class="text-xs text-secondary">
                  {{ $t('folder.share.addedOn', 'Added') }}
                  {{ formatDate(share.created_at) }}
                </div>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <!-- Role Toggle -->
              <Toggle
                :model-value="share.role"
                variant="pill"
                :options="getRoleOptionsForShare(share)"
                @update:model-value="(value: ShareRole) => updateShareRole(share, value)"
              />

              <!-- Remove Button -->
              <Button
                variant="tertiary"
                color="danger"
                size="sm"
                icon="fa fa-trash"
                icon-only
                :title="$t('folder.share.remove', 'Remove access')"
                :loading="removingShareUserId === share.user_id"
                @click="handleRemoveShare(share)"
              />
            </div>
          </div>
        </div>

        <!-- No shares yet -->
        <div v-else class="py-6 text-center text-secondary bg-base-200 rounded-lg">
          <i class="fa fa-user-friends text-2xl mb-2 opacity-50"></i>
          <p>{{ $t('folder.share.noShares', 'This folder is not shared with anyone yet') }}</p>
        </div>
      </div>
    </div>
    </template>

    <template #footer>
      <Button
        variant="secondary"
        :label="$t('common.close', 'Close')"
        @click="handleClose"
      />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { Avatar, Button, Label, Modal, Searchbar, Tag, Toggle } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { useI18n } from 'vue-i18n'
import { folderSharesQuery, userSearchQuery } from '@/queries/folderShares'
import {
  useCreateFolderShare,
  useDeleteFolderShare,
  useUpdateFolderShare,
} from '@/mutations/folderShares'
import type { FolderShare, ShareableUser, ShareRole } from '@/types/folder'

interface Props {
  folderId: string
}

const props = defineProps<Props>()

const isOpen = defineModel<boolean>({ required: true })

const { t, locale } = useI18n()

// Get role options with disabled property based on user's write permission
function getRoleOptionsForUser(user: ShareableUser) {
  return [
    { value: 'reader', label: t('folder.share.reader', 'Reader'), disabled: false },
    { value: 'writer', label: t('folder.share.writer', 'Writer'), disabled: !user.has_write_permission },
  ]
}

// Get role options for existing share based on user's write permission
function getRoleOptionsForShare(share: FolderShare) {
  return [
    { value: 'reader', label: t('folder.share.reader', 'Reader'), disabled: false },
    { value: 'writer', label: t('folder.share.writer', 'Writer'), disabled: !share.has_write_permission },
  ]
}

// Search state
const searchQuery = ref('')
const selectedUser = ref<ShareableUser | null>(null)
const selectedRole = ref<ShareRole>('reader')
const removingShareUserId = ref<string | null>(null)

// Debounced search query for API calls
const debouncedSearchQuery = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

watch(searchQuery, (newQuery) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    debouncedSearchQuery.value = newQuery
  }, 300)
})

// Fetch current shares
const {
  data: shares,
  isLoading: isLoadingShares,
} = useQuery(
  folderSharesQuery,
  () => ({ folderId: props.folderId }),
  { enabled: () => isOpen.value && !!props.folderId },
)

// Search users
const {
  data: searchResults,
  isLoading: isSearching,
  error: searchError,
} = useQuery(
  userSearchQuery,
  () => ({ query: debouncedSearchQuery.value }),
  { enabled: () => isOpen.value && debouncedSearchQuery.value.length >= 2 },
)

// Mutations
const { createShare, isLoading: isAddingShare } = useCreateFolderShare()
const { deleteShare } = useDeleteFolderShare()
const { updateShare } = useUpdateFolderShare()

// Check if user is already shared with
function isUserAlreadyShared(userId: string): boolean {
  if (!shares.value) return false
  return shares.value.some((share) => share.user_id === userId)
}

// Select a user from search results
function selectUser(user: ShareableUser) {
  if (isUserAlreadyShared(user.user_id)) return

  selectedUser.value = user
  // Default to reader if user doesn't have write permission
  selectedRole.value = user.has_write_permission ? 'reader' : 'reader'
  searchQuery.value = ''
  debouncedSearchQuery.value = ''
}

// Clear selected user
function clearSelectedUser() {
  selectedUser.value = null
  selectedRole.value = 'reader'
}

// Add the selected user as a share
async function handleAddUser() {
  if (!selectedUser.value) return

  try {
    await createShare({
      folderId: props.folderId,
      share: {
        user_id: selectedUser.value.user_id,
        user_username: selectedUser.value.username,
        role: selectedRole.value,
      },
    })
    clearSelectedUser()
  } catch {
    // Error handling is done in mutation
  }
}

// Remove a share
async function handleRemoveShare(share: FolderShare) {
  removingShareUserId.value = share.user_id
  try {
    await deleteShare({
      folderId: props.folderId,
      shareUserId: share.user_id,
    })
  } finally {
    removingShareUserId.value = null
  }
}

// Update share role
async function updateShareRole(share: FolderShare, newRole: ShareRole) {
  if (share.role === newRole) return

  await updateShare({
    folderId: props.folderId,
    shareUserId: share.user_id,
    update: { role: newRole },
  })
}

// Format date
function formatDate(dateString: string): string {
  if (!dateString) return ''
  const localeCode = locale.value === 'fr-FR' ? 'fr-FR' : 'en-US'
  return new Date(dateString).toLocaleDateString(localeCode)
}

// Handle modal close
function handleClose() {
  isOpen.value = false
  clearSelectedUser()
  searchQuery.value = ''
  debouncedSearchQuery.value = ''
}
</script>
