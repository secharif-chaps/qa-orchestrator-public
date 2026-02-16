<template>
  <div v-if="activity.actionType === 'status_changed'">
    <i18n-t
      scope="global"
      keypath="watch_files.activity.history.status_changed"
      tag="span"
    >
      <template #userName>
        <span class="font-semibold">{{ userName }}</span>
      </template>
      <template #oldStatus>
        <Tag
          variant="secondary"
          kind="outline"
          size="sm"
          :icon="getStatusIcon(oldStatus)"
        >
          {{ oldStatusText }}
        </Tag>
      </template>
      <template #newStatus>
        <Tag
          variant="secondary"
          kind="outline"
          size="sm"
          :icon="getStatusIcon(newStatus)"
        >
          {{ newStatusText }}
        </Tag>
      </template>
    </i18n-t>
  </div>

  <i18n-t v-else scope="global" :keypath="keypath" tag="span">
    <template #userName>
      <span class="font-semibold">{{ userName }}</span>
    </template>
    <template #sourceName>
      <span class="font-semibold">{{ sourceName }}</span>
    </template>
    <template #actorName>
      <span class="font-semibold">{{ actorName }}</span>
    </template>
    <template #userEmail>
      <span class="font-semibold">{{ userEmail }}</span>
    </template>
    <template #role>
      <span class="italic">{{ roleText }}</span>
    </template>
  </i18n-t>
</template>

<script setup lang="ts">
import { Tag } from '@owlint/feathers-vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type {
  ActorStatusChangedActionData,
  SharedModeChangedActionData,
  SourceStatusChangedActionData,
  StatusChangedActionData,
  WatchFileActivity,
  WatchFileStatus,
} from '~/types/watchFile';
import {
  WATCH_FILE_STATUS,
  WATCH_FILE_STATUS_ICONS,
  WatchFileUserAccessState,
} from '~/types/watchFile';

interface Props {
  activity: WatchFileActivity;
}

const props = defineProps<Props>();
const { t } = useI18n();

const userName = computed(() => props.activity.user.displayName);

const oldStatusText = computed(() => {
  if (props.activity.actionType === 'status_changed') {
    const statusData = props.activity.actionData as StatusChangedActionData;
    return t('watch_files.header_section.status.' + statusData.old_status);
  }
  return '';
});

const newStatusText = computed(() => {
  if (props.activity.actionType === 'status_changed') {
    const statusData = props.activity.actionData as StatusChangedActionData;
    return t('watch_files.header_section.status.' + statusData.new_status);
  }
  return '';
});

const oldStatus = computed(() => {
  if (props.activity.actionType === 'status_changed') {
    const statusData = props.activity.actionData as StatusChangedActionData;
    return statusData.old_status as WatchFileStatus;
  }
  return WATCH_FILE_STATUS.DRAFT;
});

const newStatus = computed(() => {
  if (props.activity.actionType === 'status_changed') {
    const statusData = props.activity.actionData as StatusChangedActionData;
    return statusData.new_status as WatchFileStatus;
  }
  return WATCH_FILE_STATUS.DRAFT;
});

function getStatusIcon(status: WatchFileStatus) {
  switch (status) {
    case WATCH_FILE_STATUS.DRAFT:
      return WATCH_FILE_STATUS_ICONS.DRAFT;
    case WATCH_FILE_STATUS.ENABLED:
      return WATCH_FILE_STATUS_ICONS.ENABLED;
    case WATCH_FILE_STATUS.ARCHIVED:
      return WATCH_FILE_STATUS_ICONS.ARCHIVED;
    default:
      return 'fa-circle-info';
  }
}

const keypath = computed(() => {
  const { actionType } = props.activity;

  switch (actionType) {
    case 'created':
      return 'watch_files.activity.history.watch_file_created';
    case 'updated':
      return 'watch_files.activity.history.watch_file_updated';
    case 'source_status_changed': {
      const sourceData = props.activity
        .actionData as SourceStatusChangedActionData;
      return (
        'watch_files.activity.history.source_status_changed_' +
        sourceData.status
      );
    }
    case 'actor_status_changed': {
      const actorData = props.activity
        .actionData as ActorStatusChangedActionData;
      return (
        'watch_files.activity.history.actor_status_changed_' + actorData.status
      );
    }
    case 'shared_mode_changed': {
      const sharedData = props.activity
        .actionData as SharedModeChangedActionData;
      if (sharedData.new_value === WatchFileUserAccessState.NO_ACCESS) {
        return 'watch_files.activity.history.shared_mode_removed';
      }
      if (sharedData.new_value === WatchFileUserAccessState.OWNER) {
        return 'watch_files.activity.history.shared_mode_owner';
      }
      return 'watch_files.activity.history.shared_mode_changed';
    }
    case 'actor_added':
      return 'watch_files.activity.history.actor_added';
    case 'source_added':
      return 'watch_files.activity.history.source_added';
    case 'monitoring_type_detected':
      return 'watch_files.activity.history.monitoring_type_detected';
    case 'reference_subject_detected':
      return 'watch_files.activity.history.reference_subject_detected';
    default:
      return '';
  }
});

const sourceName = computed(() => {
  if (props.activity.actionType === 'source_status_changed') {
    const sourceData = props.activity
      .actionData as SourceStatusChangedActionData;
    return sourceData.source_name;
  }
  return '';
});

const actorName = computed(() => {
  if (props.activity.actionType === 'actor_status_changed') {
    const actorData = props.activity.actionData as ActorStatusChangedActionData;
    return actorData.name;
  }
  return '';
});

const userEmail = computed(() => {
  if (props.activity.actionType === 'shared_mode_changed') {
    const sharedData = props.activity.actionData as SharedModeChangedActionData;
    return sharedData.user_email;
  }
  return '';
});

const roleText = computed(() => {
  if (props.activity.actionType === 'shared_mode_changed') {
    const sharedData = props.activity.actionData as SharedModeChangedActionData;
    return t('watch_files.access.' + sharedData.new_value);
  }
  return '';
});
</script>
