/**
 * Role abstraction composable for mapping permissions to roles
 *
 * This composable provides a frontend abstraction layer over backend permissions.
 * Roles are combinations of permissions that make permission management easier
 * for admins, while the backend still validates individual permissions.
 *
 * Permission Model:
 * - organization.read: Can read folders and companies shared with user
 * - organization.write: Can create folders, edit/share/delete owned folders
 * - company.create: Can add items (company screens) to folders
 * - admin.organizations: Global admin access
 */

import type { Role, RoleId } from '@/types/role'
import { isLegacyPermission, mapLegacyToNewPermissions } from '@/types/role'

/**
 * Role definitions with their associated permissions
 *
 * Permission model:
 * - reader: organization.read only
 * - writer: organization.read + organization.write + company.create
 * - manager: writer permissions + organization.manage (team management)
 * - admin: all permissions including admin.organizations
 */
const ROLES: Record<RoleId, Role> = {
  reader: {
    id: 'reader',
    name: 'Reader',
    description:
      'Read-only access to the organization\'s folders and company profiles. Can browse and consult all shared content.',
    permissions: ['organization.read'],
    color: 'primary',
    icon: 'fa-eye',
  },
  writer: {
    id: 'writer',
    name: 'Writer',
    description:
      'Can create folders, add company profiles, search for companies and manage their own content within the organization.',
    permissions: ['organization.read', 'organization.write', 'company.create'],
    color: 'secondary',
    icon: 'fa-pencil',
  },
  manager: {
    id: 'manager',
    name: 'Manager',
    description:
      'All Writer permissions plus team management: can add or remove members and configure their permissions within the organization.',
    permissions: ['organization.read', 'organization.write', 'organization.manage', 'company.create'],
    color: 'secondary',
    icon: 'fa-users-cog',
  },
  admin: {
    id: 'admin',
    name: 'Admin',
    description:
      'Full administrative control over all organizations, users and system settings. Includes user creation and organization assignment. Assign with caution.',
    permissions: [
      'admin.organizations',
      'organization.read',
      'organization.write',
      'organization.manage',
      'company.create',
      'target.create',
    ],
    color: 'error',
    icon: 'fa-shield-check',
  },
}

/**
 * Normalize permissions by removing legacy permissions and mapping to new model
 */
function normalizePermissions(permissions: string[]): string[] {
  // Check if any legacy permissions are present
  const hasLegacy = permissions.some(isLegacyPermission)

  if (hasLegacy) {
    return mapLegacyToNewPermissions(permissions)
  }

  return permissions
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
   * Handles legacy permissions by mapping them to the new model first.
   *
   * @param permissions - Array of permission strings from user
   * @returns Matching role or null if no exact match
   */
  function getUserRole(permissions: string[]): Role | null {
    // Normalize permissions (convert legacy to new model)
    const normalizedPermissions = normalizePermissions(permissions)

    // Sort permissions for comparison
    const sortedPermissions = [...normalizedPermissions].sort()

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

  /**
   * Check if user has legacy permissions that should be migrated
   *
   * @param permissions - Array of permission strings
   * @returns True if user has any legacy permissions
   */
  function hasLegacyPermissions(permissions: string[]): boolean {
    return permissions.some(isLegacyPermission)
  }

  /**
   * Get suggested new permissions based on current permissions
   * Useful for showing admins what permissions would change during migration
   *
   * @param permissions - Current permission strings
   * @returns Suggested new permission strings
   */
  function getSuggestedNewPermissions(permissions: string[]): string[] {
    return normalizePermissions(permissions)
  }

  return {
    ROLES,
    getUserRole,
    getRoleById,
    getAllRoles,
    isRole,
    getPermissionsForRole,
    hasLegacyPermissions,
    getSuggestedNewPermissions,
    normalizePermissions,
  }
}
