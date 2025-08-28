import { type Folder, type FolderCreate, type FolderUpdate, type FolderItemAdd } from '@/types/folder'
import { apiClient } from './client'
import type { PaginatedResponse } from '@/types/pagination'

export const getFolderById = async (folderId: string) => {
  const response = await apiClient.get<Folder>(`/folders/${folderId}`)
  return response
}

export const getFolders = async (filters: { page: number; size: number; name: string; archived?: boolean }) => {
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

  const response = await apiClient.get<PaginatedResponse<Folder>>(
    `/folders?${params.toString()}`,
  )
  return response
}

export const getFoldersWithItems = async (filters: { page: number; size: number; name: string; archived?: boolean }) => {
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

  const response = await apiClient.get<PaginatedResponse<Folder>>(
    `/folders?${params.toString()}`,
  )
  return response
}

export const createFolder = async (folder: FolderCreate) => {
  const response = await apiClient.post<Folder>('/folders', folder)
  return response
}

export const updateFolder = async (folderId: string, folder: FolderUpdate) => {
  const response = await apiClient.put<Folder>(`/folders/${folderId}`, folder)
  return response
}

export const deleteFolder = async (folderId: string) => {
  const response = await apiClient.delete(`/folders/${folderId}`)
  return response
}

export const restoreFolder = async (folderId: string) => {
  const response = await apiClient.post<Folder>(`/folders/${folderId}/restore`)
  return response
}

export const addItemToFolder = async (folderId: string, item: FolderItemAdd) => {
  const response = await apiClient.post(`/folders/${folderId}/items`, item)
  return response
}

export const removeItemFromFolder = async (folderId: string, itemId: string, itemType: 'company') => {
  const params = new URLSearchParams({ item_type: itemType })
  const response = await apiClient.delete(`/folders/${folderId}/items/${itemId}?${params.toString()}`)
  return response
}

// Export as a single API object for backward compatibility
export const foldersApi = {
  getFolderById,
  getFolders,
  createFolder,
  updateFolder,
  deleteFolder,
  restoreFolder,
  addItemToFolder,
  removeItemFromFolder,
}