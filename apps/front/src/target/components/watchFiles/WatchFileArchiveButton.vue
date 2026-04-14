<template>
  <Button
    variant="tertiary"
    size="sm"
    :icon="isArchived ? 'fa-rotate-left' : 'fa-box-archive'"
    :title="
      isArchived
        ? $t('target.watchFiles.actions.restore_watch_file')
        : $t('target.watchFiles.actions.archive_watch_file')
    "
    :aria-label="
      isArchived
        ? $t('target.watchFiles.actions.restore_watch_file')
        : $t('target.watchFiles.actions.archive_watch_file')
    "
    :loading="isLoading"
    @click="toggleArchive(watchFile)"
  />
</template>

<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'
import { useChangeWatchFileStatus } from '@target/api/mutations/watchFile'
import type { WatchFile } from '@target/types/watchFile'
import { WATCH_FILE_STATUS } from '@target/types/watchFile'
import { computed } from 'vue'

const { watchFile } = defineProps<{
  watchFile: WatchFile
}>()

const { changeStatus, isLoading } = useChangeWatchFileStatus(watchFile)

const isArchived = computed(() => watchFile.status === WATCH_FILE_STATUS.ARCHIVED)

const toggleArchive = (file: WatchFile) => {
  const newStatus = isArchived.value ? WATCH_FILE_STATUS.DRAFT : WATCH_FILE_STATUS.ARCHIVED
  changeStatus({ id: file.id, status: newStatus })
}
</script>
