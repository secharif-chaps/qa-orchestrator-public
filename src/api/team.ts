import { apiClient } from './client'
import { getCurrentWorkspace } from './workspace'
import type { 
  WorkspaceUser,
  WorkspaceUserResponse,
  WorkspaceUserQueryParams,
  CreateWorkspaceUserRequest,
  UpdateWorkspaceUserRequest
} from '@/types/team'

let currentWorkspaceId: number | null = null

const getCurrentWorkspaceId = async (): Promise<number> => {
  if (!currentWorkspaceId) {
    const workspace = await getCurrentWorkspace()
    currentWorkspaceId = workspace.id
  }
  return currentWorkspaceId
}

export const getWorkspaceUsers = async (params: WorkspaceUserQueryParams = {}): Promise<WorkspaceUserResponse> => {
  const workspaceId = await getCurrentWorkspaceId()
  const searchParams = new URLSearchParams()
  
  if (params.page) searchParams.set('page', params.page.toString())
  if (params.limit) searchParams.set('limit', params.limit.toString())
  if (params.sort) searchParams.set('sort', params.sort)
  if (params.order) searchParams.set('order', params.order)
  if (params.search) searchParams.set('search', params.search)
  if (params.status) searchParams.set('status', params.status)
  
  const queryString = searchParams.toString()
  const url = `/workspaces/${workspaceId}/users${queryString ? `?${queryString}` : ''}`
  
  const response = await apiClient.get<WorkspaceUserResponse>(url)
  return response
}

export const createWorkspaceUser = async (user: CreateWorkspaceUserRequest): Promise<WorkspaceUser> => {
  const workspaceId = await getCurrentWorkspaceId()
  const response = await apiClient.post<WorkspaceUser>(`/workspaces/${workspaceId}/users`, user)
  return response
}

export const updateWorkspaceUser = async (userId: number, updates: UpdateWorkspaceUserRequest): Promise<WorkspaceUser> => {
  const workspaceId = await getCurrentWorkspaceId()
  const response = await apiClient.patch<WorkspaceUser>(`/workspaces/${workspaceId}/users/${userId}`, updates)
  return response
}

export const getWorkspaceUser = async (userId: number): Promise<WorkspaceUser> => {
  const workspaceId = await getCurrentWorkspaceId()
  const response = await apiClient.get<WorkspaceUser>(`/workspaces/${workspaceId}/users/${userId}`)
  return response
}

export const disableWorkspaceUser = async (userId: number): Promise<WorkspaceUser> => {
  return updateWorkspaceUser(userId, { is_disabled: true })
}

export const enableWorkspaceUser = async (userId: number): Promise<WorkspaceUser> => {
  return updateWorkspaceUser(userId, { is_disabled: false })
}