<template>
  <Modal
    v-model:display-modal="isOpen"
    :title="$t('watch_files.shareDialog.title', { name: selectedWatchFile?.name })"
    size="xl"
    class="combobox-modal"
    @close="handleClose"
  >
    <template #description>
      <div class="flex items-center">
        <WatchFileUserInvite
          ref="inviteRef"
          :watch-file-id="selectedWatchFile?.id || ''"
          @users-added="onUsersAdded"
        />
      </div>
      <WatchFileUserList
        :watch-file-id="selectedWatchFile?.id || ''"
        :watch-file-users="watchFileUsers"
        :is-loading="isLoading"
        :skeleton-count="(selectedWatchFile?.watchFileUsersCount as number) ?? 3"
        @user-removed="onUserRemoved"
      />
    </template>
    <template #footer>
      <div class="flex justify-end">
        <Button @click="handleClose">
          {{ $t('common.button.close') }}
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { Button, Modal } from '@owlint/feathers-vue'
import { useQuery, useQueryCache } from '@pinia/colada'
import { computed, ref } from 'vue'
import { WATCH_FILE_QUERY_KEYS } from '~/api/queries/watchFile'
import { getWatchFileUsersQuery } from '~/api/queries/watchFileUser'
import { useConfirmModal } from '~/composables/useConfirmModal'
import { useWatchFileStore } from '~/stores/watchFile'
import type { WatchFile } from '~/types/watchFile'
import type { WatchFileUser } from '~/types/watchFileUser'
import WatchFileUserInvite from './WatchFileUserInvite.vue'
import WatchFileUserList from './WatchFileUserList.vue'

const watchFileStore = useWatchFileStore()
const { modalState } = useConfirmModal()
const queryCache = useQueryCache()

const props = defineProps<{
  selectedWatchFile: WatchFile | null
}>()

const isOpen = defineModel<boolean>('isOpen')

const emit = defineEmits<{
  (e: 'close'): void
}>()

const inviteRef = ref<InstanceType<typeof WatchFileUserInvite> | null>(null)

const { data: watchFileUsersData, isLoading } = useQuery(getWatchFileUsersQuery, () => ({
  watchFileId: props.selectedWatchFile?.id ?? '',
  isOpen: isOpen.value ?? false,
}))

const watchFileUsers = computed(() => watchFileUsersData.value?.member ?? [])

const handleClose = () => {
  // Only close if the confirmation modal is not open
  if (!modalState.value.isOpen) {
    emit('close')
    resetForm()
  }
}

function resetForm() {
  inviteRef.value?.resetForm()
}

function updateWatchFileUsersCount() {
  if (props.selectedWatchFile?.id) {
    queryCache.invalidateQueries({
      key: WATCH_FILE_QUERY_KEYS.byId(props.selectedWatchFile?.id),
    })
  }
  queryCache.invalidateQueries({
    key: WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters),
  })
}

function onUserRemoved(_userId: string) {
  // Data is automatically updated by the query cache invalidation
  updateWatchFileUsersCount()
}

function onUsersAdded(_newUsers: WatchFileUser[]) {
  // Data is automatically updated by the query cache invalidation
  updateWatchFileUsersCount()
}
</script>
