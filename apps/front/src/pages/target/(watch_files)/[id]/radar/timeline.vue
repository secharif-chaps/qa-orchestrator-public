<template>
  <div class="flex h-[75vh] flex-col gap-4">
    <div class="scrollable min-h-0 flex-1 pr-4">
      <AnalysisTimelineSkeleton v-if="isInitialLoading" />
      <EmptyState
        v-else-if="!allEvents.length"
        :title="t('target.watchFiles.analysis.timeline.empty.title')"
        :description="t('target.watchFiles.analysis.timeline.empty.description')"
        icon="fa-clock"
        vertical-align="center"
      />
      <div v-else class="overflow-hidden">
        <TimelineItem
          v-for="dayGroup in eventsByDay"
          :key="dayGroup.date"
          :date="eventsDatesTitle(dayGroup.date, dayGroup.events.length)"
        >
          <TimelineCard v-for="watchfileEvent in dayGroup.events" :key="watchfileEvent.id">
            <AnalysisTimeline :watch-file-event="watchfileEvent" />
          </TimelineCard>
        </TimelineItem>
        <div v-if="allEvents.length > 0" class="flex justify-center py-4">
          <Button v-if="hasMore && !isLoadingMore" variant="secondary" @click="loadMoreEvents">
            {{ t('target.watchFiles.analysis.timeline.loadMore') }}
          </Button>
          <div v-else-if="isLoadingMore" class="flex items-center gap-2 text-sm text-gray-600">
            <Icon icon="fa-spinner" class="animate-spin" />
            <span>{{ t('common.action.loading') }}</span>
          </div>
          <p v-else-if="!hasMore && !isLoadingMore" class="text-sm text-gray-600">
            {{ t('target.watchFiles.analysis.timeline.allEventsLoaded') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import TimelineCard from '@/components/ui/TimelineCard.vue'
import TimelineItem from '@/components/ui/TimelineItem.vue'
import { Button, Icon } from '@owlint/feathers-vue'
import { useWatchFileEventsInfiniteQuery } from '@target/api/queries/watchFileEvents'
import EmptyState from '@target/components/global/EmptyState.vue'
import AnalysisTimelineSkeleton from '@target/components/skeletons/AnalysisTimelineSkeleton.vue'
import type { WatchFileEvent } from '@target/types/watchFileEvent'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { d, t } = useI18n()

const route = useRoute('/target/(watch_files)/[id]/radar/timeline')

const watchFileId = computed(() => route.params.id)

const { allEvents, hasMore, isLoadingMore, loadMoreEvents, isInitialLoading } =
  useWatchFileEventsInfiniteQuery(watchFileId)

interface DayGroup {
  date: string
  events: WatchFileEvent[]
}

const eventsByDay = computed<DayGroup[]>(() => {
  if (!allEvents.value.length) {
    return []
  }

  const groupedByDay = new Map<string, WatchFileEvent[]>()

  for (const event of allEvents.value) {
    if (!event.startDate) {
      continue
    }

    const isoString = new Date(event.startDate).toISOString()
    const eventDate = isoString.split('T')[0]

    if (!eventDate) {
      continue
    }

    if (!groupedByDay.has(eventDate)) {
      groupedByDay.set(eventDate, [])
    }

    groupedByDay.get(eventDate)!.push(event)
  }

  return Array.from(groupedByDay.entries()).map(([date, events]) => ({
    date,
    events,
  }))
})

const eventsDatesTitle = (date: string, nbEvents: number) => {
  if (nbEvents > 1) {
    return `${d(date, 'eventDate')} (${nbEvents})`
  }

  return `${d(date, 'eventDate')}`
}
</script>
