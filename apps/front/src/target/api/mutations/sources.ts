import { useMutation, useQueryCache, type EntryKey } from '@pinia/colada'
import { batchChangeSourceStatus, changeSourceStatus } from '@target/api/sources'
import { useToast } from '@target/composables/useToast'
import { SourceStatus, type Source } from '@target/types/source'
import { useI18n } from 'vue-i18n'
import { SOURCES_QUERY_KEYS } from '../queries/sources'

/**
 * Helper function to update sources optimistically in all cached queries
 */
function updateSourcesOptimistically(
  queryCache: ReturnType<typeof useQueryCache>,
  watchFileId: string,
  sourceIds: Set<string>,
  status: SourceStatus,
): Array<{
  queryKey: EntryKey
  data: unknown
}> {
  const oldQueriesData: Array<{
    queryKey: EntryKey
    data: unknown
  }> = []

  const baseKey = SOURCES_QUERY_KEYS.byWatchFile(watchFileId)
  const entries = queryCache.getEntries()

  entries.forEach((entry) => {
    const key = entry.key
    if (
      Array.isArray(key) &&
      key.length >= 3 &&
      key[0] === 'sources' &&
      key[1] === 'watchFile' &&
      key[2] === watchFileId
    ) {
      const collection = queryCache.getQueryData<{
        items?: Source[]
        totalItems?: number
      }>(key)

      if (collection?.items) {
        // Save old data
        oldQueriesData.push({
          queryKey: key as EntryKey,
          data: JSON.parse(JSON.stringify(collection)),
        })

        // Update sources optimistically
        const updatedItems = collection.items.map((source) => {
          if (sourceIds.has(source.id)) {
            return {
              ...source,
              status,
              active: status === SourceStatus.ACTIVE,
            }
          }
          return source
        })

        const updatedCollection = {
          ...collection,
          items: updatedItems,
        }

        queryCache.setQueryData(key, updatedCollection)
      }
    }
  })

  // Cancel any in-flight queries to prevent them from overwriting our optimistic update
  queryCache.cancelQueries({
    key: baseKey,
  })

  return oldQueriesData
}

export const useChangeSourceStatus = () => {
  const toast = useToast()
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const defaultErrorMessage = {
    title: t('target.watchFiles.sources.status_change.error'),
  }

  const { mutate, ...mutation } = useMutation({
    mutation: ({ source, watchFileId }: { source: Source; watchFileId: string }) => {
      const newStatus =
        source.status === SourceStatus.ACTIVE ? SourceStatus.INACTIVE : SourceStatus.ACTIVE
      return changeSourceStatus(watchFileId, source.id, newStatus, defaultErrorMessage)
    },
    onMutate: ({ source, watchFileId }) => {
      const newStatus =
        source.status === SourceStatus.ACTIVE ? SourceStatus.INACTIVE : SourceStatus.ACTIVE
      const sourceIds = new Set([source.id])
      const oldQueriesData = updateSourcesOptimistically(
        queryCache,
        watchFileId,
        sourceIds,
        newStatus,
      )
      return { oldQueriesData }
    },
    onError: (error, _, context) => {
      // Restore old data on error
      const { oldQueriesData } = context || {}
      oldQueriesData?.forEach(({ queryKey, data }) => {
        queryCache.setQueryData(queryKey, data)
      })
      console.error('Error during change source status:', error)
    },
    onSettled(_, __, { watchFileId }) {
      queryCache.invalidateQueries({
        key: SOURCES_QUERY_KEYS.byWatchFile(watchFileId),
      })
    },
    onSuccess(_, { source }) {
      const newStatus =
        source.status === SourceStatus.ACTIVE ? SourceStatus.INACTIVE : SourceStatus.ACTIVE
      toast.success(t('target.watchFiles.sources.status_change.' + newStatus))
    },
  })
  return {
    ...mutation,
    changeStatus: mutate,
  }
}

export const useBatchChangeSourceStatus = () => {
  const toast = useToast()
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const defaultErrorMessage = {
    title: t('target.watchFiles.sources.batch_change.error'),
  }

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      watchFileId,
      sources,
      status,
    }: {
      watchFileId: string
      sources: Array<{ id: string }>
      status: SourceStatus
    }) => batchChangeSourceStatus(watchFileId, sources, status, defaultErrorMessage),
    onMutate: ({ watchFileId, sources, status }) => {
      const sourceIds = new Set(sources.map((s) => s.id))
      const oldQueriesData = updateSourcesOptimistically(queryCache, watchFileId, sourceIds, status)
      return { oldQueriesData }
    },
    onError: (error, _, context) => {
      // Restore old data on error
      const { oldQueriesData } = context || {}
      oldQueriesData?.forEach(({ queryKey, data }) => {
        queryCache.setQueryData(queryKey, data)
      })
      console.error('Error during batch change source status:', error)
    },
    onSettled(_, __, { watchFileId }) {
      queryCache.invalidateQueries({
        key: SOURCES_QUERY_KEYS.byWatchFile(watchFileId),
      })
    },
    onSuccess(data) {
      const successCount = data.processed
      const failedCount = data.failed

      if (data.success || successCount > 0) {
        toast.success(t('target.watchFiles.sources.batch_change.success', successCount))
      }

      if (failedCount > 0) {
        toast.error(t('target.watchFiles.sources.batch_change.partial_error', failedCount))
      }
    },
  })

  return {
    ...mutation,
    batchChangeStatus: mutate,
  }
}
