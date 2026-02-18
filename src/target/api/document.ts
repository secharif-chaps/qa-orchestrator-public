import { useApi } from '~/composables/useApi'
import { useDate } from '~/composables/useDate'
import type { DefaultErrorMessage } from '~/types/api'
import type {
  BatchValidationResponse,
  Document,
  DocumentFacets,
  DocumentQueryOptions,
  DocumentValidationAction,
  DocumentValidationResponse,
  FilterParams,
} from '~/types/document'
import { FilterDates } from '~/types/filter'
import type { JsonLdCollection } from '~/types/jsonld'

const ROOT_URL = '/watch_files'

export const getItemDocument = async (id: string) => {
  const response = await useApi().get<Document>(`/documents/${id}`)
  return response.data
}

export const markDocumentAsSeen = async (id: string, defaultErrorMessage: DefaultErrorMessage) => {
  const response = await useApi().post<Document>(
    `/documents/${id}/mark-seen`,
    {},
    { defaultErrorMessage },
  )
  return response.data
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

  const response = await useApi().get<JsonLdCollection<Document> & { facets?: DocumentFacets }>(
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
  const filteredFacets = response.data.facets
    ? {
        ...response.data.facets,
        validationStatuses: response.data.facets.validationStatuses
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
    items: response.data.member,
    totalItems: response.data.totalItems,
    facets: filteredFacets,
  }
}

export const documentValidation = async (
  documentId: string,
  action: DocumentValidationAction,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  const response = await useApi().post<DocumentValidationResponse>(
    `documents/${documentId}/manual-validate`,
    { action },
    { defaultErrorMessage },
  )

  return response.data
}

export const batchDocumentValidation = async (
  documentIds: string[],
  action: DocumentValidationAction,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  const response = await useApi().post<BatchValidationResponse>(
    'documents/batch-manual-validate',
    {
      document_ids: documentIds,
      action,
    },
    { defaultErrorMessage },
  )

  return response.data
}

const buildFilterQuery = (filters: FilterParams): Record<string, string | number | string[]> => {
  const query: Record<string, string | number | string[]> = {}
  const { getPeriodDates, convertDateStringToDate, formatToISOWithTimezone } = useDate()
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
