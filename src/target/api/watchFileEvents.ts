import type { SortOrder } from '@owlint/feathers-vue';
import type { LocationQueryRaw } from 'vue-router';
import { useApi } from '~/composables/useApi';
import { useDate } from '~/composables/useDate';
import { DatesPeriod } from '~/types/filter';
import type {
    WatchFileEventCollection,
    WatchFileEventCollectionResponse,
} from '~/types/watchFileEvent';

export const getWatchFileEventsLink = async (
  link: string,
): Promise<WatchFileEventCollectionResponse> => {
  const response = await useApi().get<WatchFileEventCollection>(link);

  return {
    items: response.data.member,
    totalItems: response.data.totalItems,
    nextPageUrl: response.data.view?.next,
  };
};

export const getWatchFileEvents = async (
  watchFileId: string,
  sortBy: string,
  sortOrder: SortOrder,
  page: number = 1,
  itemsPerPage: number = 10,
  filters?: LocationQueryRaw,
): Promise<WatchFileEventCollectionResponse> => {
  const queryParams: Record<string, string | number | string[]> = {
    [`order[${sortBy}]`]: sortOrder.toLowerCase(),
    page,
    itemsPerPage,
  };

  if (filters) {
    const { getPeriodDates, convertDateStringToDate, formatToISOWithTimezone } =
      useDate();

    let startDate: Date | undefined = undefined;
    let endDate: Date | undefined = undefined;

    // Handle period selection or date picker
    if (filters.selectedPeriod) {
      const period = filters.selectedPeriod as string;
      if (
        period === DatesPeriod.LAST_WEEK ||
        period === DatesPeriod.LAST_MONTH ||
        period === DatesPeriod.LAST_3_MONTH
      ) {
        const periodDates = getPeriodDates(period);
        startDate = periodDates.start;
        endDate = periodDates.end;
      }
    } else if (filters.datesPickerStart || filters.datesPickerEnd) {
      startDate = convertDateStringToDate(
        filters.datesPickerStart as string | undefined,
      );
      endDate = convertDateStringToDate(
        filters.datesPickerEnd as string | undefined,
      );
    }

    // Convert dates to ISO 8601 strings
    if (startDate) {
      queryParams.startDate = formatToISOWithTimezone(startDate, false);
    }
    if (endDate) {
      queryParams.endDate = formatToISOWithTimezone(endDate, true);
    }

    // Handle actors filter
    if (filters.actors) {
      if (Array.isArray(filters.actors)) {
        queryParams['actors.id[]'] = filters.actors.filter(
          (a): a is string => typeof a === 'string',
        );
      } else if (typeof filters.actors === 'string') {
        queryParams['actors.id'] = [filters.actors];
      }
    }

    // Handle sources filter
    if (filters.sources) {
      if (Array.isArray(filters.sources)) {
        queryParams['sources.id[]'] = filters.sources.filter(
          (s): s is string => typeof s === 'string',
        );
      } else if (typeof filters.sources === 'string') {
        queryParams['sources.id'] = [filters.sources];
      }
    }

    if (filters.eventTypes) {
      if (Array.isArray(filters.eventTypes)) {
        queryParams['eventType[]'] = filters.eventTypes.filter(
          (e): e is string => typeof e === 'string',
        );
      } else if (typeof filters.eventTypes === 'string') {
        queryParams.eventType = [filters.eventTypes];
      }
    }
  }

  const response = await useApi().get<WatchFileEventCollection>(
    `/watch_files/${watchFileId}/events`,
    {
      query: queryParams,
    },
  );

  return {
    items: response.data.member,
    totalItems: response.data.totalItems,
    nextPageUrl: response.data.view?.next,
  };
};
