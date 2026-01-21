import type { useQueryCache } from '@pinia/colada'
import type { Folder } from '@/types/folder'
import type { PaginatedResponse } from '@/types/pagination'
import { FOLDER_QUERY_KEYS } from '@/queries/folders'

export type FolderCacheData = PaginatedResponse<Folder> | Folder[] | Folder

/**
 * Update all folder caches dynamically using getEntries.
 * Returns a Map of previous states for rollback.
 */
export function updateAllFolderCaches(
  queryCache: ReturnType<typeof useQueryCache>,
  updateFn: (data: FolderCacheData) => FolderCacheData | null,
): Map<string, unknown> {
  const previousStates = new Map<string, unknown>()
  const entries = queryCache.getEntries({ key: FOLDER_QUERY_KEYS.root })

  entries.forEach(entry => {
    const data = entry.state.value.data as FolderCacheData | undefined
    if (!data) return

    const keyStr = JSON.stringify(entry.key)
    previousStates.set(keyStr, data)

    const newData = updateFn(data)
    if (newData !== null) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      queryCache.setQueryData(entry.key as any, newData)
    }
  })

  return previousStates
}

/**
 * Update a specific folder in any cache data structure (single, array, or paginated)
 */
export function updateFolderInCache(
  data: FolderCacheData,
  folderId: string,
  updateFn: (folder: Folder) => Folder,
): FolderCacheData {
  // Single folder (byId queries)
  if ('id' in data && typeof (data as Folder).id === 'string') {
    const folder = data as Folder
    if (folder.id === folderId) {
      return updateFn(folder)
    }
    return data
  }

  // Array of folders
  if (Array.isArray(data)) {
    return data.map(folder =>
      folder.id === folderId ? updateFn(folder) : folder
    )
  }

  // Paginated response
  if ('data' in data && Array.isArray(data.data)) {
    return {
      ...data,
      data: data.data.map(folder =>
        folder.id === folderId ? updateFn(folder) : folder
      ),
    }
  }

  return data
}

/**
 * Add a folder to list caches (skips single folder caches)
 */
export function addFolderToCache(
  data: FolderCacheData,
  newFolder: Folder,
): FolderCacheData {
  // Single folder - skip
  if ('id' in data && typeof (data as Folder).id === 'string') {
    return data
  }

  // Array of folders
  if (Array.isArray(data)) {
    if (data.some(f => f.id === newFolder.id)) return data
    return [newFolder, ...data]
  }

  // Paginated response
  if ('data' in data && Array.isArray(data.data)) {
    if (data.data.some(f => f.id === newFolder.id)) return data
    return {
      ...data,
      data: [newFolder, ...data.data],
      meta: { ...data.meta, total: data.meta.total + 1 },
    }
  }

  return data
}

/**
 * Remove a folder from list caches.
 * Returns null for single folder caches that match (to skip updating them).
 */
export function removeFolderFromCache(
  data: FolderCacheData,
  folderId: string,
): FolderCacheData | null {
  // Single folder (byId) - return null to skip this cache
  if ('id' in data && typeof (data as Folder).id === 'string') {
    return (data as Folder).id === folderId ? null : data
  }

  // Array of folders
  if (Array.isArray(data)) {
    return data.filter(folder => folder.id !== folderId)
  }

  // Paginated response
  if ('data' in data && Array.isArray(data.data)) {
    const hadFolder = data.data.some(f => f.id === folderId)
    return {
      ...data,
      data: data.data.filter(folder => folder.id !== folderId),
      meta: { ...data.meta, total: Math.max(0, data.meta.total - (hadFolder ? 1 : 0)) },
    }
  }

  return data
}
