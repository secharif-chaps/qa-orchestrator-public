import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import {
  addItemToFolder,
  removeItemFromFolder,
  updateFolder,
  createFolder,
  deleteFolder,
  restoreFolder,
  toggleFolderFavorite,
  moveItemBetweenFolders,
} from '@/api/folders'
import type { Folder, FolderCreate, FolderItem, FolderItemAdd, FolderUpdate } from '@/types/folder'
import { FOLDER_QUERY_KEYS } from '@/queries/folders'
import { useAuthStore } from '@/stores/auth'
import { toast } from '@/utils/toast'
import { useI18n } from 'vue-i18n'
import { rollbackCacheChanges } from '@/helpers/cache'
import {
  updateAllFolderCaches,
  updateFolderInCache,
  addFolderToCache,
  removeFolderFromCache,
  type FolderCacheData,
} from '@/helpers/cache/folders'

/**
 * Add an item to a folder with optimistic UI support.
 * The item appears instantly in the folder before the API responds.
 */
export const useAddItemToFolder = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({
      folderId,
      item,
    }: {
      folderId: string
      item: FolderItemAdd
      itemMetadata?: { name: string; website?: string; owner?: string; created_at?: string }
    }) => addItemToFolder(folderId, item),

    onMutate: ({
      folderId,
      item,
      itemMetadata,
    }: {
      folderId: string
      item: FolderItemAdd
      itemMetadata?: { name: string; website?: string; owner?: string; created_at?: string }
    }) => {
      // Create optimistic folder item
      const optimisticItem: FolderItem = {
        id: `temp-${Date.now()}`,
        item_id: item.item_id,
        type: item.type,
        position: item.position,
        created_at: new Date().toISOString(),
        name: itemMetadata?.name || 'Loading...',
        owner: itemMetadata?.owner,
        created_at_item: itemMetadata?.created_at || new Date().toISOString(),
        website: itemMetadata?.website,
      }

      // Update all folder caches dynamically
      const previousStates = updateAllFolderCaches(queryCache, (data) =>
        updateFolderInCache(data, folderId, (folder) => ({
          ...folder,
          items: [...(folder.items || []), optimisticItem],
          items_count: (folder.items_count || 0) + 1,
        })),
      )

      return { previousStates, folderId }
    },

    onError: (_error, _variables, context) => {
      if (context?.previousStates) {
        rollbackCacheChanges(queryCache, context.previousStates)
      }
      toast.error(t('folder.addItem.error', 'Failed to add item to folder'))
    },
  })

  return {
    ...mutation,
    mutate,
    mutateAsync,
  }
})

/**
 * Remove an item from a folder with optimistic UI support.
 * The item disappears instantly from the folder before the API responds.
 */
export const useRemoveItemFromFolder = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({
      folderId,
      itemId,
      itemType,
    }: {
      folderId: string
      itemId: string
      itemType: 'company'
    }) => removeItemFromFolder(folderId, itemId, itemType),

    onMutate: ({ folderId, itemId }: { folderId: string; itemId: string; itemType: 'company' }) => {
      // Update all folder caches dynamically
      const previousStates = updateAllFolderCaches(queryCache, (data) =>
        updateFolderInCache(data, folderId, (folder) => ({
          ...folder,
          items: folder.items?.filter((item) => item.item_id != itemId),
          items_count: Math.max(0, (folder.items_count || 0) - 1),
        })),
      )

      return { previousStates, folderId }
    },

    onError: (_error, _variables, context) => {
      if (context?.previousStates) {
        rollbackCacheChanges(queryCache, context.previousStates)
      }
      toast.error(t('folder.removeItem.error', 'Failed to remove item from folder'))
    },
  })

  return {
    ...mutation,
    mutate,
    mutateAsync,
  }
})

/**
 * Move a company from one folder to another with full optimistic UI support.
 * The company is removed from source and added to destination instantly before the API responds.
 */
export const useMoveCompanyToFolder = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: async ({
      sourceFolderId,
      destinationFolderId,
      companyId,
    }: {
      sourceFolderId: string
      destinationFolderId: string
      companyId: string
      destinationFolderName?: string
    }) => {
      return await moveItemBetweenFolders({
        current_folder_id: sourceFolderId,
        destination_folder_id: destinationFolderId,
        item_id: companyId,
        item_type: 'company',
      })
    },

    onMutate: ({
      sourceFolderId,
      destinationFolderId,
      companyId,
      destinationFolderName,
    }: {
      sourceFolderId: string
      destinationFolderId: string
      companyId: string
      destinationFolderName?: string
    }) => {
      // First, find the item to move from any cache that has the source folder
      let itemToMove: FolderItem | undefined
      const entries = queryCache.getEntries({ key: FOLDER_QUERY_KEYS.root })

      for (const entry of entries) {
        const data = entry.state.value.data as FolderCacheData | undefined
        if (!data) continue

        // Check single folder
        if ('id' in data && typeof (data as Folder).id === 'string') {
          const folder = data as Folder
          if (folder.id === sourceFolderId) {
            itemToMove = folder.items?.find((item) => item.id == companyId)
            if (itemToMove) break
          }
        }
        // Check array of folders
        else if (Array.isArray(data)) {
          const sourceFolder = data.find((f) => f.id === sourceFolderId)
          if (sourceFolder) {
            itemToMove = sourceFolder.items?.find((item) => item.id == companyId)
            if (itemToMove) break
          }
        }
        // Check paginated response
        else if ('data' in data && Array.isArray(data.data)) {
          const sourceFolder = data.data.find((f) => f.id === sourceFolderId)
          if (sourceFolder) {
            itemToMove = sourceFolder.items?.find((item) => item.id == companyId)
            if (itemToMove) break
          }
        }
      }

      // Update all folder caches - remove from source and add to destination
      const previousStates = updateAllFolderCaches(queryCache, (data) => {
        // Single folder (byId queries)
        if ('id' in data && typeof (data as Folder).id === 'string') {
          const folder = data as Folder
          if (folder.id === sourceFolderId) {
            return {
              ...folder,
              items: folder.items?.filter((item) => item.id != companyId),
              items_count: Math.max(0, (folder.items_count || 0) - 1),
            }
          }
          if (folder.id === destinationFolderId && itemToMove) {
            return {
              ...folder,
              items: [...(folder.items || []), itemToMove],
              items_count: (folder.items_count || 0) + 1,
            }
          }
          return data
        }

        // Array of folders
        if (Array.isArray(data)) {
          return data.map((folder) => {
            if (folder.id === sourceFolderId) {
              return {
                ...folder,
                items: folder.items?.filter((item) => item.id != companyId),
                items_count: Math.max(0, (folder.items_count || 0) - 1),
              }
            }
            if (folder.id === destinationFolderId && itemToMove) {
              return {
                ...folder,
                items: [...(folder.items || []), itemToMove],
                items_count: (folder.items_count || 0) + 1,
              }
            }
            return folder
          })
        }

        // Paginated response
        if ('data' in data && Array.isArray(data.data)) {
          return {
            ...data,
            data: data.data.map((folder) => {
              if (folder.id === sourceFolderId) {
                return {
                  ...folder,
                  items: folder.items?.filter((item) => item.id != companyId),
                  items_count: Math.max(0, (folder.items_count || 0) - 1),
                }
              }
              if (folder.id === destinationFolderId && itemToMove) {
                return {
                  ...folder,
                  items: [...(folder.items || []), itemToMove],
                  items_count: (folder.items_count || 0) + 1,
                }
              }
              return folder
            }),
          }
        }

        return data
      })

      // Show success toast immediately
      if (destinationFolderName) {
        toast.success(t('folder.moveCompany.success', { folderName: destinationFolderName }))
      }

      return { previousStates, sourceFolderId, destinationFolderId, itemToMove }
    },

    onError: (_error, _variables, context) => {
      if (context?.previousStates) {
        rollbackCacheChanges(queryCache, context.previousStates)
      }
      toast.error(t('folder.moveCompany.error'))
    },
  })

  return {
    ...mutation,
    moveCompany: mutateAsync,
    mutate,
    mutateAsync,
  }
})

/**
 * Update a folder with full optimistic UI support.
 * The folder updates instantly in all views (list, grid, sidebar, detail page) before the API responds.
 */
export const useUpdateFolder = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId, folder }: { folderId: string; folder: FolderUpdate }) =>
      updateFolder(folderId, folder),

    onMutate: ({ folderId, folder: folderUpdate }: { folderId: string; folder: FolderUpdate }) => {
      // Update all folder caches dynamically
      const previousStates = updateAllFolderCaches(queryCache, (data) =>
        updateFolderInCache(data, folderId, (folder) => ({
          ...folder,
          ...folderUpdate,
          updated_at: new Date().toISOString(),
        })),
      )

      return { previousStates, folderId }
    },

    onError: (_error, _variables, context) => {
      if (context?.previousStates) {
        rollbackCacheChanges(queryCache, context.previousStates)
      }
      toast.error('Failed to update folder')
    },

    onSuccess: () => {
      toast.success('Folder updated successfully')
    },
  })

  return {
    ...mutation,
    updateFolder: mutateAsync,
    mutate,
    mutateAsync,
  }
})

/**
 * Create a new folder with full optimistic UI support.
 * The folder appears instantly in all views (list, grid, sidebar) before the API responds.
 */
export const useCreateFolder = defineMutation(() => {
  const queryCache = useQueryCache()
  const authStore = useAuthStore()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: (folder: FolderCreate) => createFolder(folder),

    onMutate: (folderData: FolderCreate) => {
      // Create temporary optimistic folder with temp ID
      const optimisticFolder: Folder = {
        id: `temp-${Date.now()}`,
        name: folderData.name,
        color: folderData.color || 'blue',
        icon: folderData.icon || 'fa-jelly-duo fa-folder',
        tags: folderData.tags || [],
        is_favorite: false,
        is_deleted: false,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        owner: authStore.username,
        owner_id: authStore.userId || '',
        organization_id: authStore.organizationId || '',
        is_owner: true,
        share_role: null,
        items: [],
        items_count: 0,
      }

      // Update all folder caches dynamically - add the new folder to list caches
      const previousStates = updateAllFolderCaches(queryCache, (data) =>
        addFolderToCache(data, optimisticFolder),
      )

      return { previousStates, optimisticFolder }
    },

    onError: (_error, _variables, context) => {
      if (context?.previousStates) {
        rollbackCacheChanges(queryCache, context.previousStates)
      }
      toast.error('Failed to create folder')
    },

    onSuccess: () => {
      toast.success('Folder created successfully')
    },

    // Always invalidate to ensure consistency and replace temp data with real data
    onSettled: () => {
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })
    },
  })

  return {
    ...mutation,
    createFolder: mutateAsync,
    mutate,
    mutateAsync,
  }
})

/**
 * Delete a folder with full optimistic UI support.
 * The folder disappears instantly from all views (list, grid, sidebar) before the API responds.
 */
export const useDeleteFolder = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: (folderId: string) => deleteFolder(folderId),

    onMutate: (folderId: string) => {
      // Update all folder caches dynamically - remove the folder from all caches
      const previousStates = updateAllFolderCaches(queryCache, (data) =>
        removeFolderFromCache(data, folderId),
      )

      return { previousStates, folderId }
    },

    onError: (_error, _variables, context) => {
      if (context?.previousStates) {
        rollbackCacheChanges(queryCache, context.previousStates)
      }
      toast.error('Failed to delete folder')
    },

    onSuccess: () => {
      toast.success('Folder deleted successfully')
    },
  })

  return {
    ...mutation,
    deleteFolder: mutateAsync,
    mutate,
    mutateAsync,
  }
})

/**
 * Restore an archived folder.
 * Invalidates folder caches on success to refetch fresh data.
 */
export const useRestoreFolder = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId }: { folderId: string; folderName: string }) => restoreFolder(folderId),

    onError: (_error, { folderName }) => {
      toast.error(
        t('folder.restore.error', 'Failed to restore folder "{name}". Please try again.', {
          name: folderName,
        }),
      )
    },

    onSuccess: (_data, { folderName }) => {
      // Invalidate folder caches to refetch fresh data
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })

      toast.success(
        t('folder.restore.success', 'Folder "{name}" has been restored successfully', {
          name: folderName,
        }),
      )
    },
  })

  return {
    ...mutation,
    restoreFolder: mutateAsync,
    mutate,
    mutateAsync,
  }
})

/**
 * Toggle a folder's favorite status.
 * Invalidates folder caches on success to refetch fresh data.
 */
export const useToggleFolderFavorite = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId, shouldBeFavorite }: { folderId: string; shouldBeFavorite: boolean }) =>
      toggleFolderFavorite(folderId, shouldBeFavorite),

    onError: () => {
      toast.error(t('folder.favorite.error', 'Failed to update favorite status'))
    },

    onSuccess: (_data, { shouldBeFavorite }) => {
      // Invalidate all folder caches to refetch fresh data
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })

      toast.success(
        shouldBeFavorite
          ? t('folder.favorite.added', 'Folder added to favorites')
          : t('folder.favorite.removed', 'Folder removed from favorites'),
      )
    },
  })

  return {
    ...mutation,
    toggleFavorite: mutateAsync,
    mutate,
    mutateAsync,
  }
})
