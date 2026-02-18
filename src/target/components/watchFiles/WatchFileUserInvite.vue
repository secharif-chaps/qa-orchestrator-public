<template>
  <div class="relative flex-1">
    <div class="flex items-center gap-2">
      <div
        class="relative flex min-h-10 flex-1 flex-wrap items-center rounded-md border border-gray-300 bg-white px-1"
      >
        <template v-for="selectedUser in selectedUsers" :key="selectedUser.id">
          <WatchFileUserChip :user="selectedUser" @remove="removeSelectedUser" />
        </template>
        <input
          id="invite-input"
          v-model="newUser"
          :placeholder="$t('watch_files.shareDialog.invitePlaceholder')"
          class="ml-1 w-full min-w-32 flex-1 rounded-xs outline-none"
          autocomplete="off"
          data-form-type="other"
          data-protonpass-ignore="true"
          @input="onInputSearch"
          @focus="showAutocomplete = true"
          @blur="handleBlur"
          @keydown="handleKeyDown"
        />
        <div
          v-if="showAutocomplete && searchResults.length > 0"
          ref="list"
          class="absolute top-full left-0 z-10 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-2xl"
        >
          <ul class="divide-y divide-gray-200">
            <li
              v-for="(user, index) in searchResults"
              :key="user.id"
              class="hover:bg-primary-lightest relative flex cursor-pointer items-center gap-3 px-4 py-2 select-none"
              :class="{
                'bg-primary-lightest active': index === highlightedIndex,
              }"
              @mousedown="onDropdownSelect(user)"
            >
              <Badge :number="user.defaultThumbnail" size="sm" variant="secondary" />
              <div>
                <div class="truncate">
                  {{ user.displayName }}
                </div>
                <div class="truncate text-sm">
                  {{ user.email }}
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
      <Button
        variant="accent"
        :disabled="selectedUsers.length === 0"
        :loading="isAddingUsers"
        @click="onAddUser"
      >
        {{ $t('watch_files.shareDialog.invite') }}
      </Button>
    </div>
    <div class="flex pt-2">
      <OPopper side="bottom">
        <template #tooltip>
          <span class="block max-w-50">
            {{ $t('watch_files.shareDialog.role.viewerDescription') }}
          </span>
        </template>
        <ORadio
          id="share-rights-radio-readonly"
          v-model="shareRights"
          :value="WATCH_FILE_USER_ROLE.VIEWER"
          name="share-rights"
          :label="$t('watch_files.shareDialog.role.viewer')"
        />
      </OPopper>
      <OPopper side="bottom">
        <template #tooltip>
          <span class="block max-w-50">
            {{ $t('watch_files.shareDialog.role.editorDescription') }}
          </span>
        </template>
        <ORadio
          id="share-rights-radio-canedit"
          v-model="shareRights"
          :value="WATCH_FILE_USER_ROLE.EDITOR"
          name="share-rights"
          :label="$t('watch_files.shareDialog.role.editor')"
          class="ml-2"
        />
      </OPopper>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Badge, Button, OPopper, ORadio } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { useAddWatchFileUsers } from '@target/api/mutations/watchFileUser'
import { searchUsersQuery } from '@target/api/queries/user'
import type { User } from '@target/types/user'
import type { WatchFileUser, WatchFileUserRole } from '@target/types/watchFileUser'
import { WATCH_FILE_USER_ROLE } from '@target/types/watchFileUser'
import { computed, nextTick, ref, useTemplateRef } from 'vue'
import WatchFileUserChip from './WatchFileUserChip.vue'

const props = defineProps<{
  watchFileId: string
}>()

const emit = defineEmits<{
  (e: 'users-added', users: WatchFileUser[]): void
}>()

const { addUsers, isLoading: isAddingUsers } = useAddWatchFileUsers({
  onSuccess: (users) => {
    emit('users-added', users)
  },
})

const selectedUsers = ref<User[]>([])
const shareRights = ref<WatchFileUserRole>(WATCH_FILE_USER_ROLE.VIEWER)
const newUser = ref('')

// Autocomplete state
const showAutocomplete = ref(false)
const highlightedIndex = ref(-1)
const listRef = useTemplateRef('list')
const searchQuery = ref('')

const { data: searchData } = useQuery(searchUsersQuery, () => ({
  query: searchQuery.value,
  excludeWatchFileSharedUsers: props.watchFileId,
}))

const searchResults = computed(() => searchData.value?.member || [])

function handleKeyDown(event: KeyboardEvent) {
  // Remove last chip on Backspace if input is empty
  if (event.key === 'Backspace' && newUser.value.length === 0 && selectedUsers.value.length > 0) {
    selectedUsers.value.pop()
    event.preventDefault()
    return
  }

  if (!showAutocomplete.value || searchResults.value.length === 0) {
    if (event.key === 'Enter' && selectedUsers.value.length > 0) {
      onAddUser()
    }

    return
  }

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      highlightedIndex.value = Math.min(highlightedIndex.value + 1, searchResults.value.length - 1)
      scrollToActive()
      break
    case 'ArrowUp':
      event.preventDefault()
      highlightedIndex.value = Math.max(highlightedIndex.value - 1, -1)
      scrollToActive()
      break
    case 'Tab':
      event.preventDefault()
      if (highlightedIndex.value >= 0) {
        const user = searchResults.value[highlightedIndex.value]
        if (user) onDropdownSelect(user)
      } else {
        // select first result if no highlight
        if (searchResults.value.length > 0) {
          const firstUser = searchResults.value[0]
          if (firstUser) onDropdownSelect(firstUser)
        }
      }
      break
    case 'Enter':
      event.preventDefault()
      if (highlightedIndex.value >= 0) {
        const user = searchResults.value[highlightedIndex.value]
        if (user) onDropdownSelect(user)
      }
      break
    case 'Escape':
      event.preventDefault()
      showAutocomplete.value = false
      highlightedIndex.value = -1
      break
  }
}

const scrollToActive = async () => {
  await nextTick()
  const activeElement = listRef.value?.querySelector('.active')
  if (activeElement) {
    activeElement.scrollIntoView({
      behavior: 'smooth',
      block: 'nearest',
    })
  }
}

async function onAddUser() {
  try {
    await addUsers({
      watchFileId: props.watchFileId,
      users: selectedUsers.value,
      role: shareRights.value,
    })

    // Only clear the chips if the API call was successful
    selectedUsers.value = []
    newUser.value = ''
  } catch (error) {
    console.error('Failed to add users:', error)
  }
}

function selectUser(user: User) {
  // Prevent duplicates
  if (!selectedUsers.value.some((u: User) => u.id === user.id)) {
    selectedUsers.value.push(user)
  }
  newUser.value = ''
  showAutocomplete.value = false
  searchQuery.value = ''
  highlightedIndex.value = -1
  // Blur and then refocus input after selection (mouse or keyboard)
  requestAnimationFrame(() => {
    const el = document.getElementById('invite-input') as HTMLInputElement | null
    if (el) {
      el.blur()
      setTimeout(() => el.focus(), 0)
    }
  })
}

function removeSelectedUser(id: string) {
  const idx = selectedUsers.value.findIndex((u: User) => u.id === id)
  if (idx !== -1) selectedUsers.value.splice(idx, 1)
}

function handleBlur() {
  showAutocomplete.value = false
  highlightedIndex.value = -1
}

function onInputSearch(event: Event) {
  const input = event.target as HTMLInputElement

  if (input.value.length >= 3) {
    searchQuery.value = input.value
    showAutocomplete.value = true
    highlightedIndex.value = -1
  } else {
    searchQuery.value = ''
    showAutocomplete.value = false
    highlightedIndex.value = -1
  }
}

function onDropdownSelect(user: User) {
  selectUser(user)
  searchQuery.value = ''
}

// Expose reset function for parent component
function resetForm() {
  newUser.value = ''
  selectedUsers.value = []
  shareRights.value = WATCH_FILE_USER_ROLE.VIEWER
  searchQuery.value = ''
  showAutocomplete.value = false
  highlightedIndex.value = -1
}

defineExpose({
  resetForm,
})
</script>
