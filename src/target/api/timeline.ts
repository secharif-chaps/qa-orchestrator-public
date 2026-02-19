import { useApi } from '@target/composables/useApi'
import type {
    TimelineEventActors,
    TimelineEventParams,
    TimelineEventSources,
} from '@target/types/timeline'

const ROOT_URL = '/watch_files'

export const getWatchFileTimelineEventActors = async ({
  watchFileId,
  eventId,
}: TimelineEventParams) => {
  const response = await useApi().get<TimelineEventActors>(
    `${ROOT_URL}/${watchFileId}/timeline/${eventId}/actors`,
  )
  return response.data
}
export const getWatchFileTimelineEventSources = async ({
  watchFileId,
  eventId,
}: TimelineEventParams) => {
  const response = await useApi().get<TimelineEventSources>(
    `${ROOT_URL}/${watchFileId}/timeline/${eventId}/sources`,
  )
  return response.data
}
