export interface WorkspaceResponse {
  id: number
  name: string
  description: string | null
  slug: string
  created_at: string
  updated_at: string
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

// For UI display purposes
export interface WorkspaceListItem extends WorkspaceResponse {
  memberCount: number // Hardcoded for now
}

// Legacy interface for backward compatibility
export interface Workspace extends WorkspaceResponse {}