import { defineQueryOptions } from '@pinia/colada'
import { getEventTypes, getStreamsByFolder, getStreamById, getDeliveries } from '@/api/streams'

export const STREAM_QUERY_KEYS = {
  root: ['streams'] as const,
  byFolder: (folderId: string, page: number, perPage: number) =>
    [...STREAM_QUERY_KEYS.root, 'folder', folderId, { page, perPage }] as const,
  byId: (streamId: string) => [...STREAM_QUERY_KEYS.root, streamId] as const,
  deliveriesRoot: (streamId: string) =>
    [...STREAM_QUERY_KEYS.root, streamId, 'deliveries'] as const,
  deliveries: (streamId: string, page: number, perPage: number) =>
    [...STREAM_QUERY_KEYS.root, streamId, 'deliveries', { page, perPage }] as const,
  eventTypes: () => ['event-types'] as const,
}

export const eventTypesQuery = defineQueryOptions(() => ({
  key: STREAM_QUERY_KEYS.eventTypes(),
  query: () => getEventTypes(),
  staleTime: 1000 * 60 * 5, // 5 minutes — event catalog rarely changes
}))

export const streamsByFolderQuery = defineQueryOptions(
  ({ folderId, page, perPage }: { folderId: string; page: number; perPage: number }) => ({
    key: STREAM_QUERY_KEYS.byFolder(folderId, page, perPage),
    enabled: !!folderId,
    query: () => getStreamsByFolder(folderId, { page, per_page: perPage }),
  }),
)

export const streamByIdQuery = defineQueryOptions(({ streamId }: { streamId: string }) => ({
  key: STREAM_QUERY_KEYS.byId(streamId),
  enabled: !!streamId,
  query: () => getStreamById(streamId),
}))

export const deliveriesQuery = defineQueryOptions(
  ({ streamId, page, perPage }: { streamId: string; page: number; perPage: number }) => ({
    key: STREAM_QUERY_KEYS.deliveries(streamId, page, perPage),
    enabled: !!streamId,
    query: () => getDeliveries(streamId, { page, per_page: perPage }),
  }),
)
