/**
 * Admin user management queries
 */

import { defineQueryOptions } from '@pinia/colada'
import { getAllUsers, getUserPermissions, getUserOrganization } from '@/api/admin-users'
import type { AdminUserQueryParams } from '@/types/admin-user'

/**
 * Query keys for admin user queries
 */
export const ADMIN_USER_QUERY_KEYS = {
  root: ['admin', 'users'] as const,
  list: (params: AdminUserQueryParams) => [...ADMIN_USER_QUERY_KEYS.root, 'list', params] as const,
  permissions: (userId: string) => [...ADMIN_USER_QUERY_KEYS.root, 'permissions', userId] as const,
  organization: (userId: string) =>
    [...ADMIN_USER_QUERY_KEYS.root, 'organization', userId] as const,
}

/**
 * Query to fetch all users with filters (optimized - no permissions or organization)
 */
export const adminUsersQuery = defineQueryOptions(
  ({ params }: { params: AdminUserQueryParams }) => ({
    key: ADMIN_USER_QUERY_KEYS.list(params),
    query: () => getAllUsers(params),
  }),
)

/**
 * Query to fetch user permissions on-demand
 * Results are cached by Pinia Colada
 */
export const userPermissionsQuery = defineQueryOptions(({ userId }: { userId: string }) => ({
  key: ADMIN_USER_QUERY_KEYS.permissions(userId),
  query: () => getUserPermissions(userId),
}))

/**
 * Query to fetch user organization on-demand
 * Results are cached by Pinia Colada
 */
export const userOrganizationQuery = defineQueryOptions(({ userId }: { userId: string }) => ({
  key: ADMIN_USER_QUERY_KEYS.organization(userId),
  query: () => getUserOrganization(userId),
}))
