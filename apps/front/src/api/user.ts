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
  return apiClient.post<OrganizationUserResponse>(`/organizations/${organizationId}/users`, user, {
    silent: true,
  })
}

export const updateOrganizationUser = async (
  organizationId: string,
  userId: string,
  user: OrganizationUserUpdate,
): Promise<OrganizationUserResponse> => {
  return apiClient.put<OrganizationUserResponse>(
    `/organizations/${organizationId}/users/${userId}`,
    user,
    { silent: true },
  )
}

export const deleteOrganizationUser = async (
  organizationId: string,
  userId: string,
): Promise<void> => {
  await apiClient.delete(`/organizations/${organizationId}/users/${userId}`, { silent: true })
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
  await apiClient.post(
    `/organizations/${organizationId}/users/${userId}/reset-password`,
    {},
    { silent: true },
  )
}

export const toggleUserStatus = async (
  organizationId: string,
  userId: string,
  enabled: boolean,
): Promise<OrganizationUserResponse> => {
  return apiClient.patch<OrganizationUserResponse>(
    `/organizations/${organizationId}/users/${userId}/status`,
    { enabled },
    { silent: true },
  )
}
