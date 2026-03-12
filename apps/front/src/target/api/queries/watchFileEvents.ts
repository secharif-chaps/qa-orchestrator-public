import type { SortOrder } from '@owlint/feathers-vue'
import { defineQueryOptions, useInfiniteQuery } from '@pinia/colada'
import { getWatchFileEvents, getWatchFileEventsLink } from '@target/api/watchFileEvents'
import { useWatchFileAnalysisStore } from '@target/stores/watchFileAnalysis'
import type { WatchFileEventCollectionResponse } from '@target/types/watchFileEvent'
import { computed, nextTick, type ComputedRef } from 'vue'

export const WATCH_FILE_EVENTS_QUERY_KEYS = {
  root: ['watchFileEvents'] as const,
  byLink: (link: string) => [...WATCH_FILE_EVENTS_QUERY_KEYS.root, link] as const,
  byWatchFile: (watchFileId: string, sortBy: string, sortOrder: SortOrder) =>
    [...WATCH_FILE_EVENTS_QUERY_KEYS.root, watchFileId, sortBy, sortOrder] as const,
}

export const getWatchFileEventsQueryLink = defineQueryOptions(({ link }: { link: string }) => ({
  key: WATCH_FILE_EVENTS_QUERY_KEYS.byLink(link),
  query: () => getWatchFileEventsLink(link),
  enabled: !!link,
}))

export const useWatchFileEventsInfiniteQuery = (watchFileId: ComputedRef<string>) => {
  const ITEMS_PER_PAGE = 10

  const watchFileAnalysisStore = useWatchFileAnalysisStore()

  const filters = computed(() => watchFileAnalysisStore.filterQuery)
  const queryKey = computed(() => {
    // Serialize filters to ensure stable key comparison
    const filtersKey = filters?.value
      ? JSON.stringify(
          Object.keys(filters.value)
            .sort()
            .reduce(
              (acc, key) => {
                acc[key] = filters.value![key]
                return acc
              },
              {} as Record<string, unknown>,
            ),
        )
      : 'no-filters'
    return [
      ...WATCH_FILE_EVENTS_QUERY_KEYS.root,
      watchFileId.value,
      'startDate',
      'ASC',
      filtersKey,
    ] as const
  })

  const { data, loadNextPage, hasNextPage, asyncStatus } = useInfiniteQuery({
    key: queryKey,
    query: async ({ pageParam }: { pageParam: string | undefined }) => {
      if (!watchFileId.value) {
        return {
          items: [],
          totalItems: 0,
          nextPageUrl: undefined,
        } as WatchFileEventCollectionResponse
      }

      if (pageParam) {
        return getWatchFileEventsLink(pageParam)
      }

      return getWatchFileEvents(
        watchFileId.value,
        'startDate',
        'DESC' as SortOrder,
        1,
        ITEMS_PER_PAGE,
        filters.value,
      )
    },
    enabled: computed(() => !!watchFileId.value),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage: WatchFileEventCollectionResponse) =>
      lastPage.nextPageUrl ?? undefined,
  })

  const allEvents = computed(() => {
    if (!data.value) return []
    return data.value.pages.flatMap((page) => page.items)
  })

  const isInitialLoading = computed(
    () => asyncStatus.value === 'loading' && allEvents.value.length === 0,
  )
  const isLoadingMore = computed(
    () => asyncStatus.value === 'loading' && allEvents.value.length > 0,
  )

  const loadMoreEvents = async () => {
    if (!hasNextPage.value || isLoadingMore.value) return

    const scrollableElement = document.querySelector('.scrollable')
    const scrollPosition = scrollableElement?.scrollTop || 0

    await loadNextPage()

    await nextTick()
    if (scrollableElement) {
      scrollableElement.scrollTop = scrollPosition
    }
  }

  return {
    allEvents,
    hasMore: hasNextPage,
    isLoadingMore,
    loadMoreEvents,
    isInitialLoading,
  }
}

export const getWatchFileEventsQuery = defineQueryOptions(
  ({
    watchFileId,
    sortBy,
    sortOrder,
  }: {
    watchFileId: string
    sortBy: string
    sortOrder: SortOrder
  }) => ({
    key: WATCH_FILE_EVENTS_QUERY_KEYS.byWatchFile(watchFileId, sortBy, sortOrder),
    query: () => getWatchFileEvents(watchFileId, sortBy, sortOrder),
    enabled: !!watchFileId,
  }),
)
