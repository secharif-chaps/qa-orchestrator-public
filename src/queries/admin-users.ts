/**
 * Admin user management queries
 */

import { defineQueryOptions } from '@pinia/colada'
import { getAllUsers } from '@/api/admin-users'
import type { AdminUserQueryParams } from '@/types/admin-user'

/**
 * Query keys for admin user queries
 */
export const ADMIN_USER_QUERY_KEYS = {
  root: ['admin', 'users'] as const,
  list: (params: AdminUserQueryParams) => [...ADMIN_USER_QUERY_KEYS.root, 'list', params] as const,
}

/**
 * Query to fetch all users with filters
 */
export const adminUsersQuery = defineQueryOptions(
  ({ params }: { params: AdminUserQueryParams }) => ({
    key: ADMIN_USER_QUERY_KEYS.list(params),
    query: () => getAllUsers(params),
  }),
)
