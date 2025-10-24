/**
 * Admin user management types
 */

import type { PaginationMeta } from './pagination'

export interface AdminUserResponse {
  user_id: string
  username: string
  email: string
  workspace_id: number | null
  workspace_name: string | null
  workspace_slug: string | null
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
  workspace_filter?: string | null
  sort: 'username' | 'workspace' | 'created_at'
  order: 'asc' | 'desc'
}

export interface AssignWorkspaceRequest {
  workspace_id: number
}
