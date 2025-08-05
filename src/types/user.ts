export interface WorkspaceUserResponse {
  id: string // Keycloak user ID
  username: string
  email: string
  firstName?: string
  lastName?: string
  enabled: boolean
  emailVerified: boolean
  createdAt: string
  lastLogin?: string
  status: 'ACTIVE' | 'INACTIVE' | 'PENDING'
}

export interface WorkspaceUserCreate {
  username: string
  email: string
  firstName?: string
  lastName?: string
  temporaryPassword: string
}

export interface WorkspaceUserUpdate {
  username?: string
  email?: string
  firstName?: string
  lastName?: string
  enabled?: boolean
}

export interface WorkspaceUserListResponse {
  users: WorkspaceUserResponse[]
  total: number
  page: number
  limit: number
}

// For UI display
export interface WorkspaceUserListItem extends WorkspaceUserResponse {
  displayName: string
  initials: string
}