import { convertDateStringToDate, formatToISOWithTimezone, getPeriodDates } from '@/utils/date'
import { useApi } from '@target/composables/useApi'
import type {
  AnalysisCollectionParams,
  Document,
  DocumentFacets,
  FacetsParams,
  FilterParams,
} from '@target/types/document'
import { FilterDates } from '@target/types/filter'
import type { JsonLdCollection } from '@target/types/jsonld'

const ROOT_URL = '/watch_files'

export const getCollectionAnalysis = async ({
  sortBy,
  sortOrder,
  watchFileId,
  actors,
  sources,
  status,
  datesPickerEnd,
  datesPickerStart,
  selectedDateType,
  selectedPeriod,
  ...params
}: AnalysisCollectionParams) => {
  const query: Record<string, string | number | string[]> = {
    [`sort[${sortBy}]`]: sortOrder.toLowerCase(),
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

  const response = await useApi().get<JsonLdCollection<Document>>(
    `${ROOT_URL}/${watchFileId}/analysis`,
    {
      query,
    },
  )

  return {
    items: response.data.member,
    totalItems: response.data.totalItems,
  }
}
export const getAnalysisFacets = async ({
  watchFileId,
  actors,
  sources,
  status,
  datesPickerEnd,
  datesPickerStart,
  selectedDateType,
  selectedPeriod,
  ...params
}: FacetsParams) => {
  const query: Record<string, string | number | string[]> = {
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

  const response = await useApi().get<DocumentFacets>(
    `${ROOT_URL}/${watchFileId}/analysis/facets`,
    {
      query,
    },
  )
  return {
    actors: response.data.actors,
    sources: response.data.sources,
    statuses: response.data.statuses,
  }
}

const buildFilterQuery = (filters: FilterParams): Record<string, string | number | string[]> => {
  const query: Record<string, string | number | string[]> = {}
  if (filters.actors?.length) {
    query['actor.id[]'] = filters.actors
  }
  if (filters.sources?.length) {
    query['source.id[]'] = filters.sources
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
