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
import type { ApiPaginationRaw } from '@/utils/pagination'

export interface FolderListResponse {
  data: Folder[]
  pagination: ApiPaginationRaw
}

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

export interface FolderListApiFilters {
  page: number
  size: number
  name: string
  archived?: boolean
  favorites?: boolean
  include_all?: boolean
  sort_by?: 'name' | 'created_at' | 'updated_at'
  sort_order?: 'asc' | 'desc'
}

const appendListFilters = (params: URLSearchParams, filters: FolderListApiFilters) => {
  if (filters.name) {
    params.append('name', filters.name)
  }
  if (filters.archived) {
    params.append('archived', 'true')
  }
  if (filters.favorites) {
    params.append('favorites', 'true')
  }
  if (filters.include_all) {
    params.append('include_all', 'true')
  }
  if (filters.sort_by) {
    params.append('sort_by', filters.sort_by)
  }
  if (filters.sort_order) {
    params.append('sort_order', filters.sort_order)
  }
}

export const getFolders = async (filters: FolderListApiFilters) => {
  const params = new URLSearchParams({
    page: filters.page.toString(),
    size: filters.size.toString(),
  })
  appendListFilters(params, filters)

  const response = await apiClient.get<FolderListResponse>(`/folders/?${params.toString()}`)
  return response
}

export const getFoldersWithItems = async (filters: FolderListApiFilters) => {
  const params = new URLSearchParams({
    page: filters.page.toString(),
    size: filters.size.toString(),
    include_items: 'true', // Request items to be included
  })
  appendListFilters(params, filters)

  const response = await apiClient.get<FolderListResponse>(`/folders/?${params.toString()}`)
  return response
}

export const createFolder = async (folder: FolderCreate) => {
  return apiClient.post<Folder>('/folders/', folder, { silent: true })
}

export const updateFolder = async (folderId: string, folder: FolderUpdate) => {
  return apiClient.put<Folder>(`/folders/${folderId}`, folder, { silent: true })
}

/**
 * Add a folder to the current user's favorites.
 */
export const addFolderFavorite = async (folderId: string) => {
  return apiClient.post<{ message: string; is_favorite: boolean }>(
    `/folders/${folderId}/favorite`,
    {}, // Empty body for POST
    { silent: true },
  )
}

/**
 * Remove a folder from the current user's favorites.
 */
export const removeFolderFavorite = async (folderId: string) => {
  await apiClient.delete(`/folders/${folderId}/favorite`, { silent: true })
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
  await apiClient.delete(`/folders/${folderId}`, { silent: true })
}

export const restoreFolder = async (folderId: string) => {
  return apiClient.post<Folder>(`/folders/${folderId}/restore`, {}, { silent: true })
}

export const addItemToFolder = async (folderId: string, item: FolderItemAdd) => {
  return apiClient.post(`/folders/${folderId}/items`, item, { silent: true })
}

export const removeItemFromFolder = async (
  folderId: string,
  itemId: string,
  itemType: 'company',
) => {
  const params = new URLSearchParams({ item_type: itemType })
  await apiClient.delete(`/folders/${folderId}/items/${itemId}?${params.toString()}`, {
    silent: true,
  })
}

export const moveItemBetweenFolders = async (data: {
  current_folder_id: string
  destination_folder_id: string
  item_id: string
  item_type: 'company'
}) => {
  return apiClient.patch(
    `/folders/${data.current_folder_id}/items/${data.item_id}?item_type=${data.item_type}`,
    { folder_id: data.destination_folder_id },
    { silent: true },
  )
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
  return apiClient.post<FolderShare>(`/folders/${folderId}/shares`, share, { silent: true })
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
  return apiClient.patch<FolderShare>(`/folders/${folderId}/shares/${shareUserId}`, update, {
    silent: true,
  })
}

/**
 * Remove a share from a folder (owner only)
 * DELETE /folders/{folder_id}/shares/{share_user_id}
 */
export const deleteFolderShare = async (folderId: string, shareUserId: string) => {
  await apiClient.delete(`/folders/${folderId}/shares/${shareUserId}`, { silent: true })
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
  const response = await apiClient.get<ShareableUser[]>(
    `/folders/users/search?${params.toString()}`,
  )
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
