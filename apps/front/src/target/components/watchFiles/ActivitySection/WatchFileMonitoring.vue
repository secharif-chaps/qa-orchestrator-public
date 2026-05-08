<template>
  <div class="border-sage-100 flex h-full flex-col rounded-2xl border p-6 shadow-md">
    <div class="mb-3 flex shrink-0 items-center gap-2 pl-3">
      <Icon icon="fa-clock-rotate-left" class="text-medium text-gray-600" />
      <div class="text-medium text-gray-900">
        {{ t('target.watchFiles.activity.history.title') }}
      </div>
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto">
      <Timeline
        :days="timelineDays"
        :is-loading="isLoading"
        :has-next-page="hasNextPage"
        :is-loading-more="isLoadingMore"
        @load-more="loadMoreActivities"
      />
    </div>

    <HistoryTimelineEventDetail
      v-model:is-open="isDrawerOpen"
      :watch-file-id="watchFileId"
      :actor-event-id="actorEventId"
      :source-event-id="sourceEventId"
      :event-type="eventType"
    />
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@owlint/feathers-vue'
import { useInfiniteQuery } from '@pinia/colada'
import { getWatchFileTimeline } from '@target/api/watchFile'
import HistoryTimelineEventDetail from '@target/components/watchFiles/ActivitySection/Timeline/HistoryTimelineEventDetail.vue'
import Timeline from '@target/components/watchFiles/ActivitySection/Timeline/Timeline.vue'
import { useActivityDescription } from '@target/composables/useActivityDescription'
import type {
  TimelineActivity,
  TimelineDay,
  WatchFileActivityDescription,
} from '@target/types/timeline'
import type { GroupedWatchFileActivityDto, WatchFileActivity } from '@target/types/watchFile'
import { WatchFileEventType } from '@target/types/watchFile'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  watchFileId?: string
}

const { watchFileId = '' } = defineProps<Props>()
const { t, d } = useI18n()
const { createWatchFileActivityDescription } = useActivityDescription()

const actorEventId = ref('')
const sourceEventId = ref('')
const eventType = ref<WatchFileEventType>()

const currentWatchFileId = computed(() => watchFileId)
const isDrawerOpen = ref(false)

const {
  data,
  loadNextPage,
  hasNextPage: hasNextPageRef,
  asyncStatus,
} = useInfiniteQuery({
  key: computed(() => ['watchFiles', 'timeline', currentWatchFileId.value || '']),
  query: async ({ pageParam }: { pageParam: number }) => {
    if (!currentWatchFileId.value) return null
    return getWatchFileTimeline(currentWatchFileId.value, {
      page: pageParam,
    })
  },
  enabled: computed(() => !!currentWatchFileId.value),
  initialPageParam: 1,
  getNextPageParam: (
    lastPage: GroupedWatchFileActivityDto | null,
    _allPages: unknown,
    lastPageParam: number,
  ) => (lastPage?.hasNextPage ? lastPageParam + 1 : undefined),
})

const transformActivityToTimelineActivity = (activity: WatchFileActivity): TimelineActivity => {
  const eventType = mapActionTypeToEventType(activity.actionType)

  return {
    id: activity.id,
    time: d(new Date(activity.createdAt), 'time'),
    message: getActivityDescription(activity),
    type: eventType,
    color: 'bg-sage-100',
    icon: getEventIcon(eventType),
    user: {
      '@id': activity.user['@id'],
      '@type': activity.user['@type'],
      id: activity.user.id,
      email: activity.user.email,
    },
    button: getActivityButton(activity),
  }
}

const mapActionTypeToEventType = (actionType: string): WatchFileEventType => {
  const actionTypeMap: Record<string, WatchFileEventType> = {
    created: WatchFileEventType.WATCHFILE_CREATED,
    updated: WatchFileEventType.WATCHFILE_UPDATED,
    status_changed: WatchFileEventType.WATCHFILE_STATUS_CHANGED,
    source_status_changed: WatchFileEventType.WATCHFILE_SOURCE_STATUS_CHANGED,
    actor_status_changed: WatchFileEventType.WATCHFILE_ACTOR_STATUS_CHANGED,
    monitoring_type_detected: WatchFileEventType.WATCHFILE_MONITORING_TYPE_DETECTED,
    reference_subject_detected: WatchFileEventType.WATCHFILE_REFERENCE_SUBJECT_UPDATED,
    shared_mode_changed: WatchFileEventType.WATCHFILE_SHARED_MODE_CHANGED,
    actor_added: WatchFileEventType.WATCHFILE_ACTOR_ADDED,
    source_added: WatchFileEventType.WATCHFILE_SOURCE_ADDED,
  }

  return actionTypeMap[actionType] || WatchFileEventType.WATCHFILE_UPDATED
}

const getActivityDescription = (activity: WatchFileActivity): WatchFileActivityDescription => {
  const { actionType } = activity

  switch (actionType) {
    case 'created':
      return createWatchFileActivityDescription(
        'target.watchFiles.activity.history.created',
        activity,
      )
    case 'updated':
      return createWatchFileActivityDescription(
        'target.watchFiles.activity.history.updated',
        activity,
      )
    case 'source_status_changed':
      return createWatchFileActivityDescription(
        'target.watchFiles.activity.history.source_status_changed',
        activity,
      )
    case 'actor_status_changed':
      return createWatchFileActivityDescription(
        'target.watchFiles.activity.history.actor_status_changed',
        activity,
      )
    case 'shared_mode_changed':
      return createWatchFileActivityDescription(
        'target.watchFiles.activity.history.shared_mode_changed',
        activity,
      )
    case 'actor_added':
      return createWatchFileActivityDescription(
        'target.watchFiles.activity.history.actor_added',
        activity,
      )
    case 'source_added':
      return createWatchFileActivityDescription(
        'target.watchFiles.activity.history.source_added',
        activity,
      )
    case 'monitoring_type_detected':
      return createWatchFileActivityDescription(
        'target.watchFiles.activity.history.monitoring_type_detected',
        activity,
      )
    case 'reference_subject_updated':
      return createWatchFileActivityDescription(
        'target.watchFiles.activity.history.reference_subject_updated',
        activity,
      )

    case 'status_changed':
      return createWatchFileActivityDescription(
        'target.watchFiles.activity.history.status_changed',
        activity,
        'TimelineItemWatchFileDescriptionStatusChanged',
      )

    default:
      return createWatchFileActivityDescription(
        'target.watchFiles.activity.history.unknown_action',
        activity,
      )
  }
}

const getActivityButton = (activity: WatchFileActivity) => {
  const activityEventType = mapActionTypeToEventType(activity.actionType)

  switch (activityEventType) {
    case WatchFileEventType.WATCHFILE_ACTOR_ADDED:
    case WatchFileEventType.WATCHFILE_ACTOR_STATUS_CHANGED:
      return {
        text: t('target.watchFiles.activity.history.view_actors_added'),
        action: () => {
          eventType.value = activityEventType
          actorEventId.value = activity.id
          sourceEventId.value = ''
          isDrawerOpen.value = true
        },
      }

    case WatchFileEventType.WATCHFILE_SOURCE_ADDED:
    case WatchFileEventType.WATCHFILE_SOURCE_STATUS_CHANGED:
      return {
        text: t('target.watchFiles.activity.history.view_sources_added'),
        action: () => {
          eventType.value = activityEventType
          sourceEventId.value = activity.id
          actorEventId.value = ''
          isDrawerOpen.value = true
        },
      }

    default:
      return undefined
  }
}

const timelineDays = computed((): TimelineDay[] => {
  if (!data.value?.pages) return []

  // Merge activitiesByDay from all pages
  const mergedActivitiesByDay: Record<string, WatchFileActivity[]> = {}
  for (const page of data.value.pages) {
    if (!page || !('activitiesByDay' in page)) continue
    for (const [date, activities] of Object.entries(page.activitiesByDay)) {
      if (mergedActivitiesByDay[date]) {
        mergedActivitiesByDay[date] = [...mergedActivitiesByDay[date], ...activities]
      } else {
        mergedActivitiesByDay[date] = activities
      }
    }
  }

  return Object.entries(mergedActivitiesByDay)
    .map(([date, activities]) => ({
      date,
      activities: activities.map(transformActivityToTimelineActivity),
    }))
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
})

const hasNextPage = hasNextPageRef

const isLoading = computed(() => {
  return asyncStatus.value === 'loading' && !timelineDays.value.length
})
const isLoadingMore = computed(() => asyncStatus.value === 'loading')

const getEventIcon = (eventType: WatchFileEventType): string => {
  const iconMap: Record<WatchFileEventType, string> = {
    [WatchFileEventType.WATCHFILE_CREATED]: 'fa-plus',
    [WatchFileEventType.WATCHFILE_UPDATED]: 'fa-pen',
    [WatchFileEventType.WATCHFILE_STATUS_CHANGED]: 'fa-play',
    [WatchFileEventType.WATCHFILE_SOURCE_STATUS_CHANGED]: 'fa-link',
    [WatchFileEventType.WATCHFILE_ACTOR_STATUS_CHANGED]: 'fa-user',
    [WatchFileEventType.WATCHFILE_MONITORING_TYPE_DETECTED]: 'fa-eye',
    [WatchFileEventType.WATCHFILE_REFERENCE_SUBJECT_UPDATED]: 'fa-tag',
    [WatchFileEventType.WATCHFILE_SHARED_MODE_CHANGED]: 'fa-share',
    [WatchFileEventType.WATCHFILE_ACTOR_ADDED]: 'fa-user-plus',
    [WatchFileEventType.WATCHFILE_SOURCE_ADDED]: 'fa-link',
  }
  return iconMap[eventType] || 'fa-circle-info'
}

const loadMoreActivities = () => {
  if (!currentWatchFileId.value || isLoadingMore.value) return
  loadNextPage()
}
</script>
