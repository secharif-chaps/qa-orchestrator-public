import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import {
  createStream,
  updateStream,
  deleteStream,
  updateStreamStatus,
  testConnection,
  dispatchStream,
} from '@/api/streams'
import { STREAM_QUERY_KEYS } from '@/queries/streams'
import type {
  StreamCreate,
  StreamUpdate,
  StreamStatusUpdate,
  TestConnectionRequest,
} from '@/types/stream'
import { toast } from '@/utils/toast'
import { useI18n } from 'vue-i18n'

export const useCreateStream = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId, data }: { folderId: string; data: StreamCreate }) =>
      createStream(folderId, data),

    onSuccess: () => {
      queryCache.invalidateQueries({ key: STREAM_QUERY_KEYS.root })
      toast.success(t('stream.toast.createSuccess'))
    },

    onError: () => {
      toast.error(t('stream.toast.createError'))
    },
  })

  return { ...mutation, createStream: mutateAsync, mutate, mutateAsync }
})

export const useUpdateStream = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ streamId, data }: { streamId: string; data: StreamUpdate }) =>
      updateStream(streamId, data),

    onSuccess: () => {
      queryCache.invalidateQueries({ key: STREAM_QUERY_KEYS.root })
      toast.success(t('stream.toast.updateSuccess'))
    },

    onError: () => {
      toast.error(t('stream.toast.updateError'))
    },
  })

  return { ...mutation, updateStream: mutateAsync, mutate, mutateAsync }
})

export const useDeleteStream = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ streamId }: { streamId: string; streamName: string }) => deleteStream(streamId),

    onSuccess: (_data: void, { streamName }: { streamId: string; streamName: string }) => {
      queryCache.invalidateQueries({ key: STREAM_QUERY_KEYS.root })
      toast.success(t('stream.toast.deleteSuccess', { name: streamName }))
    },

    onError: (_error: Error, { streamName }: { streamId: string; streamName: string }) => {
      toast.error(t('stream.toast.deleteError', { name: streamName }))
    },
  })

  return { ...mutation, deleteStream: mutateAsync, mutate, mutateAsync }
})

export const useUpdateStreamStatus = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ streamId, data }: { streamId: string; data: StreamStatusUpdate }) =>
      updateStreamStatus(streamId, data),

    onSuccess: () => {
      queryCache.invalidateQueries({ key: STREAM_QUERY_KEYS.root })
      toast.success(t('stream.toast.statusUpdateSuccess'))
    },

    onError: () => {
      toast.error(t('stream.toast.statusUpdateError'))
    },
  })

  return { ...mutation, updateStreamStatus: mutateAsync, mutate, mutateAsync }
})

export const useTestConnection = defineMutation(() => {
  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: (data: TestConnectionRequest) => testConnection(data),
  })

  return { ...mutation, testConnection: mutateAsync, mutate, mutateAsync }
})

export const useDispatchStream = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ streamId }: { streamId: string }) => dispatchStream(streamId),

    onSuccess: (_data, { streamId }: { streamId: string }) => {
      queryCache.invalidateQueries({ key: STREAM_QUERY_KEYS.root })
      queryCache.invalidateQueries({
        key: STREAM_QUERY_KEYS.deliveriesRoot(streamId),
      })
      toast.success(t('stream.toast.dispatchSuccess'))
    },

    onError: () => {
      toast.error(t('stream.toast.dispatchError'))
    },
  })

  return { ...mutation, dispatchStream: mutateAsync, mutate, mutateAsync }
})
