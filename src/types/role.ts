/**
 * Role abstraction types for frontend permission management
 *
 * These roles are frontend-only abstractions that map to combinations
 * of backend permissions. The backend still validates individual permissions.
 */

export type RoleId = 'reader' | 'writer' | 'admin'

export type RoleColor = 'primary' | 'secondary' | 'accent'

export interface Role {
  id: RoleId
  name: string
  description: string
  permissions: string[]
  color: RoleColor
  icon?: string
}

/**
 * Valid application permissions that can be assigned to users
 */
export const VALID_PERMISSIONS = [
  'company.view',
  'company.create',
  'company.delete',
  'organization.read',
  'organization.write',
  'admin.organizations',
] as const

export type Permission = (typeof VALID_PERMISSIONS)[number]
