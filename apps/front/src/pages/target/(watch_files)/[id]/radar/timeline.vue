<template>
  <div class="flex h-[75vh] flex-col gap-4">
    <div ref="scrollContainerRef" class="scrollable min-h-0 flex-1 pr-4">
      <div data-scroll-target="top" />
      <AnalysisTimelineSkeleton v-if="isInitialLoading" />
      <EmptyState
        v-else-if="!allEvents.length"
        :title="t('target.watchFiles.analysis.timeline.empty.title')"
        :description="t('target.watchFiles.analysis.timeline.empty.description')"
        icon="fa-clock"
        vertical-align="center"
      />
      <div v-else class="overflow-hidden">
        <template
          v-for="row in timelineRows"
          :key="row.type === 'virtual-today' ? 'virtual-today' : row.dayGroup.date"
        >
          <!-- Scroll anchor placed above the today row (when today exists in the timeline) -->
          <div v-if="row.type === 'day' && row.isToday" data-scroll-target="today" />

          <TimelineItem
            v-if="row.type === 'virtual-today'"
            data-scroll-target="today"
            :date="t('common.today')"
            is-today
          />
          <TimelineItem
            v-else
            :date="dateTitle(row.dayGroup.date, row.dayGroup.events.length)"
            :is-today="row.isToday"
          >
            <TimelineCard v-for="watchfileEvent in row.dayGroup.events" :key="watchfileEvent.id">
              <AnalysisTimeline :watch-file-event="watchfileEvent" />
            </TimelineCard>
          </TimelineItem>
        </template>
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
import AnalysisTimeline from '@target/components/analysis/timeline/AnalysisTimeline.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import AnalysisTimelineSkeleton from '@target/components/skeletons/AnalysisTimelineSkeleton.vue'
import { useWatchFileAnalysisStore } from '@target/stores/watchFileAnalysis'
import type { WatchFileEvent } from '@target/types/watchFileEvent'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { d, t } = useI18n()

const route = useRoute('/target/(watch_files)/[id]/radar/timeline')

const watchFileId = computed(() => route.params.id)

const { allEvents, hasMore, isLoadingMore, loadMoreEvents, isInitialLoading } =
  useWatchFileEventsInfiniteQuery(watchFileId)

const watchFileAnalysisStore = useWatchFileAnalysisStore()
const filterQuery = computed(() => watchFileAnalysisStore.filterQuery)

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

const startOfDay = (date: Date): number =>
  new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime()

const splitIndex = computed(() => {
  const today = startOfDay(new Date())
  return eventsByDay.value.findIndex((day) => startOfDay(new Date(day.date)) <= today)
})

const todayISO = computed(() => new Date().toISOString().split('T')[0])

const hasTodayInTimeline = computed(() =>
  eventsByDay.value.some((day) => day.date === todayISO.value),
)

const shouldShowVirtualToday = computed(() => splitIndex.value > 0 && !hasTodayInTimeline.value)

type TimelineRow = { type: 'virtual-today' } | { type: 'day'; dayGroup: DayGroup; isToday: boolean }

const timelineRows = computed<TimelineRow[]>(() => {
  const rows: TimelineRow[] = []
  eventsByDay.value.forEach((dayGroup, idx) => {
    if (shouldShowVirtualToday.value && idx === splitIndex.value) {
      rows.push({ type: 'virtual-today' })
    }
    rows.push({ type: 'day', dayGroup, isToday: dayGroup.date === todayISO.value })
  })
  return rows
})

const dateTitle = (date: string, nbEvents: number): string => {
  const label = date === todayISO.value ? t('common.today') : d(date, 'eventDate')
  return nbEvents > 1 ? `${label} (${nbEvents})` : label
}

const scrollContainerRef = ref<HTMLElement>()
const hasScrolledToToday = ref(false)

const scrollToToday = () => {
  if (hasScrolledToToday.value || !scrollContainerRef.value) return
  if (splitIndex.value <= 0) {
    const top = scrollContainerRef.value.querySelector<HTMLElement>('[data-scroll-target="top"]')
    if (top) {
      top.scrollIntoView({
        behavior: 'smooth',
      })
    }
    hasScrolledToToday.value = true
    return
  }
  const target = scrollContainerRef.value.querySelector<HTMLElement>('[data-scroll-target="today"]')
  if (target) {
    target.scrollIntoView({
      behavior: 'smooth',
      block: 'center',
    })
    hasScrolledToToday.value = true
  }
}

watch(
  [isInitialLoading, eventsByDay],
  async ([loading, days]) => {
    if (!loading && days.length > 0 && !hasScrolledToToday.value) {
      await nextTick()
      scrollToToday()
    }
  },
  { immediate: true },
)

watch(
  filterQuery,
  () => {
    hasScrolledToToday.value = false
  },
  { deep: true },
)
</script>
