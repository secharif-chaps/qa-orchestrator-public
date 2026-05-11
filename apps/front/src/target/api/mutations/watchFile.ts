import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { WATCH_FILE_QUERY_KEYS } from '@target/api/queries/watchFile'
import {
  changeWatchFileStatus,
  createWatchFile,
  deleteWatchFile,
  removeWatchFileActor,
  toggleWatchFileFavorite,
  updateWatchFile,
} from '@target/api/watchFile'
import { useToast } from '@/composables/useToast'
import { useWatchFileStore } from '@target/stores/watchFile'
import type { WatchFile } from '@target/types/watchFile'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

interface CallbackMutations<T> {
  onSuccess?: (data: T) => void
  onError?: () => void
}

export const useCreateWatchFile = (options?: CallbackMutations<WatchFile>) => {
  const queryCache = useQueryCache()
  const watchFileStore = useWatchFileStore()

  const { mutate, ...mutation } = useMutation({
    mutation: (content: string) => createWatchFile(content),

    onError(error) {
      options?.onError?.()
      console.error('Failed to send message:', error)
    },
    onSuccess(data) {
      options?.onSuccess?.(data)
      queryCache.invalidateQueries({
        key: WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters),
      })
    },
  })
  return { ...mutation, createWatchFile: mutate }
}

export const useUpdateWatchFile = defineMutation(() => {
  const toast = useToast()
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const watchFileStore = useWatchFileStore()
  const defaultErrorMessage = {
    title: t('target.watchFiles.title.error.toast_title'),
    description: t('target.watchFiles.title.error.generic'),
  }

  const { mutate, ...mutation } = useMutation({
    mutation: ({ id, data }: { id: string; data: Partial<WatchFile> }) =>
      updateWatchFile(id, data, defaultErrorMessage),
    onError() {},
    onSuccess({ id }) {
      toast.success(t('target.watchFiles.title.success.updated'))
      queryCache.invalidateQueries({
        key: WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters),
      })
      queryCache.invalidateQueries({
        key: WATCH_FILE_QUERY_KEYS.byId(id),
      })
    },
  })
  return { ...mutation, updateTask: mutate }
})

export const useDeleteWatchFile = defineMutation(() => {
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const toast = useToast()
  const watchFileStore = useWatchFileStore()

  const { mutate, ...mutation } = useMutation({
    mutation: (id: string) => deleteWatchFile(id),
    onSuccess() {
      toast.success(t('target.watchFiles.delete.success'))
      queryCache.invalidateQueries({
        key: WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters),
      })
    },
  })
  return { ...mutation, deleteTask: mutate }
})

export const useChangeWatchFileStatus = (watchFile: WatchFile) => {
  const toast = useToast()
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const watchFileStore = useWatchFileStore()
  const statusValue = ref(watchFile.status)
  const defaultErrorMessage = {
    title: t('target.watchFiles.header_section.status.change.error'),
  }

  const { mutate, ...mutation } = useMutation({
    mutation: ({ id, status }: { id: string; status: string }) =>
      changeWatchFileStatus(id, status, defaultErrorMessage),
    onError() {
      statusValue.value = watchFile.status
    },
    onSuccess(_, { id }) {
      toast.success(t(`target.watchFiles.header_section.status.change.success`))
      queryCache.invalidateQueries({
        key: WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters),
      })
      queryCache.invalidateQueries({
        key: WATCH_FILE_QUERY_KEYS.byId(id),
      })
    },
  })
  return { ...mutation, changeStatus: mutate, statusValue }
}

export const useToggleWatchFileFavorite = () => {
  const toast = useToast()
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const watchFileStore = useWatchFileStore()

  const { mutate, ...mutation } = useMutation({
    mutation: (watchFile: WatchFile) => {
      const defaultErrorMessage = {
        title: watchFile.isFavorite
          ? t('target.watchFiles.status_change.error_true')
          : t('target.watchFiles.status_change.error_false'),
      }
      return toggleWatchFileFavorite(watchFile.id, !!watchFile.isFavorite, defaultErrorMessage)
    },
    onMutate: ({ id }) => {
      const watchFileCollection = queryCache.getQueryData<{
        items: WatchFile[]
        totalItems: number
      }>(WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters))

      const oldCollection = JSON.parse(JSON.stringify(watchFileCollection))

      const findWatchFile = watchFileCollection?.items.find((watchFile) => watchFile.id === id)
      if (findWatchFile) {
        findWatchFile.isFavorite = !findWatchFile.isFavorite
      }

      queryCache.setQueryData(
        WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters),
        watchFileCollection,
      )
      queryCache.cancelQueries({
        key: WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters),
      })
      return { watchFileCollection, oldCollection }
    },
    onError: (error, _, { oldCollection, watchFileCollection }) => {
      if (
        watchFileCollection ===
        queryCache.getQueryData(WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters))
      ) {
        queryCache.setQueryData(
          WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters),
          oldCollection,
        )
      }
      console.error('Error During ToggleWatchFileFavorite : ', error)
    },
    onSettled() {
      // invalidate the query to refetch the new data
      queryCache.invalidateQueries({
        key: WATCH_FILE_QUERY_KEYS.withFilters(watchFileStore.filters),
      })
    },
    onSuccess(_, { isFavorite, name }) {
      toast.success(
        isFavorite
          ? t('target.watchFiles.status_change.success_true', { name })
          : t('target.watchFiles.status_change.success_false', { name }),
      )
    },
  })
  return {
    ...mutation,
    toggleFavorite: mutate,
  }
}

export const useRemoveWatchFileActor = defineMutation(() => {
  const queryCache = useQueryCache()
  const { mutate, ...mutation } = useMutation({
    mutation: ({ watchFileId, actorId }: { watchFileId: string; actorId: number }) =>
      removeWatchFileActor(watchFileId, actorId),
    onSuccess(_, { watchFileId }) {
      queryCache.invalidateQueries({
        key: WATCH_FILE_QUERY_KEYS.byId(watchFileId),
      })
    },
  })
  return { ...mutation, removeActor: mutate }
})
