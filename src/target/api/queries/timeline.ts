import { defineQueryOptions } from '@pinia/colada'
import type { TimelineEventParams } from '~/types/timeline'
import { getWatchFileTimelineEventActors, getWatchFileTimelineEventSources } from '../timeline'

export const TIMELINE_QUERY_KEYS = {
  root: ['timeline'] as const,
  eventActors: (watchFileId: string, eventId: string) =>
    [...TIMELINE_QUERY_KEYS.root, watchFileId, 'event', eventId, 'actors'] as const,
  eventSources: (watchFileId: string, eventId: string) =>
    [...TIMELINE_QUERY_KEYS.root, watchFileId, 'event', eventId, 'sources'] as const,
}

export const getWatchFileTimelineEventActorsQuery = defineQueryOptions(
  ({ watchFileId, eventId }: TimelineEventParams) => ({
    key: TIMELINE_QUERY_KEYS.eventActors(watchFileId, eventId),
    query: () => getWatchFileTimelineEventActors({ watchFileId, eventId }),
    enabled: !!eventId,
  }),
)
export const getWatchFileTimelineEventSourcesQuery = defineQueryOptions(
  ({ watchFileId, eventId }: TimelineEventParams) => ({
    key: TIMELINE_QUERY_KEYS.eventSources(watchFileId, eventId),
    query: () => getWatchFileTimelineEventSources({ watchFileId, eventId }),
    enabled: !!eventId,
  }),
)
