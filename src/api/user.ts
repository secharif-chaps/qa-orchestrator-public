import { apiClient } from './client'
import type { 
  WorkspaceUserResponse, 
  WorkspaceUserCreate, 
  WorkspaceUserUpdate,
  WorkspaceUserListResponse
} from '@/types/user'

// Workspace user management endpoints
export const getWorkspaceUsers = async (workspaceId: number, page = 1, limit = 20): Promise<WorkspaceUserListResponse> => {
  const response = await apiClient.get<WorkspaceUserListResponse>(
    `/workspace/admin/${workspaceId}/users?page=${page}&limit=${limit}`
  )
  return response
}

export const createWorkspaceUser = async (workspaceId: number, user: WorkspaceUserCreate): Promise<WorkspaceUserResponse> => {
  const response = await apiClient.post<WorkspaceUserResponse>(
    `/workspace/admin/${workspaceId}/users`, 
    user
  )
  return response
}

export const updateWorkspaceUser = async (workspaceId: number, userId: string, user: WorkspaceUserUpdate): Promise<WorkspaceUserResponse> => {
  const response = await apiClient.put<WorkspaceUserResponse>(
    `/workspace/admin/${workspaceId}/users/${userId}`, 
    user
  )
  return response
}

export const deleteWorkspaceUser = async (workspaceId: number, userId: string): Promise<void> => {
  await apiClient.delete(`/workspace/admin/${workspaceId}/users/${userId}`)
}

export const getWorkspaceUser = async (workspaceId: number, userId: string): Promise<WorkspaceUserResponse> => {
  const response = await apiClient.get<WorkspaceUserResponse>(
    `/workspace/admin/${workspaceId}/users/${userId}`
  )
  return response
}

export const resendPasswordReset = async (workspaceId: number, userId: string): Promise<void> => {
  await apiClient.post(`/workspace/admin/${workspaceId}/users/${userId}/reset-password`)
}

export const toggleUserStatus = async (workspaceId: number, userId: string, enabled: boolean): Promise<WorkspaceUserResponse> => {
  const response = await apiClient.patch<WorkspaceUserResponse>(
    `/workspace/admin/${workspaceId}/users/${userId}/status`,
    { enabled }
  )
  return response
}