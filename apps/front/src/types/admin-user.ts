/**
 * Admin user management types
 */

import type { PermissionTier } from './team'

/**
 * User list item for the paginated user list
 * Includes organization info and permission tier computed from permissions
 */
export interface AdminUserListItem {
  user_id: string
  username: string
  email: string
  first_name: string | null
  last_name: string | null
  status: 'active' | 'revoked'
  created_at: string
  permission_tier: PermissionTier | null
  organization_id: string | null
  organization_name: string | null
}

/**
 * User permissions response (on-demand)
 * Fetched when opening the permissions modal
 */
export interface UserPermissionsResponse {
  user_id: string
  username: string
  permissions: string[]
}

/**
 * User organization response (on-demand)
 * Fetched when opening the organization modal
 */
export interface UserOrganizationResponse {
  user_id: string
  username: string
  organization: {
    id: string
    name: string
  } | null
}

/**
 * Legacy type alias for backward compatibility
 * @deprecated Use AdminUserListItem instead
 */
export interface AdminUserResponse {
  user_id: string
  username: string
  email: string
  first_name?: string | null
  last_name?: string | null
  organization_id?: string | null
  organization_name?: string | null
  status: 'active' | 'revoked'
  created_at: string
  permissions?: string[]
}

// Backend pagination format (different from standard PaginationMeta)
export interface AdminUserPagination {
  page?: number
  current_page?: number
  limit?: number
  per_page?: number
  total: number
  total_pages?: number
  last_page?: number
}

export interface AdminUserListResponse {
  data: AdminUserListItem[]
  pagination: AdminUserPagination
}

export interface AdminUserQueryParams {
  page: number
  limit: number
  search?: string
  sort: 'username' | 'created_at'
  order: 'asc' | 'desc'
}

export interface AssignOrganizationRequest {
  organization_id: string
}
