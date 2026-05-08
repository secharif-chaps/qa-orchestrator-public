<template>
  <i18n-t
    v-if="activity.actionType === SourceActionType.SOURCE_CONNECTED"
    scope="global"
    keypath="target.watchFiles.activity.sources.history.source_connected"
    tag="span"
  />
  <i18n-t
    v-else-if="activity.actionType === SourceActionType.SOURCE_ERROR"
    scope="global"
    keypath="target.watchFiles.activity.sources.history.source_error"
    tag="span"
  >
    <template #errorMessage>
      <span class="block italic">{{ errorMessage }}</span>
    </template>
  </i18n-t>
  <i18n-t
    v-else-if="activity.actionType === SourceActionType.SOURCE_RECOVERED"
    scope="global"
    keypath="target.watchFiles.activity.sources.history.source_recovered"
    tag="span"
  />
  <i18n-t
    v-else-if="activity.actionType === SourceActionType.SOURCE_CONFIG_UPDATED"
    scope="global"
    keypath="target.watchFiles.activity.sources.history.source_config_updated"
    tag="span"
  >
    <template #userName>
      <span class="font-semibold">{{ userName }}</span>
    </template>
  </i18n-t>
  <i18n-t
    v-else-if="activity.actionType === SourceActionType.SOURCE_ADDED_TO_WATCHFILE"
    scope="global"
    keypath="target.watchFiles.activity.sources.history.source_added_to_watchfile"
    tag="span"
  >
    <template #userName>
      <span class="font-semibold">{{ userName }}</span>
    </template>
  </i18n-t>
  <i18n-t
    v-else-if="activity.actionType === SourceActionType.SOURCE_COLLECT_STATUS_CHANGED"
    scope="global"
    keypath="target.watchFiles.activity.sources.history.source_collect_status_changed"
    tag="span"
  />
  <i18n-t
    v-else-if="
      activity.actionType === SourceActionType.SOURCE_STATUS_CHANGED &&
      (activity.actionData as Record<string, unknown>).new_status === SourceStatus.ACTIVE
    "
    scope="global"
    keypath="target.watchFiles.activity.sources.history.source_status_changed_active"
    tag="span"
  >
    <template #userName>
      <span class="font-semibold">{{ userName }}</span>
    </template>
  </i18n-t>
  <i18n-t
    v-else-if="activity.actionType === SourceActionType.SOURCE_STATUS_CHANGED"
    scope="global"
    keypath="target.watchFiles.activity.sources.history.source_status_changed_inactive"
    tag="span"
  >
    <template #userName>
      <span class="font-semibold">{{ userName }}</span>
    </template>
  </i18n-t>
  <span v-else />
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

const errorMessage = computed(() => {
  if (props.activity.actionType === SourceActionType.SOURCE_ERROR) {
    return props.activity.actionData.error_message
  }
  return ''
})
</script>
