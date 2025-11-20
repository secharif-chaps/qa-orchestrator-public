/**
 * Role abstraction composable for mapping permissions to roles
 *
 * This composable provides a frontend abstraction layer over backend permissions.
 * Roles are combinations of permissions that make permission management easier
 * for admins, while the backend still validates individual permissions.
 */

import type { Role, RoleId } from '@/types/role'

/**
 * Role definitions with their associated permissions
 */
const ROLES: Record<RoleId, Role> = {
  reader: {
    id: 'reader',
    name: 'Reader',
    description: 'View-only access to companies and organization',
    permissions: ['company.view', 'organization.read'],
    color: 'primary',
    icon: 'fa-eye',
  },
  writer: {
    id: 'writer',
    name: 'Writer',
    description: 'Full access to companies and organization management',
    permissions: [
      'company.view',
      'company.create',
      'company.delete',
      'organization.read',
      'organization.write',
    ],
    color: 'secondary',
    icon: 'fa-pencil',
  },
  admin: {
    id: 'admin',
    name: 'Admin',
    description: 'Complete administrative access',
    permissions: [
      'admin.organizations',
      'company.view',
      'company.create',
      'company.delete',
      'organization.read',
      'organization.write',
    ],
    color: 'accent',
    icon: 'fa-shield-check',
  },
}

/**
 * Composable for role management and permission-to-role mapping
 */
export function useRoles() {
  /**
   * Get a user's role based on their permissions
   *
   * Matches the user's permissions against predefined roles.
   * Returns the role if permissions exactly match, otherwise null.
   *
   * @param permissions - Array of permission strings from user
   * @returns Matching role or null if no exact match
   */
  function getUserRole(permissions: string[]): Role | null {
    // Sort permissions for comparison
    const sortedPermissions = [...permissions].sort()

    // Check each role for exact match
    for (const role of Object.values(ROLES)) {
      const sortedRolePermissions = [...role.permissions].sort()

      // Compare sorted arrays
      if (
        sortedPermissions.length === sortedRolePermissions.length &&
        sortedPermissions.every((perm, index) => perm === sortedRolePermissions[index])
      ) {
        return role
      }
    }

    // No exact match found - user has custom permissions
    return null
  }

  /**
   * Get a role by its ID
   *
   * @param roleId - The role identifier
   * @returns Role object or undefined if not found
   */
  function getRoleById(roleId: RoleId): Role | undefined {
    return ROLES[roleId]
  }

  /**
   * Get all available roles
   *
   * @returns Array of all role definitions
   */
  function getAllRoles(): Role[] {
    return Object.values(ROLES)
  }

  /**
   * Check if a set of permissions matches a specific role
   *
   * @param permissions - Array of permission strings
   * @param roleId - Role to check against
   * @returns True if permissions exactly match the role
   */
  function isRole(permissions: string[], roleId: RoleId): boolean {
    const role = getUserRole(permissions)
    return role?.id === roleId
  }

  /**
   * Get permissions for a specific role
   *
   * @param roleId - The role identifier
   * @returns Array of permission strings for the role
   */
  function getPermissionsForRole(roleId: RoleId): string[] {
    return ROLES[roleId]?.permissions ?? []
  }

  return {
    ROLES,
    getUserRole,
    getRoleById,
    getAllRoles,
    isRole,
    getPermissionsForRole,
  }
}
