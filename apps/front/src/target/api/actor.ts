import type { SortOrder } from '@owlint/feathers-vue'
import { apiClient } from '@/api/client'
import type {
  Actor,
  ActorFilters,
  ActorStatus,
  ActorTypesResponse,
  BatchChangeActorStatusResponse,
} from '@target/types/actor'
import type { DefaultErrorMessage } from '@target/types/api'
import type { JsonLdCollection } from '@target/types/jsonld'
import type { Source } from '@target/types/source'
import type { WatchFileActor } from '@target/types/watchFile'

export const getCollectionActor = async ({
  watchFileId,
  status,
  search,
  type,
  sortBy = 'actor.label',
  sortOrder = 'ASC',
  ...params
}: ActorFilters & { watchFileId: string }) => {
  const query: Record<string, string | number | string[]> = {
    [`order[${sortBy}]`]: sortOrder.toLowerCase(),
    ...params,
  }

  // Add status filter if provided
  if (status) {
    query.status = status
  }

  // Add search filter if provided (maps to actor.label)
  if (search) {
    query['actor.label'] = search
  }

  // Add type filter if provided (can be single type or array of types)
  if (type) {
    if (Array.isArray(type) && type.length > 0) {
      // For multiple types, add each type as a separate parameter
      type.forEach((t, index) => {
        query[index === 0 ? 'type[]' : `type[${index}]`] = t
      })
    } else if (typeof type === 'string') {
      // For single type
      query.type = type
    }
  }

  const response = await apiClient.get<JsonLdCollection<WatchFileActor>>(
    `/watch_files/${watchFileId}/actors`,
    {
      query,
    },
  )

  return {
    items: response.member || [],
    totalItems: response.totalItems || 0,
  }
}

export const getItemActor = async (watchFileId: string, actorId: string) => {
  return apiClient.get<Actor>(`/watch_files/${watchFileId}/actors/${actorId}`)
}

export const getActorSources = async (
  watchFileId: string,
  actorId: string,
  page: number,
  itemsPerPage: number,
  sortBy: string,
  sortOrder: SortOrder,
) => {
  const query: Record<string, string | number> = {}

  if (page !== undefined) {
    query.page = page
  }

  if (itemsPerPage !== undefined) {
    query.itemsPerPage = itemsPerPage
  }

  if (sortBy !== undefined && sortBy.trim() !== '') {
    query[`order[${sortBy}]`] = sortOrder.toLowerCase()
  }

  const response = await apiClient.get<JsonLdCollection<Source>>(
    `/watch_files/${watchFileId}/actors/${actorId}/sources`,
    {
      query,
    },
  )

  if (response && typeof response === 'object' && 'member' in response) {
    return {
      items: response.member,
      totalItems: response.totalItems || 0,
    }
  }

  return {
    items: response,
    totalItems: 0,
  }
}

export const changeActorStatus = async (
  watchFileId: string,
  actorId: string,
  status: ActorStatus,
  sourceIds?: string[],
  defaultErrorMessage?: DefaultErrorMessage,
) => {
  return apiClient.post(
    `/watch_files/${watchFileId}/actors/${actorId}/status`,
    {
      sourceIds: sourceIds || [],
      status,
      newStatus: false,
    },
    { mediaType: 'ld+json', errorMessage: defaultErrorMessage },
  )
}

export const removeWatchFileActor = async (
  watchFileId: string,
  actorId: string,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  await apiClient.delete(`/watch_files/${watchFileId}/actors/${actorId}`, {
    errorMessage: defaultErrorMessage,
  })
}

export const getActorTypes = async (
  watchFileId: string,
  status?: ActorStatus.ACTIVE | ActorStatus.INACTIVE,
  name?: string,
) => {
  const query: Record<string, string> = {}
  if (status) {
    query.status = status
  }
  if (name) {
    query.name = name
  }
  return apiClient.get<ActorTypesResponse>(`/watch_files/${watchFileId}/actor-types`, { query })
}

export const batchChangeActorStatus = async (
  watchFileId: string,
  actors: Array<{ id: string; sourceIds: string[] }>,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  return apiClient.post<BatchChangeActorStatusResponse>(
    `/watch_files/${watchFileId}/actors/batch-change-status`,
    {
      actors,
    },
    { mediaType: 'ld+json', errorMessage: defaultErrorMessage },
  )
}
