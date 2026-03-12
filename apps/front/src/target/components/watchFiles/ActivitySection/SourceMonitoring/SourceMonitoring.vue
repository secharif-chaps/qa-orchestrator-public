<template>
  <Timeline :days="timelineDays" :is-loading="isLoading" />
</template>

<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { getSourceHistoryQuery } from '@target/api/queries/sources'
import Timeline from '@target/components/watchFiles/ActivitySection/Timeline/Timeline.vue'
import { useActivityDescription } from '@target/composables/useActivityDescription'
import type { SourceActivity } from '@target/types/source'
import { SourceActionType, SourceStatus } from '@target/types/source'
import type { SourceActivityDescription } from '@target/types/timeline'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { d } = useI18n()
const { createSourceActivityDescription } = useActivityDescription()

interface Props {
  sourceId: string
}

const props = defineProps<Props>()
const currentSourceId = computed(() => props.sourceId)

const { data, isLoading } = useQuery(() =>
  getSourceHistoryQuery({
    sourceId: currentSourceId.value!,
  }),
)

const timelineDays = computed(() => {
  if (!data.value) return []

  const apiData = data.value
  return Object.entries(apiData.activitiesByDay)
    .map(([date, activities]) => ({
      date,
      activities: (activities as SourceActivity[])
        .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
        .map((activity, index) => ({
          id: `${date}-${index}`,
          time: d(activity.createdAt, 'time'),
          message: getActivityDescription(activity),
          type: activity.actionType,
          color: getActivityColor(activity),
          icon: getActivityIcon(activity),
        })),
    }))
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
})

// Get activity color based on action type
const getActivityColor = (activity: SourceActivity): string => {
  const { actionType, actionData } = activity

  const colorMap: Record<SourceActionType, string> = {
    [SourceActionType.SOURCE_CONNECTED]: 'bg-green-200',
    [SourceActionType.SOURCE_ERROR]: 'bg-red-200',
    [SourceActionType.SOURCE_RECOVERED]: 'bg-green-200',
    [SourceActionType.SOURCE_CONFIG_UPDATED]: 'bg-gray-200',
    [SourceActionType.SOURCE_ADDED_TO_WATCHFILE]: 'bg-gray-200',
    [SourceActionType.SOURCE_STATUS_CHANGED]:
      actionData.new_status === SourceStatus.ACTIVE ? 'bg-green-200' : 'bg-red-200',
    [SourceActionType.SOURCE_COLLECT_STATUS_CHANGED]: 'bg-gray-200',
  }
  return colorMap[actionType] || 'bg-gray-200'
}

// Get activity icon based on action type
const getActivityIcon = (activity: SourceActivity): string => {
  const { actionType, actionData } = activity
  const iconMap: Record<SourceActionType, string> = {
    [SourceActionType.SOURCE_CONNECTED]: 'fa-check',
    [SourceActionType.SOURCE_ERROR]: 'fa-exclamation',
    [SourceActionType.SOURCE_RECOVERED]: 'fa-check',
    [SourceActionType.SOURCE_CONFIG_UPDATED]: 'fa-gear',
    [SourceActionType.SOURCE_ADDED_TO_WATCHFILE]: 'fa-plus',
    [SourceActionType.SOURCE_STATUS_CHANGED]:
      actionData.new_status === SourceStatus.ACTIVE ? 'fa-check' : 'fa-exclamation',
    [SourceActionType.SOURCE_COLLECT_STATUS_CHANGED]: 'fa-circle-info',
  }
  return iconMap[actionType] || 'fa-circle-info'
}

// Get activity description based on action type and data
const getActivityDescription = (activity: SourceActivity): SourceActivityDescription => {
  const { actionType } = activity

  switch (actionType) {
    case SourceActionType.SOURCE_CONNECTED:
    case SourceActionType.SOURCE_ERROR:
    case SourceActionType.SOURCE_RECOVERED:
    case SourceActionType.SOURCE_CONFIG_UPDATED:
    case SourceActionType.SOURCE_ADDED_TO_WATCHFILE:
    case SourceActionType.SOURCE_STATUS_CHANGED:
      return createSourceActivityDescription(
        `watch_files.activity.sources.history.${actionType}`,
        activity,
      )

    default:
      return createSourceActivityDescription(
        'watch_files.activity.sources.history.unknown_action',
        activity,
      )
  }
}
</script>
