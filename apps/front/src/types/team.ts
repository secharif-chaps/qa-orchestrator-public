/**
 * Team management types with permission tiers
 */

export type PermissionTier = 'no_access' | 'reader' | 'writer' | 'manager' | 'admin'

/**
 * Team member for list view (without permission tier - lazy loaded)
 */
export interface TeamMemberListItem {
  id: string // Keycloak user UUID
  username: string
  email: string
  first_name?: string | null
  last_name?: string | null
  is_current_user: boolean
  created_at?: number | null // Unix timestamp
}

/**
 * Paginated response for team members list
 */
export interface TeamMemberListResponse {
  data: TeamMemberListItem[]
  pagination: {
    total: number
    page: number
    limit: number
    total_pages: number
  }
}

/**
 * Team member permissions (lazy-loaded)
 */
export interface TeamMemberPermissions {
  user_id: string
  permission_tier: PermissionTier
}

/**
 * Full team member with permission tier (used after update)
 */
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
