/**
 * Admin user management API functions
 */

import { apiClient } from './client'
import type {
  AdminUserListResponse,
  AdminUserQueryParams,
  AssignOrganizationRequest,
  UserPermissionsResponse,
  UserOrganizationResponse,
} from '@/types/admin-user'
import type { OrganizationMemberResponse } from '@/types/organization'

/**
 * Get all users with search and pagination (optimized)
 * Does NOT include permissions or organization - use dedicated endpoints for those
 */
export const getAllUsers = async (params: AdminUserQueryParams) => {
  const queryParams = new URLSearchParams({
    page: params.page.toString(),
    limit: params.limit.toString(),
    sort: params.sort,
    order: params.order,
  })

  if (params.search) {
    queryParams.append('search', params.search)
  }

  return apiClient.get<AdminUserListResponse>(`/users?${queryParams}`)
}

/**
 * Get user's current permissions (realm roles)
 * Use this when opening the permissions management modal
 */
export const getUserPermissions = async (userId: string) => {
  return apiClient.get<UserPermissionsResponse>(`/users/${userId}/permissions`)
}

/**
 * Get user's current organization membership
 * Use this when opening the organization assignment modal
 */
export const getUserOrganization = async (userId: string) => {
  return apiClient.get<UserOrganizationResponse>(`/users/${userId}/organization`)
}

/**
 * Assign a user to an organization or change their organization
 */
export const assignUserOrganization = async (userId: string, organizationId: string) => {
  return apiClient.put<OrganizationMemberResponse>(
    `/users/${userId}/organization`,
    {
      organization_id: organizationId,
    } satisfies AssignOrganizationRequest,
    { silent: true },
  )
}

/**
 * Update user's permissions (roles)
 */
export const updateUserPermissions = async (userId: string, permissions: string[]) => {
  return apiClient.put(`/users/${userId}/permissions`, { permissions }, { silent: true })
}

export interface ResetPasswordResponse {
  success: boolean
  method: string
  message: string
  temporary_password: string
}

/**
 * Reset user password by setting a new temporary password
 * User will be required to change password on next login
 */
export const resetUserPassword = async (userId: string, temporaryPassword: string) => {
  return apiClient.post<ResetPasswordResponse>(
    `/users/${userId}/reset-password`,
    {
      temporary_password: temporaryPassword,
    },
    { silent: true },
  )
}

/**
 * Disable a user account (soft delete - account exists but cannot login)
 * This sets enabled=false in Keycloak
 */
export const disableUser = async (userId: string) => {
  return apiClient.put(`/users/${userId}/disable`, {}, { silent: true })
}

/**
 * Enable a previously disabled user account
 * This sets enabled=true in Keycloak
 */
export const enableUser = async (userId: string) => {
  return apiClient.put(`/users/${userId}/enable`, {}, { silent: true })
}
