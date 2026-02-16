<template>
  <div
    v-for="eventType in eventTypes"
    :key="eventType"
    class="bg-sage-200 text-sage-600 flex items-center gap-1 rounded-sm p-1 text-sm"
  >
    <Icon icon="fa-bullhorn" />
    <span class="shrink">{{
      $t(`watch_files.analysis.event.type.${eventType}`)
    }}</span>
    <button
      class="flex items-center justify-center"
      @click="emit('openEditFilter', 'sources')"
    >
      <Icon icon="fa-pen" />
    </button>
    <button
      class="flex items-center justify-center"
      @click="handleRemove(eventType)"
    >
      <Icon icon="fa-xmark" />
    </button>
  </div>
</template>

<script lang="ts" setup>
import { Icon } from '@owlint/feathers-vue';
import type { WatchFileEventType } from '~/types/watchFile';

interface Props {
  eventTypes: WatchFileEventType[];
}

defineProps<Props>();

const emit = defineEmits<{
  openEditFilter: [filterType: string];
  remove: [id: WatchFileEventType];
}>();

const handleRemove = (id: WatchFileEventType) => {
  emit('remove', id);
};
</script>
