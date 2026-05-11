import { apiClient } from '@/api/client'
import { convertDateStringToDate, formatToISOWithTimezone, getPeriodDates } from '@/utils/date'
import type { DefaultErrorMessage } from '@target/types/api'
import type {
  BatchValidationResponse,
  Document,
  DocumentFacets,
  DocumentQueryOptions,
  DocumentValidationAction,
  DocumentValidationResponse,
  FilterParams,
} from '@target/types/document'
import { FilterDates } from '@target/types/filter'
import type { JsonLdCollection } from '@target/types/jsonld'

const ROOT_URL = '/watch_files'

export const getItemDocument = async (id: string) => {
  return apiClient.get<Document>(`/documents/${id}`)
}

export const markDocumentAsSeen = async (id: string, defaultErrorMessage: DefaultErrorMessage) => {
  return apiClient.post<Document>(
    `/documents/${id}/mark-seen`,
    {},
    { mediaType: 'ld+json', errorMessage: defaultErrorMessage },
  )
}

export const getCollectionDocument = async ({
  watchFileId,
  filters: {
    actors,
    sortBy,
    sortOrder,
    sources,
    status,
    datesPickerEnd,
    datesPickerStart,
    selectedDateType,
    selectedPeriod,
    ...params
  },
}: DocumentQueryOptions) => {
  const query: Record<string, string | number | string[]> = {
    [`order[${sortBy}]`]: sortOrder.toLowerCase(),
    ...params,
    ...buildFilterQuery({
      actors,
      sources,
      status,
      datesPickerEnd,
      datesPickerStart,
      selectedDateType,
      selectedPeriod,
    }),
  }

  const response = await apiClient.get<JsonLdCollection<Document> & { facets?: DocumentFacets }>(
    `${ROOT_URL}/${watchFileId}/documents`,
    {
      query,
    },
  )

  // This is a temporary workaround to sort and filter validation statuses.
  // This should be done in the API response, and AI validation statuses should be
  // separated from manual validation statuses.
  const rejectedValidationStatuses = ['ai_empty', 'ai_failed', 'ai_pending']
  const validationStatusOrder = [
    'ai_validated',
    'ai_rejected',
    'ai_uncertain',
    'manual_accept',
    'manual_refuse',
    'manual_empty',
  ]
  const filteredFacets = response.facets
    ? {
        ...response.facets,
        validationStatuses: response.facets.validationStatuses
          ?.filter((facet) => !rejectedValidationStatuses.includes(facet.status))
          .sort((a, b) => {
            const indexA = validationStatusOrder.indexOf(a.status)
            const indexB = validationStatusOrder.indexOf(b.status)
            // If status not found in order, put it at the end
            if (indexA === -1 && indexB === -1) return 0
            if (indexA === -1) return 1
            if (indexB === -1) return -1
            return indexA - indexB
          }),
      }
    : undefined

  return {
    items: response.member,
    totalItems: response.totalItems,
    facets: filteredFacets,
  }
}

export const documentValidation = async (
  documentId: string,
  action: DocumentValidationAction,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  return apiClient.post<DocumentValidationResponse>(
    `documents/${documentId}/manual-validate`,
    { action },
    { mediaType: 'ld+json', errorMessage: defaultErrorMessage },
  )
}

export const batchDocumentValidation = async (
  documentIds: string[],
  action: DocumentValidationAction,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  return apiClient.post<BatchValidationResponse>(
    'documents/batch-manual-validate',
    {
      document_ids: documentIds,
      action,
    },
    { mediaType: 'ld+json', errorMessage: defaultErrorMessage },
  )
}

const buildFilterQuery = (filters: FilterParams): Record<string, string | number | string[]> => {
  const query: Record<string, string | number | string[]> = {}
  if (filters.actors?.length) {
    query['actor.id[]'] = filters.actors
  }
  if (filters.sources?.length) {
    query['source.id[]'] = filters.sources
  }
  if (filters.status?.length) {
    query['validationStatus[]'] = filters.status
  }

  let startDate: Date | undefined = undefined
  let endDate: Date | undefined = undefined
  if (filters.selectedPeriod) {
    const periodDates = getPeriodDates(filters.selectedPeriod)
    startDate = periodDates.start
    endDate = periodDates.end
  } else if (filters.datesPickerEnd || filters.datesPickerStart) {
    startDate = convertDateStringToDate(filters.datesPickerStart)
    endDate = convertDateStringToDate(filters.datesPickerEnd)
  }

  if (filters.selectedDateType === FilterDates.PUBLICATION) {
    if (startDate) {
      query['datePublish[after]'] = formatToISOWithTimezone(startDate, false)
    }
    if (endDate) {
      query['datePublish[before]'] = formatToISOWithTimezone(endDate, true)
    }
  } else {
    if (startDate) {
      query['dateCollect[after]'] = formatToISOWithTimezone(startDate, false)
    }
    if (endDate) {
      query['dateCollect[before]'] = formatToISOWithTimezone(endDate, true)
    }
  }

  return query
}
