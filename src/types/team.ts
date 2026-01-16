/**
 * Team management types with permission tiers
 */

export type PermissionTier = 'reader' | 'writer' | 'manager'

export interface TeamMember {
  id: string // Keycloak user UUID
  username: string
  email: string
  first_name?: string | null
  last_name?: string | null
  avatar_url?: string | null
  permission_tier: PermissionTier
  is_current_user: boolean
  created_at?: number | null // Unix timestamp
}

export interface UpdateTeamMemberPermissions {
  permission_tier: PermissionTier
}

export interface ResetPasswordRequest {
  temporary_password: string
}

export interface TeamMemberPasswordReset {
  temporary_password: string
  message: string
}
