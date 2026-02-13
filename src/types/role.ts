/**
 * Role abstraction types for frontend permission management
 *
 * These roles are frontend-only abstractions that map to combinations
 * of backend permissions. The backend still validates individual permissions.
 *
 * Permission Model:
 * - organization.read: Can read folders and companies shared with user
 * - organization.write: Can create folders, edit/share/delete owned folders
 * - company.create: Can add items (company screens) to folders
 * - admin.organizations: Global admin access
 */

export type RoleId = 'reader' | 'writer' | 'manager' | 'admin'

export type RoleColor = 'primary' | 'secondary' | 'error'

export interface Role {
  id: RoleId
  name: string
  description: string
  permissions: string[]
  color: RoleColor
  icon?: string
}

/**
 * Valid permissions
 */
export const VALID_PERMISSIONS = [
  'organization.read',
  'organization.write',
  'organization.manage',
  'company.create',
  'admin.organizations',
] as const

export type Permission = (typeof VALID_PERMISSIONS)[number]

/**
 * Permission categories for UI display
 */
export const PERMISSION_CATEGORIES = {
  base: {
    label: 'Base Access',
    permissions: ['organization.read', 'organization.write', 'organization.manage'] as const,
  },
  modules: {
    label: 'Module Permissions',
    permissions: ['company.create'] as const,
  },
  admin: {
    label: 'Admin Permissions',
    permissions: ['admin.organizations'] as const,
  },
} as const

/**
 * Permission metadata for UI display
 */
export const PERMISSION_METADATA: Record<
  string,
  { label: string; description: string; icon: string; alwaysOn?: boolean }
> = {
  'organization.read': {
    label: 'Read Access',
    description: 'View folders and companies shared with you',
    icon: 'fa-eye',
    alwaysOn: true, // All users have this by default
  },
  'organization.write': {
    label: 'Write Access',
    description: 'Create folders, edit/share/delete owned folders',
    icon: 'fa-folder-plus',
  },
  'organization.manage': {
    label: 'Team Management',
    description: 'Manage team members and their permissions',
    icon: 'fa-users-cog',
  },
  'company.create': {
    label: 'Add Items',
    description: 'Add company screens and other items to folders',
    icon: 'fa-plus-circle',
  },
  'admin.organizations': {
    label: 'Organization Admin',
    description: 'Full administrative access to all organizations',
    icon: 'fa-shield-check',
  },
}

/**
 * Check if a permission is a legacy permission (no longer used)
 */
export function isLegacyPermission(permission: string): boolean {
  return ['company.view', 'company.delete', 'screen.create', 'target.create'].includes(permission)
}

/**
 * Map legacy permissions to new permissions
 */
export function mapLegacyToNewPermissions(legacyPermissions: string[]): string[] {
  const newPermissions = new Set<string>()

  for (const permission of legacyPermissions) {
    if (permission === 'company.view') {
      newPermissions.add('organization.read')
    } else if (permission === 'company.delete') {
      newPermissions.add('organization.write')
    } else if (permission === 'screen.create') {
      newPermissions.add('company.create')
    } else if (!isLegacyPermission(permission)) {
      // Keep non-legacy permissions as-is
      newPermissions.add(permission)
    }
  }

  // Ensure organization.read is always present
  newPermissions.add('organization.read')

  return Array.from(newPermissions)
}
