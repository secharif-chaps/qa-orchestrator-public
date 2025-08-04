import { apiClient } from './client'
import type { 
  WorkspaceResponse, 
  WorkspaceCreate, 
  WorkspaceUpdate,
  WorkspaceWithMembersResponse,
  WorkspaceMemberResponse,
  Workspace
} from '@/types/workspace'

// Admin workspace management endpoints
export const getAllWorkspaces = async (): Promise<WorkspaceResponse[]> => {
  const response = await apiClient.get<WorkspaceResponse[]>('/workspace/admin/all')
  return response
}

export const createWorkspace = async (workspace: WorkspaceCreate): Promise<WorkspaceResponse> => {
  const response = await apiClient.post<WorkspaceResponse>('/workspace/admin', workspace)
  return response
}

export const updateWorkspace = async (id: number, workspace: WorkspaceUpdate): Promise<WorkspaceResponse> => {
  const response = await apiClient.put<WorkspaceResponse>(`/workspace/admin/${id}`, workspace)
  return response
}

export const deleteWorkspace = async (id: number): Promise<void> => {
  await apiClient.delete(`/workspace/admin/${id}`)
}

export const getWorkspaceById = async (id: number): Promise<WorkspaceResponse> => {
  const response = await apiClient.get<WorkspaceResponse>(`/workspace/admin/${id}`)
  return response
}

export const getWorkspaceMembers = async (id: number): Promise<WorkspaceMemberResponse[]> => {
  const response = await apiClient.get<WorkspaceMemberResponse[]>(`/workspace/admin/${id}/members`)
  return response
}

// Regular user workspace endpoints
export const getCurrentWorkspace = async (): Promise<WorkspaceResponse> => {
  const response = await apiClient.get<WorkspaceResponse>('/workspace/current')
  return response
}

export const getCurrentWorkspaceWithMembers = async (): Promise<WorkspaceWithMembersResponse> => {
  const response = await apiClient.get<WorkspaceWithMembersResponse>('/workspace/current/with-members')
  return response
}