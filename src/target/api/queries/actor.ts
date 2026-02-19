import type { SortOrder } from '@owlint/feathers-vue'
import { defineQueryOptions } from '@pinia/colada'
import { getActorSources, getActorTypes, getCollectionActor, getItemActor } from '@target/api/actor'
import type { ActorFilters, ActorStatus } from '@target/types/actor'

export const ACTOR_QUERY_KEYS = {
  root: ['actors'] as const,
  byWatchFile: (watchFileId: string) =>
    [...ACTOR_QUERY_KEYS.root, 'watchFile', watchFileId] as const,
  withFilters: (watchFileId: string, filters: ActorFilters) =>
    [...ACTOR_QUERY_KEYS.byWatchFile(watchFileId), JSON.stringify(filters)] as const,
  byId: (watchFileId: string, actorId: string) =>
    [...ACTOR_QUERY_KEYS.byWatchFile(watchFileId), actorId] as const,
  sources: (watchFileId: string, actorId: string) =>
    [...ACTOR_QUERY_KEYS.byId(watchFileId, actorId), 'sources'] as const,
  types: (watchFileId: string, status?: string, name?: string) =>
    [...ACTOR_QUERY_KEYS.byWatchFile(watchFileId), 'types', status || 'all', name || ''] as const,
}

export const getCollectionActorQuery = defineQueryOptions(
  (filters: ActorFilters & { watchFileId: string }) => ({
    key: ACTOR_QUERY_KEYS.withFilters(filters.watchFileId, filters),
    query: () => getCollectionActor(filters),
    enabled: !!filters.watchFileId,
  }),
)

export const getItemActorQuery = defineQueryOptions(
  ({ watchFileId, actorId }: { watchFileId: string; actorId: string }) => ({
    key: ACTOR_QUERY_KEYS.byId(watchFileId, actorId),
    query: () => getItemActor(watchFileId, actorId),
    enabled: !!watchFileId && !!actorId,
  }),
)

export const getActorSourcesQuery = defineQueryOptions(
  ({
    watchFileId,
    actorId,
    page,
    itemsPerPage,
    sortBy,
    sortOrder,
  }: {
    watchFileId: string
    actorId: string
    page: number
    itemsPerPage: number
    sortBy: string
    sortOrder: SortOrder
  }) => ({
    key: [
      ...ACTOR_QUERY_KEYS.sources(watchFileId, actorId),
      { page, itemsPerPage, sortBy, sortOrder },
    ],
    query: () => getActorSources(watchFileId, actorId, page, itemsPerPage, sortBy, sortOrder),
    enabled: !!watchFileId && !!actorId,
  }),
)

export const getActorTypesQuery = defineQueryOptions(
  ({
    watchFileId,
    status,
    name,
  }: {
    watchFileId: string
    status?: ActorStatus.ACTIVE | ActorStatus.INACTIVE
    name?: string
  }) => ({
    key: ACTOR_QUERY_KEYS.types(watchFileId, status, name),
    query: () => getActorTypes(watchFileId, status, name),
    enabled: !!watchFileId,
  }),
)
