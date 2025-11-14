export interface OrganizationUserResponse {
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

export interface OrganizationUserCreate {
  username: string
  email: string
  firstName?: string
  lastName?: string
  temporaryPassword: string
}

export interface OrganizationUserUpdate {
  username?: string
  email?: string
  firstName?: string
  lastName?: string
  enabled?: boolean
}

export interface OrganizationUserListResponse {
  users: OrganizationUserResponse[]
  total: number
  page: number
  limit: number
}

// For UI display
export interface OrganizationUserListItem extends OrganizationUserResponse {
  displayName: string
  initials: string
}