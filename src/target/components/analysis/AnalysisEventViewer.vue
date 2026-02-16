<template>
  <div class="absolute inset-0 flex flex-col overflow-auto px-6 pb-6">
    <EventSkeleton v-if="isLoading" />
    <ErrorMessage v-else-if="error" vertical-align="center" />
    <EmptyState
      v-else-if="eventsData?.totalItems === 0"
      vertical-align="center"
    />
    <div v-else class="w-full space-y-8">
      <AnalysisTimeline
        v-for="event in eventsData?.items"
        :key="event.id"
        :watch-file-event="event"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { useQuery } from '@pinia/colada';
import { getWatchFileEventsQueryLink } from '~/api/queries/watchFileEvents';
import EmptyState from '../global/EmptyState.vue';
import ErrorMessage from '../global/ErrorMessage.vue';
import EventSkeleton from '../skeletons/EventSkeleton.vue';
import AnalysisTimeline from './timeline/AnalysisTimeline.vue';

interface Props {
  link: string;
}

const { link } = defineProps<Props>();

const {
  data: eventsData,
  isLoading,
  error,
} = useQuery(getWatchFileEventsQueryLink, () => ({
  link: link,
}));
</script>
