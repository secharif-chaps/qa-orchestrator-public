/**
 * Admin user management types
 */

import type { PaginationMeta } from './pagination'

export interface AdminUserResponse {
  user_id: string
  username: string
  email: string
  organization_id: string | null
  organization_name: string | null
  status: 'active' | 'revoked'
  created_at: string
}

export interface AdminUserListResponse {
  data: AdminUserResponse[]
  pagination: PaginationMeta
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
