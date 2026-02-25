import type { SortOrder } from '@owlint/feathers-vue'
import { defineQueryOptions, useInfiniteQuery } from '@pinia/colada'
import { getWatchFileEvents, getWatchFileEventsLink } from '@target/api/watchFileEvents'
import { useWatchFileAnalysisStore } from '@target/stores/watchFileAnalysis'
import type { WatchFileEventCollectionResponse } from '@target/types/watchFileEvent'
import { computed, nextTick, ref, watch, watchEffect, type ComputedRef } from 'vue'

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

  const nextPageUrl = ref<string | undefined>(undefined)
  const resetQuery = ref(false)

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

  const {
    state: infiniteData,
    loadMore: loadMorePages,
    asyncStatus,
  } = useInfiniteQuery({
    key: queryKey,
    query: async () => {
      if (!watchFileId.value) {
        return {
          items: [],
          totalItems: 0,
          nextPageUrl: undefined,
        } as WatchFileEventCollectionResponse
      }

      if (nextPageUrl.value) {
        return getWatchFileEventsLink(nextPageUrl.value)
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
    initialPage: {
      items: [],
      totalItems: 0,
      nextPageUrl: undefined,
    } as WatchFileEventCollectionResponse,
    merge: (
      result: WatchFileEventCollectionResponse,
      current: WatchFileEventCollectionResponse | null,
    ) => {
      if (!current) return result
      if (resetQuery.value) {
        resetQuery.value = false
        return current
      }
      return {
        items: [...result.items, ...current.items],
        totalItems: current.totalItems,
        nextPageUrl: current.nextPageUrl,
      }
    },
  })
  const allEvents = computed(() => {
    if (infiniteData.value?.status !== 'success') return []
    return infiniteData.value.data.items
  })
  const hasMore = computed(() => {
    if (infiniteData.value?.status !== 'success') return false
    return !!infiniteData.value.data.nextPageUrl
  })

  const isInitialLoading = computed(
    () => asyncStatus.value === 'loading' && allEvents.value.length === 0,
  )
  const isLoadingMore = computed(
    () => asyncStatus.value === 'loading' && allEvents.value.length > 0,
  )

  // Sync nextPageUrl from query data (including cached data)
  watchEffect(() => {
    if (infiniteData.value?.status === 'success') {
      const cachedNextPageUrl = infiniteData.value.data.nextPageUrl
      if (cachedNextPageUrl !== nextPageUrl.value) {
        nextPageUrl.value = cachedNextPageUrl
      }
    }
  })

  // Reset nextPageUrl and trigger refetch when watchFileId or filters change
  // Don't use immediate to avoid interfering with initial query
  watch(
    filters,
    () => {
      nextPageUrl.value = undefined
      resetQuery.value = true
      loadMorePages()
    },
    { deep: true },
  )

  const loadMoreEvents = async () => {
    if (!hasMore.value || isLoadingMore.value) return

    const scrollableElement = document.querySelector('.scrollable')
    const scrollPosition = scrollableElement?.scrollTop || 0

    await loadMorePages()

    await nextTick()
    if (scrollableElement) {
      scrollableElement.scrollTop = scrollPosition
    }
  }

  watch(
    watchFileId,
    () => {
      nextPageUrl.value = undefined
      loadMorePages()
    },
    {
      immediate: true,
    },
  )
  return {
    allEvents,
    hasMore,
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
