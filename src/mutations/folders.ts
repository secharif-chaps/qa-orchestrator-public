import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { addItemToFolder, removeItemFromFolder, updateFolder, createFolder, deleteFolder, toggleFolderFavorite, moveItemBetweenFolders } from '@/api/folders'
import type { Folder, FolderCreate, FolderItemAdd, FolderUpdate } from '@/types/folder'
import type { PaginatedResponse } from '@/types/pagination'
import { FOLDER_QUERY_KEYS } from '@/queries/folders'
import { useAuthStore } from '@/stores/auth'
import { toast } from '@/utils/toast'
import { useI18n } from 'vue-i18n'

export const useAddItemToFolder = defineMutation(() => {
  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId, item }: { folderId: string; item: FolderItemAdd }) =>
      addItemToFolder(folderId, item),
  })

  return {
    ...mutation,
    mutate,
    mutateAsync,
  }
})

export const useRemoveItemFromFolder = defineMutation(() => {
  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId, itemId, itemType }: { folderId: string; itemId: string; itemType: 'company' }) =>
      removeItemFromFolder(folderId, itemId, itemType),
  })

  return {
    ...mutation,
    mutate,
    mutateAsync,
  }
})

/**
 * Move a company from one folder to another with optimistic UI support.
 * The company is removed instantly from the UI before the API responds.
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

    // Optimistic update BEFORE API call - remove item from source folder only
    onMutate: ({
      sourceFolderId,
      companyId,
      destinationFolderName,
    }: {
      sourceFolderId: string
      destinationFolderId: string
      companyId: string
      destinationFolderName?: string
    }) => {
      // Store previous cache states for rollback
      const previousStates = new Map<string, unknown>()

      // Helper to update folder in paginated cache
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const updateInPaginatedCache = (queryKey: any, updateFn: (folders: Folder[]) => Folder[]) => {
        const currentData = queryCache.getQueryData<PaginatedResponse<Folder> | Folder[]>(queryKey)

        if (!currentData) return

        // Store for rollback
        previousStates.set(JSON.stringify(queryKey), currentData)

        // Check if it's a paginated response or direct array
        if (Array.isArray(currentData)) {
          // Direct array - apply update function
          queryCache.setQueryData(queryKey, updateFn(currentData))
        } else if ('data' in currentData && Array.isArray(currentData.data)) {
          // Paginated response - apply update function to data array
          queryCache.setQueryData(queryKey, {
            ...currentData,
            data: updateFn(currentData.data),
          })
        }
      }

      // Update source folder cache - try both with and without filters
      // The component uses filters: { archived: false }, so we need to update that cache key
      const sourceFolderKeyNoFilter = FOLDER_QUERY_KEYS.byId(sourceFolderId)
      const sourceFolderKeyWithFilter = FOLDER_QUERY_KEYS.byId(sourceFolderId, { archived: false })

      // Try to get data from filtered cache key first (this is what the component uses)
      let sourceFolder = queryCache.getQueryData<Folder>(sourceFolderKeyWithFilter)
      const usingFilterKey = !!sourceFolder

      if (!sourceFolder) {
        sourceFolder = queryCache.getQueryData<Folder>(sourceFolderKeyNoFilter)
      }

      // Find the item to move - use loose equality to handle string/number mismatch
      const itemToMove = sourceFolder?.items?.find((item) => item.id == companyId)

      if (sourceFolder && itemToMove) {
        // Save source folder state for rollback
        const cacheKeyToUse = usingFilterKey ? sourceFolderKeyWithFilter : sourceFolderKeyNoFilter
        previousStates.set(JSON.stringify(cacheKeyToUse), sourceFolder)

        const updatedItems = sourceFolder.items?.filter((item) => item.id != companyId)
        const updatedFolder = {
          ...sourceFolder,
          items: updatedItems,
          items_count: Math.max(0, (sourceFolder.items_count || 0) - 1),
        }
        queryCache.setQueryData(cacheKeyToUse, updatedFolder)

        // Also update the other cache key if it exists
        const otherKey = usingFilterKey ? sourceFolderKeyNoFilter : sourceFolderKeyWithFilter
        const otherFolder = queryCache.getQueryData<Folder>(otherKey)
        if (otherFolder) {
          previousStates.set(JSON.stringify(otherKey), otherFolder)
          queryCache.setQueryData(otherKey, updatedFolder)
        }
      }

      // Update folder list caches (sidebar, list view, etc.)
      const updateFolderItems = (folders: Folder[]) => {
        return folders.map(folder => {
          if (folder.id === sourceFolderId && itemToMove) {
            return {
              ...folder,
              items: folder.items?.filter((item) => item.id != companyId),
              items_count: Math.max(0, (folder.items_count || 0) - 1),
            }
          }
          return folder
        })
      }

      // Update common folder query caches
      // Sidebar uses: { page: 1, size: 5, name: '' }
      const sidebarKey = FOLDER_QUERY_KEYS.withItems({ page: 1, size: 5, name: '' })
      updateInPaginatedCache(sidebarKey, updateFolderItems)

      // List page table view (various page/size combos)
      const listTableKeys = [
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 6, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 12, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 21, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 30, name: '' }),
      ]
      listTableKeys.forEach((key) => updateInPaginatedCache(key, updateFolderItems))

      // Show success toast immediately
      if (destinationFolderName) {
        toast.success(t('folder.moveCompany.success', { folderName: destinationFolderName }))
      }

      return {
        previousStates,
        sourceFolderId,
      }
    },

    // Rollback on error
    onError: (_error, _variables, context) => {
      if (context?.previousStates) {
        context.previousStates.forEach((value, key) => {
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          queryCache.setQueryData(JSON.parse(key) as any, value)
        })
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

    // Optimistic update BEFORE API call
    onMutate: ({ folderId, folder: folderUpdate }: { folderId: string; folder: FolderUpdate }) => {
      // Store previous cache states for rollback
      const previousStates = new Map<string, unknown>()

      // Helper to update folder in paginated cache
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const updateInPaginatedCache = (queryKey: any) => {
        const currentData = queryCache.getQueryData<PaginatedResponse<Folder> | Folder[]>(queryKey)

        if (!currentData) return

        // Store for rollback
        previousStates.set(JSON.stringify(queryKey), currentData)

        // Check if it's a paginated response or direct array
        if (Array.isArray(currentData)) {
          // Direct array - update the matching folder
          queryCache.setQueryData(
            queryKey,
            currentData.map((folder) =>
              folder.id === folderId
                ? { ...folder, ...folderUpdate, updated_at: new Date().toISOString() }
                : folder,
            ),
          )
        } else if ('data' in currentData && Array.isArray(currentData.data)) {
          // Paginated response - update the matching folder in data array
          queryCache.setQueryData(queryKey, {
            ...currentData,
            data: currentData.data.map((folder) =>
              folder.id === folderId
                ? { ...folder, ...folderUpdate, updated_at: new Date().toISOString() }
                : folder,
            ),
          })
        }
      }

      // Update folder by ID cache (detail page)
      const folderByIdKey = FOLDER_QUERY_KEYS.byId(folderId)
      const currentFolder = queryCache.getQueryData<Folder>(folderByIdKey)
      if (currentFolder) {
        previousStates.set(JSON.stringify(folderByIdKey), currentFolder)
        queryCache.setQueryData(folderByIdKey, {
          ...currentFolder,
          ...folderUpdate,
          updated_at: new Date().toISOString(),
        })
      }

      // Update common folder query caches
      // Sidebar uses: { page: 1, size: 5, name: '' }
      const sidebarKey = FOLDER_QUERY_KEYS.withItems({ page: 1, size: 5, name: '' })
      updateInPaginatedCache(sidebarKey)

      // List page grid view (various page/size combos)
      const listGridKeys = [
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 6, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 12, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 21, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 30, name: '' }),
      ]
      listGridKeys.forEach((key) => updateInPaginatedCache(key))

      // List page table view
      const listTableKeys = [
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 6, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 12, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 21, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 30, name: '' }),
      ]
      listTableKeys.forEach((key) => updateInPaginatedCache(key))

      return { previousStates, folderId }
    },

    // Rollback on error
    onError: (_error, _variables, context) => {
      if (context?.previousStates) {
        context.previousStates.forEach((value, key) => {
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          queryCache.setQueryData(JSON.parse(key) as any, value)
        })
      }
      toast.error('Failed to update folder')
    },

    // Success notification
    onSuccess: () => {
      toast.success('Folder updated successfully')
    },

    // Always invalidate to ensure consistency
    onSettled: () => {
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })
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

    // Optimistic update BEFORE API call
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

      // Store previous cache states for rollback
      const previousStates = new Map<string, unknown>()

      // Helper to update paginated folder caches
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const updatePaginatedCache = (queryKey: any, folder: Folder) => {
        const currentData = queryCache.getQueryData<PaginatedResponse<Folder> | Folder[]>(queryKey)

        if (!currentData) return

        // Store for rollback
        previousStates.set(JSON.stringify(queryKey), currentData)

        // Check if it's a paginated response or direct array
        if (Array.isArray(currentData)) {
          // Direct array - prepend new folder
          queryCache.setQueryData(queryKey, [folder, ...currentData])
        } else if ('data' in currentData && Array.isArray(currentData.data)) {
          // Paginated response - prepend to data array and update meta
          queryCache.setQueryData(queryKey, {
            ...currentData,
            data: [folder, ...currentData.data],
            meta: {
              ...currentData.meta,
              total: currentData.meta.total + 1,
            },
          })
        }
      }

      // Update common folder query caches
      // Sidebar uses: { page: 1, size: 5, name: '' }
      const sidebarKey = FOLDER_QUERY_KEYS.withItems({ page: 1, size: 5, name: '' })
      updatePaginatedCache(sidebarKey, optimisticFolder)

      // List page grid view (various page/size combos)
      // We'll update the first page which is most commonly viewed
      const listGridKeys = [
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 6, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 12, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 21, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 30, name: '' }),
      ]
      listGridKeys.forEach((key) => updatePaginatedCache(key, optimisticFolder))

      // List page table view
      const listTableKeys = [
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 6, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 12, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 21, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 30, name: '' }),
      ]
      listTableKeys.forEach((key) => updatePaginatedCache(key, optimisticFolder))

      return { previousStates, optimisticFolder }
    },

    // Rollback on error
    onError: (_error, _variables, context) => {
      if (context?.previousStates) {
        context.previousStates.forEach((value, key) => {
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          queryCache.setQueryData(JSON.parse(key) as any, value)
        })
      }
      toast.error('Failed to create folder')
    },

    // Success notification
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

    // Optimistic update BEFORE API call
    onMutate: (folderId: string) => {
      // Store previous cache states for rollback
      const previousStates = new Map<string, unknown>()

      // Helper to remove folder from paginated cache
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const removeFromPaginatedCache = (queryKey: any) => {
        const currentData = queryCache.getQueryData<PaginatedResponse<Folder> | Folder[]>(queryKey)

        if (!currentData) return

        // Store for rollback
        previousStates.set(JSON.stringify(queryKey), currentData)

        // Check if it's a paginated response or direct array
        if (Array.isArray(currentData)) {
          // Direct array - filter out the deleted folder
          queryCache.setQueryData(
            queryKey,
            currentData.filter((folder) => folder.id !== folderId),
          )
        } else if ('data' in currentData && Array.isArray(currentData.data)) {
          // Paginated response - filter out from data array and update meta
          queryCache.setQueryData(queryKey, {
            ...currentData,
            data: currentData.data.filter((folder) => folder.id !== folderId),
            meta: {
              ...currentData.meta,
              total: Math.max(0, currentData.meta.total - 1),
            },
          })
        }
      }

      // Update common folder query caches
      // Sidebar uses: { page: 1, size: 5, name: '' }
      const sidebarKey = FOLDER_QUERY_KEYS.withItems({ page: 1, size: 5, name: '' })
      removeFromPaginatedCache(sidebarKey)

      // List page grid view (various page/size combos)
      const listGridKeys = [
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 6, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 12, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 21, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 30, name: '' }),
      ]
      listGridKeys.forEach((key) => removeFromPaginatedCache(key))

      // List page table view
      const listTableKeys = [
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 6, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 12, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 21, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 30, name: '' }),
      ]
      listTableKeys.forEach((key) => removeFromPaginatedCache(key))

      return { previousStates, folderId }
    },

    // Rollback on error
    onError: (_error, _variables, context) => {
      if (context?.previousStates) {
        context.previousStates.forEach((value, key) => {
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          queryCache.setQueryData(JSON.parse(key) as any, value)
        })
      }
      toast.error('Failed to delete folder')
    },

    // Success notification
    onSuccess: () => {
      toast.success('Folder deleted successfully')
    },

    // Always invalidate to ensure consistency
    onSettled: () => {
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })
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
 * Toggle a folder's favorite status with full optimistic UI support.
 * The favorite state updates instantly in all views before the API responds.
 */
export const useToggleFolderFavorite = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ folderId, shouldBeFavorite }: { folderId: string; shouldBeFavorite: boolean }) =>
      toggleFolderFavorite(folderId, shouldBeFavorite),

    // Optimistic update BEFORE API call
    onMutate: ({ folderId, shouldBeFavorite }: { folderId: string; shouldBeFavorite: boolean }) => {
      // Store previous cache states for rollback
      const previousStates = new Map<string, unknown>()

      // Helper to update folder's is_favorite in paginated cache
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const updateFavoriteInCache = (queryKey: any) => {
        const currentData = queryCache.getQueryData<PaginatedResponse<Folder> | Folder[]>(queryKey)

        if (!currentData) return

        // Store for rollback
        previousStates.set(JSON.stringify(queryKey), currentData)

        // Check if it's a paginated response or direct array
        if (Array.isArray(currentData)) {
          // Direct array - update the matching folder
          queryCache.setQueryData(
            queryKey,
            currentData.map((folder) =>
              folder.id === folderId
                ? { ...folder, is_favorite: shouldBeFavorite }
                : folder,
            ),
          )
        } else if ('data' in currentData && Array.isArray(currentData.data)) {
          // Paginated response - update the matching folder in data array
          queryCache.setQueryData(queryKey, {
            ...currentData,
            data: currentData.data.map((folder) =>
              folder.id === folderId
                ? { ...folder, is_favorite: shouldBeFavorite }
                : folder,
            ),
          })
        }
      }

      // Helper to remove folder from favorites-only cache when unfavoriting
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const removeFromFavoritesCache = (queryKey: any) => {
        const currentData = queryCache.getQueryData<PaginatedResponse<Folder> | Folder[]>(queryKey)

        if (!currentData) return

        // Store for rollback
        previousStates.set(JSON.stringify(queryKey), currentData)

        // Check if it's a paginated response or direct array
        if (Array.isArray(currentData)) {
          queryCache.setQueryData(
            queryKey,
            currentData.filter((folder) => folder.id !== folderId),
          )
        } else if ('data' in currentData && Array.isArray(currentData.data)) {
          queryCache.setQueryData(queryKey, {
            ...currentData,
            data: currentData.data.filter((folder) => folder.id !== folderId),
            meta: {
              ...currentData.meta,
              total: Math.max(0, currentData.meta.total - 1),
            },
          })
        }
      }

      // Update folder by ID cache (detail page)
      const folderByIdKey = FOLDER_QUERY_KEYS.byId(folderId)
      const currentFolder = queryCache.getQueryData<Folder>(folderByIdKey)
      if (currentFolder) {
        previousStates.set(JSON.stringify(folderByIdKey), currentFolder)
        queryCache.setQueryData(folderByIdKey, {
          ...currentFolder,
          is_favorite: shouldBeFavorite,
        })
      }

      // Also check with archived filter
      const folderByIdArchivedKey = FOLDER_QUERY_KEYS.byId(folderId, { archived: true })
      const currentFolderArchived = queryCache.getQueryData<Folder>(folderByIdArchivedKey)
      if (currentFolderArchived) {
        previousStates.set(JSON.stringify(folderByIdArchivedKey), currentFolderArchived)
        queryCache.setQueryData(folderByIdArchivedKey, {
          ...currentFolderArchived,
          is_favorite: shouldBeFavorite,
        })
      }

      // Update common folder query caches (all folders - no favorites filter)
      // Sidebar uses: { page: 1, size: 5, name: '' }
      const sidebarKey = FOLDER_QUERY_KEYS.withItems({ page: 1, size: 5, name: '' })
      updateFavoriteInCache(sidebarKey)

      // List page grid view (various page/size combos) - "all" filter
      const listGridKeys = [
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 6, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 12, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 21, name: '' }),
        FOLDER_QUERY_KEYS.withFilters({ page: 1, size: 30, name: '' }),
      ]
      listGridKeys.forEach((key) => updateFavoriteInCache(key))

      // List page table view - "all" filter
      const listTableKeys = [
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 6, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 12, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 21, name: '' }),
        FOLDER_QUERY_KEYS.withItems({ page: 1, size: 30, name: '' }),
      ]
      listTableKeys.forEach((key) => updateFavoriteInCache(key))

      // Handle favorites-filtered caches
      // When removing from favorites, remove from favorites cache
      // When adding to favorites, we'll let invalidation handle adding (safer)
      if (!shouldBeFavorite) {
        // Remove from favorites-only caches
        const favoritesKey = FOLDER_QUERY_KEYS.favorites()
        removeFromFavoritesCache(favoritesKey)
      }

      return { previousStates, folderId, shouldBeFavorite }
    },

    // Rollback on error
    onError: (_error, _variables, context) => {
      if (context?.previousStates) {
        context.previousStates.forEach((value, key) => {
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          queryCache.setQueryData(JSON.parse(key) as any, value)
        })
      }
      toast.error(t('folder.favorite.error', 'Failed to update favorite status'))
    },

    // Success notification
    onSuccess: (_data, { shouldBeFavorite }) => {
      toast.success(
        shouldBeFavorite
          ? t('folder.favorite.added', 'Folder added to favorites')
          : t('folder.favorite.removed', 'Folder removed from favorites')
      )
    },

    // Always invalidate to ensure consistency
    onSettled: () => {
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })
    },
  })

  return {
    ...mutation,
    toggleFavorite: mutateAsync,
    mutate,
    mutateAsync,
  }
})