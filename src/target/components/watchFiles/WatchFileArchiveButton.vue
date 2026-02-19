<template>
  <div>
    <Button
      variant="tertiary"
      size="sm"
      :icon="isArchived ? 'fa-rotate-left' : 'fa-box-archive'"
      :title="
        $t(
          isArchived
            ? 'watch_files.actions.restore_watch_file'
            : 'watch_files.actions.archive_watch_file',
        )
      "
      @click.stop="onButtonClick()"
    />
  </div>
</template>

<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'
import { useWatchFileStatusModal } from '@target/composables/useWatchFileStatusModal'
import type { WatchFile } from '@target/types/watchFile'
import { WATCH_FILE_STATUS } from '@target/types/watchFile'
import { computed } from 'vue'

const props = defineProps<{
  watchFile: WatchFile
}>()

const { showStatusModal, showRestoreModal } = useWatchFileStatusModal({}, props.watchFile)

const isArchived = computed(() => props.watchFile.status === WATCH_FILE_STATUS.ARCHIVED)

const onButtonClick = async () => {
  if (isArchived.value) {
    showRestoreModal(props.watchFile)
  } else {
    showStatusModal(props.watchFile, WATCH_FILE_STATUS.ARCHIVED)
  }
}
</script>
