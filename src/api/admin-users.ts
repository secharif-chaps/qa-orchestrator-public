/**
 * Admin user management API functions
 */

import { apiClient } from './client'
import type {
  AdminUserListResponse,
  AdminUserQueryParams,
  AssignOrganizationRequest,
} from '@/types/admin-user'
import type { OrganizationMemberResponse } from '@/types/organization'

/**
 * Get all users across all organizations with filtering and sorting
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

  if (params.organization_filter !== undefined && params.organization_filter !== null) {
    queryParams.append('organization_filter', params.organization_filter)
  }

  return apiClient.get<AdminUserListResponse>(`/users?${queryParams}`)
}

/**
 * Assign a user to an organization or change their organization
 */
export const assignUserOrganization = async (userId: string, organizationId: string) => {
  return apiClient.put<OrganizationMemberResponse>(
    `/users/${userId}/organization`,
    { organization_id: organizationId } satisfies AssignOrganizationRequest,
  )
}
