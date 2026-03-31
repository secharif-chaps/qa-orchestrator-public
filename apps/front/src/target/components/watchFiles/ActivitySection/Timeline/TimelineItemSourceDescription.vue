<template>
  <i18n-t scope="global" :keypath="keypath" tag="span">
    <template #userName>
      <span class="font-semibold">{{ userName }}</span>
    </template>
    <template #errorMessage>
      <span class="block italic">{{ errorMessage }}</span>
    </template>
  </i18n-t>
</template>

<script setup lang="ts">
import type { SourceActivity } from '@target/types/source'
import { SourceActionType, SourceStatus } from '@target/types/source'
import { computed } from 'vue'

interface Props {
  activity: SourceActivity
}

const props = defineProps<Props>()

const userName = computed(() => props.activity?.user?.displayName || '')

const keypath = computed(() => {
  const { actionType, actionData } = props.activity

  switch (actionType) {
    case SourceActionType.SOURCE_CONNECTED:
      return 'target.watchFiles.activity.sources.history.source_connected'
    case SourceActionType.SOURCE_ERROR:
      return 'target.watchFiles.activity.sources.history.source_error'
    case SourceActionType.SOURCE_RECOVERED:
      return 'target.watchFiles.activity.sources.history.source_recovered'
    case SourceActionType.SOURCE_CONFIG_UPDATED:
      return 'target.watchFiles.activity.sources.history.source_config_updated'
    case SourceActionType.SOURCE_ADDED_TO_WATCHFILE:
      return 'target.watchFiles.activity.sources.history.source_added_to_watchfile'
    case SourceActionType.SOURCE_COLLECT_STATUS_CHANGED:
      return 'target.watchFiles.activity.sources.history.source_collect_status_changed'
    case SourceActionType.SOURCE_STATUS_CHANGED:
      if (actionData.new_status === SourceStatus.ACTIVE) {
        return 'target.watchFiles.activity.sources.history.source_status_changed_active'
      } else {
        return 'target.watchFiles.activity.sources.history.source_status_changed_inactive'
      }
    default:
      return ''
  }
})

const errorMessage = computed(() => {
  if (props.activity.actionType === SourceActionType.SOURCE_ERROR) {
    return props.activity.actionData.error_message
  }
  return ''
})
</script>
