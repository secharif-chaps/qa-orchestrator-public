<template>
  <div class="flex h-[75vh] flex-col gap-4">
    <div class="scrollable min-h-0 flex-1 pr-4">
      <AnalysisTimelineSkeleton v-if="isInitialLoading" />
      <EmptyState
        v-else-if="!allEvents.length"
        :title="t('watch_files.analysis.timeline.empty.title')"
        :description="t('watch_files.analysis.timeline.empty.description')"
        icon="fa-clock"
        vertical-align="center"
      />
      <div v-else class="overflow-hidden">
        <div
          v-for="dayGroup in eventsByDay"
          :key="dayGroup.date"
          class="relative flex items-start gap-6 pb-6"
        >
          <div class="w-36 pt-3 text-right">
            <div class="text-secondary-font pt-2.5 text-sm font-medium">
              {{ eventsDatesTitle(dayGroup.date, dayGroup.events.length) }}
            </div>
          </div>
          <div class="bg-primary-light absolute top-6 left-[163px] h-full w-0.5" />
          <div class="relative">
            <Indicator size="md" class="absolute top-6 -left-3" />
          </div>

          <div class="flex-1 space-y-2">
            <AnalysisTimeline
              v-for="watchfileEvent in dayGroup.events"
              :key="watchfileEvent.id"
              :watch-file-event="watchfileEvent"
            />
          </div>
        </div>
        <div v-if="allEvents.length > 0" class="flex justify-center py-4">
          <Button v-if="hasMore && !isLoadingMore" variant="secondary" @click="loadMoreEvents">
            {{ t('watch_files.analysis.timeline.loadMore') }}
          </Button>
          <div v-else-if="isLoadingMore" class="flex items-center gap-2 text-sm text-gray-600">
            <Icon icon="fa-spinner" class="animate-spin" />
            <span>{{ t('common.action.loading') }}</span>
          </div>
          <p v-else-if="!hasMore && !isLoadingMore" class="text-sm text-gray-600">
            {{ t('watch_files.analysis.timeline.allEventsLoaded') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { Button, Icon, Indicator } from '@owlint/feathers-vue'
import { useWatchFileEventsInfiniteQuery } from '@target/api/queries/watchFileEvents'
import EmptyState from '@target/components/global/EmptyState.vue'
import AnalysisTimelineSkeleton from '@target/components/skeletons/AnalysisTimelineSkeleton.vue'
import type { WatchFileEvent } from '@target/types/watchFileEvent'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { d, t } = useI18n()

const route = useRoute()

const watchFileId = computed(() => route.params.id as string)

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
