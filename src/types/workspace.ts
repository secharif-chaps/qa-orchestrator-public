export interface WorkspaceResponse {
  id: number
  name: string
  description: string | null
  slug: string
  created_at: string
  updated_at: string
}

export interface WorkspaceWithMemberCount extends WorkspaceResponse {
  member_count: number
}

export interface WorkspaceCreate {
  name: string
  description?: string
  slug: string
}

export interface WorkspaceUpdate {
  name?: string
  description?: string
  slug?: string
}

export interface WorkspaceMemberResponse {
  id: number
  workspace_id: number
  user_id: string
  username: string
  email: string
  status: 'ACTIVE' | 'REVOKED'
  created_at: string
  updated_at: string
}

export interface WorkspaceWithMembersResponse {
  workspace: WorkspaceResponse
  members: WorkspaceMemberResponse[]
}

// Pagination response wrapper
export interface PaginatedWorkspacesResponse {
  data: WorkspaceWithMemberCount[]
  pagination: {
    page: number
    limit: number
    total: number
    totalPages: number
    hasNext: boolean
    hasPrev: boolean
  }
}

// Query parameters for workspace list
export interface WorkspaceQueryParams {
  page?: number
  limit?: number
  sort?: 'name' | 'created_at' | 'member_count'
  order?: 'asc' | 'desc'
  search?: string
}

// For UI display purposes (backward compatibility)
export interface WorkspaceListItem extends WorkspaceWithMemberCount {
  memberCount: number
}

// Legacy interface for backward compatibility
export interface Workspace extends WorkspaceResponse {}

// Activity feed types
export interface Activity {
  type: 'company' | 'folder'
  id: number | string // int for companies, string for folders (UUID)
  name: string
  owner_username: string
  created_at: string
}