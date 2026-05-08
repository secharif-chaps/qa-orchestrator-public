<template>
  <div v-if="activity.actionType === 'status_changed'">
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
  </div>

  <i18n-t
    v-else-if="activity.actionType === 'created'"
    scope="global"
    keypath="target.watchFiles.activity.history.watch_file_created"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="activity.actionType === 'updated'"
    scope="global"
    keypath="target.watchFiles.activity.history.watch_file_updated"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="
      activity.actionType === 'source_status_changed' &&
      (activity.actionData as SourceStatusChangedActionData).status === 'active'
    "
    scope="global"
    keypath="target.watchFiles.activity.history.source_status_changed_active"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
    <template #sourceName
      ><span class="font-semibold">{{ sourceName }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="
      activity.actionType === 'source_status_changed' &&
      (activity.actionData as SourceStatusChangedActionData).status === 'inactive'
    "
    scope="global"
    keypath="target.watchFiles.activity.history.source_status_changed_inactive"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
    <template #sourceName
      ><span class="font-semibold">{{ sourceName }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="
      activity.actionType === 'source_status_changed' &&
      (activity.actionData as SourceStatusChangedActionData).status === 'auto_disabled'
    "
    scope="global"
    keypath="target.watchFiles.activity.history.source_status_changed_auto_disabled"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
    <template #sourceName
      ><span class="font-semibold">{{ sourceName }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="
      activity.actionType === 'actor_status_changed' &&
      (activity.actionData as ActorStatusChangedActionData).status === 'active'
    "
    scope="global"
    keypath="target.watchFiles.activity.history.actor_status_changed_active"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
    <template #actorName
      ><span class="font-semibold">{{ actorName }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="
      activity.actionType === 'actor_status_changed' &&
      (activity.actionData as ActorStatusChangedActionData).status === 'inactive'
    "
    scope="global"
    keypath="target.watchFiles.activity.history.actor_status_changed_inactive"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
    <template #actorName
      ><span class="font-semibold">{{ actorName }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="
      activity.actionType === 'shared_mode_changed' &&
      (activity.actionData as SharedModeChangedActionData).new_value ===
        WatchFileUserAccessState.NO_ACCESS
    "
    scope="global"
    keypath="target.watchFiles.activity.history.shared_mode_removed"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
    <template #userEmail
      ><span class="font-semibold">{{ userEmail }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="
      activity.actionType === 'shared_mode_changed' &&
      (activity.actionData as SharedModeChangedActionData).new_value ===
        WatchFileUserAccessState.OWNER
    "
    scope="global"
    keypath="target.watchFiles.activity.history.shared_mode_owner"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="activity.actionType === 'shared_mode_changed'"
    scope="global"
    keypath="target.watchFiles.activity.history.shared_mode_changed"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
    <template #userEmail
      ><span class="font-semibold">{{ userEmail }}</span></template
    >
    <template #role
      ><span class="italic">{{ roleText }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="activity.actionType === 'actor_added'"
    scope="global"
    keypath="target.watchFiles.activity.history.actor_added"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
    <template #actorName
      ><span class="font-semibold">{{ actorName }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="activity.actionType === 'source_added'"
    scope="global"
    keypath="target.watchFiles.activity.history.source_added"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
    <template #sourceName
      ><span class="font-semibold">{{ sourceName }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="activity.actionType === 'monitoring_type_detected'"
    scope="global"
    keypath="target.watchFiles.activity.history.monitoring_type_detected"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
  </i18n-t>
  <i18n-t
    v-else-if="activity.actionType === 'reference_subject_detected'"
    scope="global"
    keypath="target.watchFiles.activity.history.reference_subject_detected"
    tag="span"
  >
    <template #userName
      ><span class="font-semibold">{{ userName }}</span></template
    >
  </i18n-t>
  <span v-else />
</template>

<script setup lang="ts">
import { Tag } from '@owlint/feathers-vue'
import type {
  ActorStatusChangedActionData,
  SharedModeChangedActionData,
  SourceStatusChangedActionData,
  StatusChangedActionData,
  WatchFileActivity,
  WatchFileStatus,
} from '@target/types/watchFile'
import {
  WATCH_FILE_STATUS,
  WATCH_FILE_STATUS_ICONS,
  WatchFileUserAccessState,
} from '@target/types/watchFile'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  activity: WatchFileActivity
}

const props = defineProps<Props>()
const { t } = useI18n()

const headerStatusLabelMap: Record<string, string> = {
  draft: t('target.watchFiles.header_section.status.draft'),
  enabled: t('target.watchFiles.header_section.status.enabled'),
  archived: t('target.watchFiles.header_section.status.archived'),
}

const userName = computed(() => props.activity.user.displayName)

const oldStatusText = computed(() => {
  if (props.activity.actionType === 'status_changed') {
    const statusData = props.activity.actionData as StatusChangedActionData
    return headerStatusLabelMap[statusData.old_status] ?? statusData.old_status
  }
  return ''
})

const newStatusText = computed(() => {
  if (props.activity.actionType === 'status_changed') {
    const statusData = props.activity.actionData as StatusChangedActionData
    return headerStatusLabelMap[statusData.new_status] ?? statusData.new_status
  }
  return ''
})

const oldStatus = computed(() => {
  if (props.activity.actionType === 'status_changed') {
    const statusData = props.activity.actionData as StatusChangedActionData
    return statusData.old_status as WatchFileStatus
  }
  return WATCH_FILE_STATUS.DRAFT
})

const newStatus = computed(() => {
  if (props.activity.actionType === 'status_changed') {
    const statusData = props.activity.actionData as StatusChangedActionData
    return statusData.new_status as WatchFileStatus
  }
  return WATCH_FILE_STATUS.DRAFT
})

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

const sourceName = computed(() => {
  if (props.activity.actionType === 'source_status_changed') {
    const sourceData = props.activity.actionData as SourceStatusChangedActionData
    return sourceData.source_name
  }
  return ''
})

const actorName = computed(() => {
  if (props.activity.actionType === 'actor_status_changed') {
    const actorData = props.activity.actionData as ActorStatusChangedActionData
    return actorData.name
  }
  return ''
})

const userEmail = computed(() => {
  if (props.activity.actionType === 'shared_mode_changed') {
    const sharedData = props.activity.actionData as SharedModeChangedActionData
    return sharedData.user_email
  }
  return ''
})

const roleText = computed(() => {
  if (props.activity.actionType === 'shared_mode_changed') {
    const sharedData = props.activity.actionData as SharedModeChangedActionData
    return t('target.watchFiles.access', { role: sharedData.new_value })
  }
  return ''
})
</script>
