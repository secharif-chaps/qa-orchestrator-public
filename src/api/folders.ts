import {
  type Folder,
  type FolderCreate,
  type FolderUpdate,
  type FolderItemAdd,
  type FolderShare,
  type FolderShareCreate,
  type FolderShareUpdate,
  type ShareableUser,
} from '@/types/folder'
import { apiClient } from './client'
import type { PaginatedResponse } from '@/types/pagination'

export const getFolderById = async (folderId: string, filters?: { archived?: boolean }) => {
  const params = new URLSearchParams()

  if (filters?.archived) {
    params.set('archived', 'true')
  }

  const queryString = params.toString()

  const url = queryString ? `/folders/${folderId}?${queryString}` : `/folders/${folderId}`

  const response = await apiClient.get<Folder>(url)
  return response
}

export const getFolders = async (filters: {
  page: number
  size: number
  name: string
  archived?: boolean
  favorites?: boolean
}) => {
  const params = new URLSearchParams({
    page: filters.page.toString(),
    size: filters.size.toString(),
  })

  if (filters.name) {
    params.append('name', filters.name)
  }

  if (filters.archived) {
    params.append('archived', 'true')
  }

  if (filters.favorites) {
    params.append('favorites', 'true')
  }

  const response = await apiClient.get<PaginatedResponse<Folder>>(
    `/folders/?${params.toString()}`,
  )
  return response
}

export const getFoldersWithItems = async (filters: {
  page: number
  size: number
  name: string
  archived?: boolean
  favorites?: boolean
}) => {
  const params = new URLSearchParams({
    page: filters.page.toString(),
    size: filters.size.toString(),
    include_items: 'true', // Request items to be included
  })

  if (filters.name) {
    params.append('name', filters.name)
  }

  if (filters.archived) {
    params.append('archived', 'true')
  }

  if (filters.favorites) {
    params.append('favorites', 'true')
  }

  const response = await apiClient.get<PaginatedResponse<Folder>>(
    `/folders/?${params.toString()}`,
  )
  return response
}

export const createFolder = async (folder: FolderCreate) => {
  const response = await apiClient.post<Folder>('/folders/', folder)
  return response
}

export const updateFolder = async (folderId: string, folder: FolderUpdate) => {
  const response = await apiClient.put<Folder>(`/folders/${folderId}`, folder)
  return response
}

/**
 * Add a folder to the current user's favorites.
 */
export const addFolderFavorite = async (folderId: string) => {
  const response = await apiClient.post<{ message: string; is_favorite: boolean }>(
    `/folders/${folderId}/favorite`,
    {}, // Empty body for POST
  )
  return response
}

/**
 * Remove a folder from the current user's favorites.
 */
export const removeFolderFavorite = async (folderId: string) => {
  await apiClient.delete(`/folders/${folderId}/favorite`)
  // DELETE endpoint returns void, return the expected state
  return { message: 'Folder removed from favorites', is_favorite: false }
}

/**
 * Toggle a folder's favorite status for the current user.
 * Uses dedicated POST/DELETE endpoints for user-scoped favorites.
 */
export const toggleFolderFavorite = async (folderId: string, shouldBeFavorite: boolean) => {
  if (shouldBeFavorite) {
    return addFolderFavorite(folderId)
  } else {
    return removeFolderFavorite(folderId)
  }
}

export const deleteFolder = async (folderId: string) => {
  const response = await apiClient.delete(`/folders/${folderId}`)
  return response
}

export const restoreFolder = async (folderId: string) => {
  const response = await apiClient.post<Folder>(`/folders/${folderId}/restore`, {})
  return response
}

export const addItemToFolder = async (folderId: string, item: FolderItemAdd) => {
  const response = await apiClient.post(`/folders/${folderId}/items`, item)
  return response
}

export const removeItemFromFolder = async (
  folderId: string,
  itemId: string,
  itemType: 'company',
) => {
  const params = new URLSearchParams({ item_type: itemType })
  const response = await apiClient.delete(
    `/folders/${folderId}/items/${itemId}?${params.toString()}`,
  )
  return response
}

export const moveItemBetweenFolders = async (data: {
  current_folder_id: string
  destination_folder_id: string
  item_id: string
  item_type: 'company'
}) => {
  const response = await apiClient.patch(
    `/folders/${data.current_folder_id}/items/${data.item_id}?item_type=${data.item_type}`,
    { folder_id: data.destination_folder_id }
  )
  return response
}

// =====================================================
// Folder Sharing API Functions
// =====================================================

/**
 * Get all shares for a folder (owner only)
 * GET /folders/{folder_id}/shares
 */
export const getFolderShares = async (folderId: string) => {
  const response = await apiClient.get<FolderShare[]>(`/folders/${folderId}/shares`)
  return response
}

/**
 * Create a new share for a folder (owner only)
 * POST /folders/{folder_id}/shares
 */
export const createFolderShare = async (folderId: string, share: FolderShareCreate) => {
  const response = await apiClient.post<FolderShare>(`/folders/${folderId}/shares`, share)
  return response
}

/**
 * Update an existing share's role (owner only)
 * PATCH /folders/{folder_id}/shares/{share_user_id}
 */
export const updateFolderShare = async (
  folderId: string,
  shareUserId: string,
  update: FolderShareUpdate,
) => {
  const response = await apiClient.patch<FolderShare>(
    `/folders/${folderId}/shares/${shareUserId}`,
    update,
  )
  return response
}

/**
 * Remove a share from a folder (owner only)
 * DELETE /folders/{folder_id}/shares/{share_user_id}
 */
export const deleteFolderShare = async (folderId: string, shareUserId: string) => {
  await apiClient.delete(`/folders/${folderId}/shares/${shareUserId}`)
}

/**
 * Search users in the organization for sharing
 * GET /folders/users/search?q={query}&limit=10
 * Returns users that can be shared with (excluding current user and existing shares)
 */
export const searchUsersForSharing = async (query: string, limit: number = 10) => {
  const params = new URLSearchParams({
    q: query,
    limit: limit.toString(),
  })
  const response = await apiClient.get<ShareableUser[]>(`/folders/users/search?${params.toString()}`)
  return response
}

// Export as a single API object for backward compatibility
export const foldersApi = {
  getFolderById,
  getFolders,
  createFolder,
  updateFolder,
  addFolderFavorite,
  removeFolderFavorite,
  toggleFolderFavorite,
  deleteFolder,
  restoreFolder,
  addItemToFolder,
  removeItemFromFolder,
  moveItemBetweenFolders,
  // Sharing functions
  getFolderShares,
  createFolderShare,
  updateFolderShare,
  deleteFolderShare,
  searchUsersForSharing,
}
