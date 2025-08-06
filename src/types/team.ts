export interface WorkspaceUser {
  id: number
  email: string
  username: string
  first_name: string
  last_name: string
  created_at: string
  updated_at: string
  is_disabled: boolean
  permissions: string[]
  created_by: number | null
}

export interface WorkspaceUserListItem extends WorkspaceUser {
  display_name: string
}

export interface WorkspaceUserQueryParams {
  page: number
  limit: number
  search: string
  sort: 'name' | 'email' | 'created_at' | 'username'
  order: 'asc' | 'desc'
  status: 'active' | 'disabled' | 'all'
}

export interface WorkspaceUserResponse {
  data: WorkspaceUser[]
  pagination: {
    page: number
    limit: number
    total: number
    totalPages: number
    hasNext: boolean
    hasPrev: boolean
  }
}

export interface CreateWorkspaceUserRequest {
  email: string
  username: string
  password: string
  first_name: string
  last_name: string
  permissions?: string[]
}

export interface UpdateWorkspaceUserRequest {
  is_disabled?: boolean
  permissions?: string[]
  first_name?: string
  last_name?: string
}