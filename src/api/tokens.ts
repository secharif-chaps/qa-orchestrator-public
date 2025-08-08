import { apiClient } from './client'
import type {
  ModulesResponse,
  ModuleTokenResponse,
  TokenUpdateRequest,
  AddTokensRequest,
  ModuleName,
} from '@/types/tokens'

// Admin Endpoints
export const getWorkspaceModules = async (workspaceId: number) => {
  const response = await apiClient.get<ModulesResponse>(`/admin/workspaces/${workspaceId}/modules`)
  return response
}

export const updateWorkspaceModules = async (workspaceId: number, updates: Record<ModuleName, TokenUpdateRequest>) => {
  const response = await apiClient.put<ModulesResponse>(`/admin/workspaces/${workspaceId}/modules`, updates)
  return response
}

export const addModuleTokens = async (workspaceId: number, module: ModuleName, data: AddTokensRequest) => {
  const response = await apiClient.post<ModuleTokenResponse>(`/admin/workspaces/${workspaceId}/modules/${module}/tokens`, data)
  return response
}

export const toggleModule = async (workspaceId: number, module: ModuleName, enabled: boolean) => {
  const response = await apiClient.put<ModuleTokenResponse>(`/admin/workspaces/${workspaceId}/modules/${module}/toggle`, { enabled })
  return response
}

// Token Validation Endpoints
export const getModuleTokens = async (workspaceId: number, module: ModuleName) => {
  const response = await apiClient.get<ModuleTokenResponse>(`/workspaces/${workspaceId}/modules/${module}/tokens`)
  return response
}