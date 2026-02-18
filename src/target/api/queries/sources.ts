import type { SortOrder } from '@owlint/feathers-vue'
import { defineQueryOptions } from '@pinia/colada'
import {
  getCollectionSource,
  getSourceHistory,
  getSourceTypes,
  getWatchFileSourcesGrouped,
} from '~/api/sources'
import type { SourceStatus } from '~/types/source'

export const SOURCES_QUERY_KEYS = {
  root: ['sources'] as const,
  byWatchFile: (watchFileId: string) =>
    [...SOURCES_QUERY_KEYS.root, 'watchFile', watchFileId] as const,
  withFilters: (
    watchFileId: string,
    filters: Record<string, string | number | boolean | string[]>,
  ) => [...SOURCES_QUERY_KEYS.byWatchFile(watchFileId), JSON.stringify(filters)] as const,
  grouped: (watchFileId: string) => [...SOURCES_QUERY_KEYS.root, 'grouped', watchFileId] as const,
  history: (sourceId: string) => [...SOURCES_QUERY_KEYS.root, 'history', sourceId] as const,
  types: (
    watchFileId: string,
    status?: SourceStatus.ACTIVE | SourceStatus.INACTIVE,
    name?: string,
  ) =>
    [...SOURCES_QUERY_KEYS.byWatchFile(watchFileId), 'types', status || 'all', name || ''] as const,
}

export const getWatchFileSourcesGroupedQuery = defineQueryOptions(
  ({ watchFileId }: { watchFileId: string }) => ({
    key: SOURCES_QUERY_KEYS.grouped(watchFileId),
    query: () => getWatchFileSourcesGrouped(watchFileId),
    enabled: !!watchFileId,
  }),
)

export const getSourceHistoryQuery = defineQueryOptions(({ sourceId }: { sourceId: string }) => ({
  key: SOURCES_QUERY_KEYS.history(sourceId),
  query: () => getSourceHistory(sourceId),
  enabled: !!sourceId,
}))

export const getCollectionSourceQuery = defineQueryOptions(
  (filters: {
    watchFileId: string
    page?: number
    itemsPerPage?: number
    name?: string
    active?: boolean
    type?: string[]
    sortBy: string
    sortOrder: SortOrder
  }) => {
    const { watchFileId, sortBy, sortOrder, ...queryFilters } = filters
    return {
      key: SOURCES_QUERY_KEYS.withFilters(watchFileId, {
        sortBy,
        sortOrder,
        ...queryFilters,
      }),
      query: () =>
        getCollectionSource(watchFileId, {
          page: filters.page,
          itemsPerPage: filters.itemsPerPage,
          name: filters.name,
          active: filters.active,
          type: filters.type,
          sortBy,
          sortOrder,
        }),
      enabled: !!watchFileId,
    }
  },
)

export const getSourceTypesQuery = defineQueryOptions(
  ({
    watchFileId,
    status,
    name,
  }: {
    watchFileId: string
    status?: SourceStatus.ACTIVE | SourceStatus.INACTIVE
    name?: string
  }) => ({
    key: SOURCES_QUERY_KEYS.types(watchFileId, status, name),
    query: () => getSourceTypes(watchFileId, status, name),
    enabled: !!watchFileId,
  }),
)
