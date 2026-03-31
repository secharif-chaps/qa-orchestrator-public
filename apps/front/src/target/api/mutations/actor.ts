import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { batchChangeActorStatus, changeActorStatus, removeWatchFileActor } from '@target/api/actor'
import { ACTOR_QUERY_KEYS } from '@target/api/queries/actor'
import { useToast } from '@target/composables/useToast'
import { ActorStatus } from '@target/types/actor'
import { useI18n } from 'vue-i18n'
import { SOURCES_QUERY_KEYS } from '../queries/sources'

interface CallbackMutations<T> {
  onSuccess?: (data: T) => void
  onError?: (error: Error) => void
}

export const useChangeActorStatus = (options?: CallbackMutations<unknown>) => {
  const queryCache = useQueryCache()
  const toast = useToast()
  const { t } = useI18n()

  const defaultErrorMessage = {
    title: t('target.watchFiles.actors.deactivation_modal.error'),
  }

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      watchFileId,
      actorId,
      status,
      sourceIds,
    }: {
      watchFileId: string
      actorId: string
      status: ActorStatus
      sourceIds?: string[]
    }) => changeActorStatus(watchFileId, actorId, status, sourceIds, defaultErrorMessage),

    onError(error) {
      options?.onError?.(error)
      console.error('Failed to change actor status:', error)
    },
    onSuccess(data, { watchFileId, status, sourceIds }) {
      options?.onSuccess?.(data)

      const sourceCount = sourceIds?.length || 0
      const messageKey =
        status === ActorStatus.ACTIVE
          ? 'target.watchFiles.actors.deactivation_modal.success.activated'
          : 'target.watchFiles.actors.deactivation_modal.success.deactivated'

      toast.success(t(messageKey, sourceCount))

      queryCache.invalidateQueries({
        key: ACTOR_QUERY_KEYS.byWatchFile(watchFileId),
      })
    },
    onSettled(_, __, { watchFileId }) {
      queryCache.invalidateQueries({
        key: SOURCES_QUERY_KEYS.byWatchFile(watchFileId),
      })
    },
  })

  return { ...mutation, changeStatus: mutate }
}

export const useRemoveWatchFileActor = defineMutation(() => {
  const queryCache = useQueryCache()
  const toast = useToast()
  const { t } = useI18n()

  const defaultErrorMessage = {
    title: t('target.watchFiles.actors.remove.error'),
  }

  const { mutate, ...mutation } = useMutation({
    mutation: ({ watchFileId, actorId }: { watchFileId: string; actorId: string }) =>
      removeWatchFileActor(watchFileId, actorId, defaultErrorMessage),
    onSuccess(_, { watchFileId }) {
      toast.success(t('target.watchFiles.actors.remove.success'))

      queryCache.invalidateQueries({
        key: ACTOR_QUERY_KEYS.byWatchFile(watchFileId),
      })
    },
    onError(error) {
      console.error('Failed to remove actor:', error)
    },
  })

  return { ...mutation, removeActor: mutate }
})

export const useBatchChangeActorStatus = (options?: CallbackMutations<unknown>) => {
  const queryCache = useQueryCache()
  const toast = useToast()
  const { t } = useI18n()

  const defaultErrorMessage = {
    title: t('target.watchFiles.actors.batch_change.error'),
  }

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      watchFileId,
      actors,
    }: {
      watchFileId: string
      actors: Array<{ id: string; sourceIds: string[] }>
    }) => batchChangeActorStatus(watchFileId, actors, defaultErrorMessage),

    onError(error) {
      options?.onError?.(error)
      console.error('Failed to batch change actor status:', error)
    },
    onSuccess(data, { watchFileId }) {
      options?.onSuccess?.(data)

      const successCount = data.processed
      const failedCount = data.failed

      if (data.success || successCount > 0) {
        toast.success(t('target.watchFiles.actors.batch_change.success', successCount))
      }

      if (failedCount > 0) {
        toast.error(t('target.watchFiles.actors.batch_change.partial_error', failedCount))
      }

      queryCache.invalidateQueries({
        key: ACTOR_QUERY_KEYS.byWatchFile(watchFileId),
      })
    },
    onSettled(_, __, { watchFileId }) {
      queryCache.invalidateQueries({
        key: SOURCES_QUERY_KEYS.byWatchFile(watchFileId),
      })
    },
  })

  return { ...mutation, batchChangeStatus: mutate }
}
