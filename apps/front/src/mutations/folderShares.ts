import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { createFolderShare, deleteFolderShare, updateFolderShare } from '@/api/folders'
import type { FolderShare, FolderShareCreate, FolderShareUpdate } from '@/types/folder'
import { toast } from '@/utils/toast'
import { useI18n } from 'vue-i18n'

// Query keys for folder shares
export const FOLDER_SHARE_QUERY_KEYS = {
  root: ['folder-shares'] as const,
  byFolderId: (folderId: string) => [...FOLDER_SHARE_QUERY_KEYS.root, folderId] as const,
}

/**
 * Create a new folder share
 */
export const useCreateFolderShare = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId, share }: { folderId: string; share: FolderShareCreate }) =>
      createFolderShare(folderId, share),

    onSuccess: (_data, { folderId }) => {
      // Invalidate folder shares query to refetch the list
      queryCache.invalidateQueries({ key: FOLDER_SHARE_QUERY_KEYS.byFolderId(folderId) })
      toast.success(t('common.folder.share.added', 'User added to folder'))
    },

    onError: () => {
      toast.error(t('common.folder.share.addError', 'Failed to share folder'))
    },
  })

  return {
    ...mutation,
    createShare: mutateAsync,
    mutate,
    mutateAsync,
  }
})

/**
 * Update a folder share's role
 */
export const useUpdateFolderShare = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({
      folderId,
      shareUserId,
      update,
    }: {
      folderId: string
      shareUserId: string
      update: FolderShareUpdate
    }) => updateFolderShare(folderId, shareUserId, update),

    // Optimistic update
    onMutate: ({ folderId, shareUserId, update }) => {
      const queryKey = FOLDER_SHARE_QUERY_KEYS.byFolderId(folderId)
      const previousShares = queryCache.getQueryData<FolderShare[]>(queryKey)

      if (previousShares) {
        queryCache.setQueryData(
          queryKey,
          previousShares.map((share) =>
            share.user_id === shareUserId ? { ...share, role: update.role } : share,
          ),
        )
      }

      return { previousShares, queryKey }
    },

    onError: (_error, _variables, context) => {
      // Rollback on error
      if (context?.previousShares && context?.queryKey) {
        queryCache.setQueryData(context.queryKey, context.previousShares)
      }
      toast.error(t('common.folder.share.updateError', 'Failed to update share role'))
    },

    onSuccess: () => {
      toast.success(t('common.folder.share.updated', 'Share role updated'))
    },

    onSettled: (_data, _error, { folderId }) => {
      // Invalidate to ensure consistency
      queryCache.invalidateQueries({ key: FOLDER_SHARE_QUERY_KEYS.byFolderId(folderId) })
    },
  })

  return {
    ...mutation,
    updateShare: mutateAsync,
    mutate,
    mutateAsync,
  }
})

/**
 * Delete a folder share
 */
export const useDeleteFolderShare = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId, shareUserId }: { folderId: string; shareUserId: string }) =>
      deleteFolderShare(folderId, shareUserId),

    // Optimistic update - remove from list immediately
    onMutate: ({ folderId, shareUserId }) => {
      const queryKey = FOLDER_SHARE_QUERY_KEYS.byFolderId(folderId)
      const previousShares = queryCache.getQueryData<FolderShare[]>(queryKey)

      if (previousShares) {
        queryCache.setQueryData(
          queryKey,
          previousShares.filter((share) => share.user_id !== shareUserId),
        )
      }

      return { previousShares, queryKey }
    },

    onError: (_error, _variables, context) => {
      // Rollback on error
      if (context?.previousShares && context?.queryKey) {
        queryCache.setQueryData(context.queryKey, context.previousShares)
      }
      toast.error(t('common.folder.share.removeError', 'Failed to remove share'))
    },

    onSuccess: () => {
      toast.success(t('common.folder.share.removed', 'User removed from folder'))
    },

    onSettled: (_data, _error, { folderId }) => {
      // Invalidate to ensure consistency
      queryCache.invalidateQueries({ key: FOLDER_SHARE_QUERY_KEYS.byFolderId(folderId) })
    },
  })

  return {
    ...mutation,
    deleteShare: mutateAsync,
    mutate,
    mutateAsync,
  }
})
