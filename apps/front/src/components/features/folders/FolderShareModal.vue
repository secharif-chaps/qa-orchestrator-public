<template>
  <Modal
    v-model:displayModal="isOpen"
    :title="$t('common.folder.share.title')"
    icon="fa-users"
    size="2xl"
    @close="handleClose"
  >
    <template #description>
      {{ $t('common.folder.share.description') }}

      <div class="mt-4 flex flex-col gap-6">
        <!-- User Search Section -->
        <div class="flex flex-col gap-3">
          <Label id="user-search">
            {{ $t('common.folder.share.searchLabel') }}
          </Label>

          <div class="flex gap-2">
            <div class="flex-1">
              <Searchbar
                id="user-search-input"
                v-model="searchQuery"
                :placeholder="$t('common.folder.share.searchPlaceholder')"
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
              class="border-primary-lighter-stroke absolute top-0 right-0 left-0 z-10 max-h-60 overflow-y-auto rounded-sm border bg-white shadow-lg"
            >
              <!-- Loading state -->
              <div v-if="isSearching" class="text-neutral-black-font p-4 text-center">
                <i class="fa fa-spinner fa-spin mr-2"></i>
                {{ $t('common.folder.share.searching') }}
              </div>

              <!-- Results -->
              <div v-else-if="(searchResults?.length ?? 0) > 0" class="py-2">
                <button
                  v-for="user in searchResults"
                  :key="user.user_id"
                  type="button"
                  class="hover:bg-primary-lightest flex w-full items-center justify-between px-4 py-2 text-left transition-colors"
                  :class="{ 'opacity-50': isUserAlreadyShared(user.user_id) }"
                  :disabled="isUserAlreadyShared(user.user_id)"
                  @click="selectUser(user)"
                >
                  <div class="flex items-center gap-3">
                    <Avatar :label="user.username" color="sage" size="sm" />
                    <div>
                      <div class="font-medium">{{ user.username }}</div>
                      <div v-if="user.email" class="text-neutral-black-font text-xs">
                        {{ user.email }}
                      </div>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <Tag
                      v-if="!user.has_write_permission"
                      :label="$t('common.folder.share.readOnly')"
                      variant="secondary"
                      size="xs"
                    />
                    <Tag
                      v-if="isUserAlreadyShared(user.user_id)"
                      :label="$t('common.folder.share.alreadyShared')"
                      intent="info"
                      size="xs"
                    />
                  </div>
                </button>
              </div>

              <!-- No results -->
              <div v-else-if="!searchError" class="text-neutral-black-font p-4 text-center">
                {{ $t('common.folder.share.noResults') }}
              </div>
            </div>
          </div>

          <!-- Search Error Display -->
          <div
            v-if="searchError"
            class="bg-error-light text-error-light-content border-error-stroke rounded-sm border p-3 text-sm"
          >
            <i class="fa fa-exclamation-triangle mr-2"></i>
            {{ $t('common.folder.share.searchError') }}
          </div>

          <!-- Selected User (pending add) -->
          <div
            v-if="selectedUser"
            class="border-primary-lighter-stroke rounded-sm border bg-white p-4"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <Avatar :label="selectedUser.username" color="sage" size="sm" />
                <div>
                  <div class="font-medium">{{ selectedUser.username }}</div>
                  <div v-if="selectedUser.email" class="text-neutral-black-font text-xs">
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
                  :label="$t('common.folder.share.add')"
                  :loading="isAddingShare"
                  @click="handleAddUser"
                />

                <Button
                  variant="tertiary"
                  size="sm"
                  icon="fa fa-times"
                  icon-only
                  :title="$t('common.cancel')"
                  @click="clearSelectedUser"
                />
              </div>
            </div>

            <!-- Writer disabled explanation -->
            <div
              v-if="!selectedUser.has_write_permission"
              class="text-neutral-black-font mt-2 flex items-center gap-1 text-xs"
            >
              <i class="fa fa-info-circle"></i>
              {{ $t('common.folder.share.writerDisabledNote') }}
            </div>
          </div>
        </div>

        <!-- Current Shares Section -->
        <div class="flex flex-col gap-3">
          <Label id="current-shares">
            {{ $t('common.folder.share.currentShares') }}
          </Label>

          <!-- Loading shares -->
          <div v-if="isLoadingShares" class="text-neutral-black-font py-4 text-center">
            <i class="fa fa-spinner fa-spin mr-2"></i>
            {{ $t('common.folder.share.loadingShares') }}
          </div>

          <!-- Shares list -->
          <div
            v-else-if="shares && shares.length > 0"
            class="border-primary-lighter-stroke divide-primary-stroke divide-y rounded-sm border"
          >
            <div
              v-for="share in shares"
              :key="share.user_id"
              class="flex items-center justify-between p-3"
            >
              <div class="flex items-center gap-3">
                <Avatar :label="share.user_username" color="sage" size="sm" />
                <div>
                  <div class="font-medium">{{ share.user_username }}</div>
                  <div class="text-neutral-black-font text-xs">
                    {{ $t('common.folder.share.addedOn') }}
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
                  @update:model-value="(value) => updateShareRole(share, value as ShareRole)"
                />

                <!-- Remove Button -->
                <Button
                  variant="tertiary"
                  color="danger"
                  size="sm"
                  icon="fa fa-trash"
                  icon-only
                  :title="$t('common.folder.share.remove')"
                  :loading="removingShareUserId === share.user_id"
                  @click="handleRemoveShare(share)"
                />
              </div>
            </div>
          </div>

          <!-- No shares yet -->
          <div
            v-else
            class="text-neutral-black-font bg-primary-lightest rounded-sm py-6 text-center"
          >
            <i class="fa fa-user-friends mb-2 text-2xl opacity-50"></i>
            <p>
              {{ $t('common.folder.share.noShares') }}
            </p>
          </div>
        </div>
      </div>
    </template>

    <template #footer>
      <Button variant="secondary" :label="$t('common.close')" @click="handleClose" />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import {
  useCreateFolderShare,
  useDeleteFolderShare,
  useUpdateFolderShare,
} from '@/mutations/folderShares'
import { folderSharesQuery, userSearchQuery } from '@/queries/folderShares'
import type { FolderShare, ShareableUser, ShareRole } from '@/types/folder'
import { useDateTime } from '@/composables/useDateTime'
import { Avatar, Button, Label, Modal, Searchbar, Tag, Toggle } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  folderId: string
}

const props = defineProps<Props>()

const isOpen = defineModel<boolean>({ required: true })

const { t } = useI18n()
const { formatDate } = useDateTime()

// Get role options with disabled property based on user's write permission
function getRoleOptionsForUser(user: ShareableUser) {
  return [
    { value: 'reader', label: t('common.folder.share.reader'), disabled: false },
    {
      value: 'writer',
      label: t('common.folder.share.writer'),
      disabled: !user.has_write_permission,
    },
  ]
}

// Get role options for existing share based on user's write permission
function getRoleOptionsForShare(share: FolderShare) {
  return [
    { value: 'reader', label: t('common.folder.share.reader'), disabled: false },
    {
      value: 'writer',
      label: t('common.folder.share.writer'),
      disabled: !share.has_write_permission,
    },
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
const { data: shares, isLoading: isLoadingShares } = useQuery({
  ...folderSharesQuery({ folderId: props.folderId }),
  enabled: () => isOpen.value && !!props.folderId,
})

// Search users — getter form ensures options re-evaluate when debouncedSearchQuery changes
const {
  data: searchResults,
  isLoading: isSearching,
  error: searchError,
} = useQuery(() => userSearchQuery({ query: debouncedSearchQuery.value }))

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

// Handle modal close
function handleClose() {
  isOpen.value = false
  clearSelectedUser()
  searchQuery.value = ''
  debouncedSearchQuery.value = ''
}
</script>
