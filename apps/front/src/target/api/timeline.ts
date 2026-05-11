import { apiClient } from '@/api/client'
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
  return apiClient.get<TimelineEventActors>(`${ROOT_URL}/${watchFileId}/timeline/${eventId}/actors`)
}
export const getWatchFileTimelineEventSources = async ({
  watchFileId,
  eventId,
}: TimelineEventParams) => {
  return apiClient.get<TimelineEventSources>(
    `${ROOT_URL}/${watchFileId}/timeline/${eventId}/sources`,
  )
}
