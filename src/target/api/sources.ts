import type { SortOrder } from '@owlint/feathers-vue'
import { useApi } from '@target/composables/useApi'
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
  const response = await useApi().get<SourcesGroupedResponse>(
    `${ROOT_URL}/${watchFileId}/sources/grouped`,
  )
  return response.data
}

export const getSourceHistory = async (sourceId: string) => {
  const response = await useApi().get<GroupedSourceActivityDto>(`/sources/${sourceId}/history`)
  return response.data
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

  const response = await useApi().get<JsonLdCollection<Source>>(
    `${ROOT_URL}/${watchFileId}/sources`,
    {
      query,
    },
  )

  return {
    items: response.data.member,
    totalItems: response.data.totalItems,
  }
}

export const changeSourceStatus = async (
  watchFileId: string,
  sourceId: string,
  status: string,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  const response = await useApi().post<Source>(
    `${ROOT_URL}/${watchFileId}/source/${sourceId}/change-status/`,
    { status },
    { defaultErrorMessage },
  )
  return response.data
}

export const batchChangeSourceStatus = async (
  watchFileId: string,
  sources: Array<{ id: string }>,
  status: string,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  const response = await useApi().post<BatchChangeSourceStatusResponse>(
    `${ROOT_URL}/${watchFileId}/sources/batch-change-status`,
    {
      sources,
      status,
    },
    { defaultErrorMessage },
  )
  return response.data
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
  const response = await useApi().get<SourceTypesResponse>(
    `${ROOT_URL}/${watchFileId}/source-types`,
    { query },
  )
  return response.data
}
