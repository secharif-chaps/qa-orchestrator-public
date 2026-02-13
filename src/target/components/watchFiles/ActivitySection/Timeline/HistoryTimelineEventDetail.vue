<template>
  <Drawer v-model="isDrawerOpen" :title="drawerTitle" to="#watchfile-layout">
    <div class="flex flex-col gap-1 p-6">
      <HistoryTimelineEventSkeleton v-if="isLoadingEvent" />
      <SourceCard
        v-for="source in dataSources?.sources"
        v-else-if="sourceEventId && dataSources?.sources?.length"
        :key="source.id"
        :source="source"
      />
      <ActorCard
        v-for="actor in dataActors?.actors"
        v-else-if="actorEventId && dataActors?.actors?.length"
        :key="actor.id"
        :actor="actor"
      />
      <EmptyState
        v-else-if="!dataSources?.sources?.length && !dataActors?.actors?.length"
        :title="emptyTitle"
        :description="emptyDescription"
        vertical-align="center"
      />
      <ErrorMessage
        v-else-if="isError"
        :title="errorTitle"
        vertical-align="center"
      />
    </div>
  </Drawer>
</template>

<script setup lang="ts">
import { useQuery } from '@pinia/colada';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
  getWatchFileTimelineEventActorsQuery,
  getWatchFileTimelineEventSourcesQuery,
} from '~/api/queries/timeline';
import ErrorMessage from '~/components/global/ErrorMessage.vue';
import Drawer from '~/components/global/Drawer.vue';
import EmptyState from '~/components/global/EmptyState.vue';
import SourceCard from '~/components/sources/SourceCard.vue';
import type { WatchFileEventType } from '~/types/watchFile';
import HistoryTimelineEventSkeleton from './HistoryTimelineEventSkeleton.vue';
import ActorCard from '~/components/actors/ActorCard.vue';

interface Props {
  watchFileId: string;
  actorEventId: string;
  sourceEventId: string;
  eventType?: WatchFileEventType;
}

const {
  watchFileId,
  actorEventId,
  sourceEventId,
  eventType = undefined,
} = defineProps<Props>();

const isOpen = defineModel<boolean>('isOpen');

const { t } = useI18n();

const isDrawerOpen = isOpen;

const {
  data: dataActors,
  isLoading: isLoadingActors,
  error: errorActors,
} = useQuery(getWatchFileTimelineEventActorsQuery, () => ({
  watchFileId: watchFileId,
  eventId: actorEventId,
}));

const {
  data: dataSources,
  isLoading: isLoadingSources,
  error: errorSources,
} = useQuery(getWatchFileTimelineEventSourcesQuery, () => ({
  watchFileId: watchFileId,
  eventId: sourceEventId,
}));

const isLoadingEvent = computed(
  () => isLoadingActors.value || isLoadingSources.value,
);
const isError = computed(() => errorActors.value || errorSources.value);

const errorTitle = computed(() =>
  actorEventId
    ? t('watch_files.activity.history.event.error.actors')
    : t('watch_files.activity.history.event.error.sources'),
);

const emptyTitle = computed(() =>
  actorEventId
    ? t('watch_files.activity.history.event.empty.actors.title')
    : t('watch_files.activity.history.event.empty.sources.title'),
);

const emptyDescription = computed(() =>
  actorEventId
    ? t('watch_files.activity.history.event.empty.actors.description')
    : t('watch_files.activity.history.event.empty.sources.description'),
);

const drawerTitle = computed(() =>
  t(`watch_files.activity.history.event.${eventType}`),
);
</script>
