import { convertDateStringToDate, formatToISOWithTimezone, getPeriodDates } from '@/utils/date'
import { useApi } from '@target/composables/useApi'
import type { AnalysisFacets } from '@target/types/facet'
import { DatesPeriod } from '@target/types/filter'
import type { JsonLdCollection } from '@target/types/jsonld'
import type { WatchFileGraphEvent } from '@target/types/watchFileEvent'
import type { LocationQueryRaw } from 'vue-router'

const ROOT_URL = '/watch_files'

interface EventsGraphFilters {
  startDate?: string
  endDate?: string
  'actors.id'?: string[]
  'actors.id[]'?: string[]
  eventType?: string[]
  'eventType[]'?: string[]
}

export const getEventsGraph = async (watchFileId: string, filters?: LocationQueryRaw) => {
  const queryParams: EventsGraphFilters = {}

  if (filters) {
    let startDate: Date | undefined = undefined
    let endDate: Date | undefined = undefined

    // Handle period selection or date picker
    if (filters.selectedPeriod) {
      const period = filters.selectedPeriod as string
      if (
        period === DatesPeriod.LAST_WEEK ||
        period === DatesPeriod.LAST_MONTH ||
        period === DatesPeriod.LAST_3_MONTH
      ) {
        const periodDates = getPeriodDates(period)
        startDate = periodDates.start
        endDate = periodDates.end
      }
    } else if (filters.datesPickerStart || filters.datesPickerEnd) {
      startDate = convertDateStringToDate(filters.datesPickerStart as string | undefined)
      endDate = convertDateStringToDate(filters.datesPickerEnd as string | undefined)
    }

    // Convert dates to ISO 8601 strings
    if (startDate) {
      queryParams.startDate = formatToISOWithTimezone(startDate, false)
    }
    if (endDate) {
      queryParams.endDate = formatToISOWithTimezone(endDate, true)
    }

    // Handle actors filter
    if (filters.actors) {
      if (Array.isArray(filters.actors)) {
        queryParams['actors.id[]'] = filters.actors.filter(
          (a): a is string => typeof a === 'string',
        )
      } else if (typeof filters.actors === 'string') {
        queryParams['actors.id'] = [filters.actors]
      }
    }

    if (filters.eventTypes) {
      if (Array.isArray(filters.eventTypes)) {
        queryParams['eventType[]'] = filters.eventTypes.filter(
          (e): e is string => typeof e === 'string',
        )
      } else if (typeof filters.eventTypes === 'string') {
        queryParams.eventType = [filters.eventTypes]
      }
    }
  }

  const response = await useApi().get<
    JsonLdCollection<WatchFileGraphEvent> & { facets?: AnalysisFacets }
  >(`${ROOT_URL}/${watchFileId}/events/graph`, {
    query: queryParams,
  })

  return {
    items: response.data.member,
    totalItems: response.data.totalItems,
    facets: response.data.facets,
  }
}
