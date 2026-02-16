import { defineQueryOptions } from '@pinia/colada';
import {
    getCollectionWatchFile,
    getItemWatchFile,
    getWatchFileTimeline,
} from '~/api/watchFile';
import type { WatchFileFilters } from '~/types/watchFile';

export const WATCH_FILE_QUERY_KEYS = {
  root: ['watchFiles'] as const,
  byId: (id: string) => [...WATCH_FILE_QUERY_KEYS.root, id] as const,
  withFilters: (filters: WatchFileFilters) =>
    [...WATCH_FILE_QUERY_KEYS.root, JSON.stringify(filters)] as const,
  timeline: (watchFileId: string) =>
    [...WATCH_FILE_QUERY_KEYS.root, 'timeline', watchFileId] as const,
};

export const getItemWatchFileQuery = defineQueryOptions(
  ({ id }: { id: string }) => ({
    key: WATCH_FILE_QUERY_KEYS.byId(id),
    query: () => getItemWatchFile(id),
    enabled: !!id,
  }),
);

export const getCollectionWatchFileQuery = defineQueryOptions(
  (filters: WatchFileFilters) => ({
    key: WATCH_FILE_QUERY_KEYS.withFilters(filters),
    query: () => getCollectionWatchFile(filters),
    enabled: !!filters.sortBy,
  }),
);

export const getWatchFileTimelineQuery = defineQueryOptions(
  ({ watchFileId }: { watchFileId: string }) => ({
    key: WATCH_FILE_QUERY_KEYS.timeline(watchFileId),
    query: () => getWatchFileTimeline(watchFileId),
    enabled: !!watchFileId,
  }),
);
