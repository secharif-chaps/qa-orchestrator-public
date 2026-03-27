<template>
  <i18n-t scope="global" keypath="target.watchFiles.activity.history.status_changed" tag="span">
    <template #userName>
      <span class="font-semibold">{{ userName }}</span>
    </template>
    <template #oldStatus>
      <Tag variant="secondary" kind="light" size="sm" :icon="getStatusIcon(oldStatus)">
        {{ oldStatusText }}
      </Tag>
    </template>
    <template #newStatus>
      <Tag variant="secondary" kind="light" size="sm" :icon="getStatusIcon(newStatus)">
        {{ newStatusText }}
      </Tag>
    </template>
  </i18n-t>
</template>

<script setup lang="ts">
import { Tag } from '@owlint/feathers-vue'
import type { WatchFileStatus } from '@target/types/watchFile'
import { WATCH_FILE_STATUS, WATCH_FILE_STATUS_ICONS } from '@target/types/watchFile'

interface Props {
  userName: string
  oldStatusText: string
  newStatusText: string
  oldStatus: WatchFileStatus
  newStatus: WatchFileStatus
}

defineProps<Props>()

function getStatusIcon(status: WatchFileStatus) {
  switch (status) {
    case WATCH_FILE_STATUS.DRAFT:
      return WATCH_FILE_STATUS_ICONS.DRAFT
    case WATCH_FILE_STATUS.ENABLED:
      return WATCH_FILE_STATUS_ICONS.ENABLED
    case WATCH_FILE_STATUS.ARCHIVED:
      return WATCH_FILE_STATUS_ICONS.ARCHIVED
    default:
      return 'fa-circle-info'
  }
}
</script>
