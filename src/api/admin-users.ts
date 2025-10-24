/**
 * Admin user management API functions
 */

import { apiClient } from './client'
import type {
  AdminUserListResponse,
  AdminUserQueryParams,
  AssignWorkspaceRequest,
} from '@/types/admin-user'
import type { WorkspaceMemberResponse } from '@/types/workspace'

/**
 * Get all users across all workspaces with filtering and sorting
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

  if (params.workspace_filter !== undefined && params.workspace_filter !== null) {
    queryParams.append('workspace_filter', params.workspace_filter)
  }

  return apiClient.get<AdminUserListResponse>(`/workspace/admin/users?${queryParams}`)
}

/**
 * Assign a user to a workspace or change their workspace
 */
export const assignUserWorkspace = async (userId: string, workspaceId: number) => {
  return apiClient.put<WorkspaceMemberResponse>(
    `/workspace/admin/users/${userId}/workspace`,
    { workspace_id: workspaceId } satisfies AssignWorkspaceRequest,
  )
}
