import { apiClient } from './client'
import type {
  OrganizationUserResponse,
  OrganizationUserCreate,
  OrganizationUserUpdate,
  OrganizationUserListResponse,
} from '@/types/user'

// Organization user management endpoints
// RESTful API using Keycloak organization UUIDs (string)
export const getOrganizationUsers = async (
  organizationId: string,
  page = 1,
  limit = 20,
): Promise<OrganizationUserListResponse> => {
  const response = await apiClient.get<OrganizationUserListResponse>(
    `/organizations/${organizationId}/users?page=${page}&limit=${limit}`,
  )
  return response
}

export const createOrganizationUser = async (
  organizationId: string,
  user: OrganizationUserCreate,
): Promise<OrganizationUserResponse> => {
  const response = await apiClient.post<OrganizationUserResponse>(
    `/organizations/${organizationId}/users`,
    user,
  )
  return response
}

export const updateOrganizationUser = async (
  organizationId: string,
  userId: string,
  user: OrganizationUserUpdate,
): Promise<OrganizationUserResponse> => {
  const response = await apiClient.put<OrganizationUserResponse>(
    `/organizations/${organizationId}/users/${userId}`,
    user,
  )
  return response
}

export const deleteOrganizationUser = async (
  organizationId: string,
  userId: string,
): Promise<void> => {
  await apiClient.delete(`/organizations/${organizationId}/users/${userId}`)
}

export const getOrganizationUser = async (
  organizationId: string,
  userId: string,
): Promise<OrganizationUserResponse> => {
  const response = await apiClient.get<OrganizationUserResponse>(
    `/organizations/${organizationId}/users/${userId}`,
  )
  return response
}

export const resendPasswordReset = async (
  organizationId: string,
  userId: string,
): Promise<void> => {
  await apiClient.post(`/organizations/${organizationId}/users/${userId}/reset-password`, {})
}

export const toggleUserStatus = async (
  organizationId: string,
  userId: string,
  enabled: boolean,
): Promise<OrganizationUserResponse> => {
  const response = await apiClient.patch<OrganizationUserResponse>(
    `/organizations/${organizationId}/users/${userId}/status`,
    { enabled },
  )
  return response
}
