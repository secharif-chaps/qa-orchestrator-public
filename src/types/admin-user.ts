/**
 * Admin user management types
 */

export interface AdminUserResponse {
  user_id: string
  username: string
  email: string
  organization_id: string | null
  organization_name: string | null
  status: 'active' | 'revoked'
  created_at: string
  permissions: string[]
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
  data: AdminUserResponse[]
  pagination: AdminUserPagination
}

export interface AdminUserQueryParams {
  page: number
  limit: number
  search?: string
  organization_filter?: string | null
  sort: 'username' | 'organization' | 'created_at'
  order: 'asc' | 'desc'
}

export interface AssignOrganizationRequest {
  organization_id: string
}
