import { apiClient } from './client'
import type {
  ModulesResponse,
  ModuleTokenResponse,
  TokenUpdateRequest,
  AddTokensRequest,
  ModuleName,
} from '@/types/tokens'

// Organization Endpoints (non-admin - organization members can view their own)
export const getOrganizationModules = async (organizationId: string) => {
  const response = await apiClient.get<ModulesResponse>(`/organizations/${organizationId}/modules`)
  return response
}

export const updateOrganizationModules = async (organizationId: string, updates: Record<ModuleName, TokenUpdateRequest>) => {
  const response = await apiClient.put<ModulesResponse>(`/organizations/${organizationId}/modules`, updates)
  return response
}

export const addModuleTokens = async (organizationId: string, module: ModuleName, data: AddTokensRequest) => {
  const response = await apiClient.post<ModuleTokenResponse>(`/organizations/${organizationId}/modules/${module}/tokens`, data)
  return response
}

export const toggleModule = async (organizationId: string, module: ModuleName, enabled: boolean) => {
  const response = await apiClient.put<ModuleTokenResponse>(`/organizations/${organizationId}/modules/${module}/toggle`, { enabled })
  return response
}

// Token Validation Endpoints
export const getModuleTokens = async (organizationId: string, module: ModuleName) => {
  const response = await apiClient.get<ModuleTokenResponse>(`/organizations/${organizationId}/modules/${module}/tokens`)
  return response
}