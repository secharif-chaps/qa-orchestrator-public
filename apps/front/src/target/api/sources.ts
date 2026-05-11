import type { SortOrder } from '@owlint/feathers-vue'
import { apiClient } from '@/api/client'
import type { DefaultErrorMessage } from '@target/types/api'
import type { JsonLdCollection } from '@target/types/jsonld'
import type {
  BatchChangeSourceStatusResponse,
  GroupedSourceActivityDto,
  Source,
  SourcesGroupedResponse,
  SourceStatus,
} from '@target/types/source'
const ROOT_URL = '/watch_files'

export const getWatchFileSourcesGrouped = async (watchFileId: string) => {
  return apiClient.get<SourcesGroupedResponse>(`${ROOT_URL}/${watchFileId}/sources/grouped`)
}

export const getSourceHistory = async (sourceId: string) => {
  return apiClient.get<GroupedSourceActivityDto>(`/sources/${sourceId}/history`)
}

export const getCollectionSource = async (
  watchFileId: string,
  params: {
    page?: number
    itemsPerPage?: number
    name?: string
    active?: boolean
    type?: string[]
    sortBy: string
    sortOrder: SortOrder
  },
) => {
  const { type, sortBy, sortOrder, ...restParams } = params
  const query: Record<string, string | number | string[] | boolean> = {
    ...restParams,
  }

  if (sortBy && sortBy.trim() !== '') {
    query[`order[${sortBy}]`] = sortOrder.toLowerCase()
  }

  if (type && type.length > 0) {
    query['type[]'] = type
  }

  const response = await apiClient.get<JsonLdCollection<Source>>(
    `${ROOT_URL}/${watchFileId}/sources`,
    {
      query,
    },
  )

  return {
    items: response.member,
    totalItems: response.totalItems,
  }
}

export const changeSourceStatus = async (
  watchFileId: string,
  sourceId: string,
  status: string,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  return apiClient.post<Source>(
    `${ROOT_URL}/${watchFileId}/sources/${sourceId}/change-status`,
    { status },
    { mediaType: 'ld+json', errorMessage: defaultErrorMessage },
  )
}

export const batchChangeSourceStatus = async (
  watchFileId: string,
  sources: Array<{ id: string }>,
  status: string,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  return apiClient.post<BatchChangeSourceStatusResponse>(
    `${ROOT_URL}/${watchFileId}/sources/batch-change-status`,
    {
      sources,
      status,
    },
    { mediaType: 'ld+json', errorMessage: defaultErrorMessage },
  )
}

export interface SourceTypesResponse {
  types: Record<string, number>
}

export const getSourceTypes = async (
  watchFileId: string,
  status?: SourceStatus.ACTIVE | SourceStatus.INACTIVE,
  name?: string,
) => {
  const query: Record<string, string> = {}
  if (status) {
    query.status = status
  }
  if (name) {
    query.name = name
  }
  return apiClient.get<SourceTypesResponse>(`${ROOT_URL}/${watchFileId}/source-types`, { query })
}
