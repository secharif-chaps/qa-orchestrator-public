import { defineQueryOptions } from '@pinia/colada'
import { getAdminTasks, getAdminOrganizations } from '@/api/admin'
import type { AdminTasksFilters } from '@/types/admin'

/**
 * Query keys for admin task monitoring
 * Used for cache management and invalidation
 */
export const ADMIN_QUERY_KEYS = {
  root: ['admin'] as const,
  tasks: () => [...ADMIN_QUERY_KEYS.root, 'tasks'] as const,
  tasksWithFilters: (filters: AdminTasksFilters) =>
    [...ADMIN_QUERY_KEYS.tasks(), { filters }] as const,
  organizations: () => [...ADMIN_QUERY_KEYS.root, 'organizations'] as const,
}

/**
 * Query for paginated admin tasks list
 * Supports filtering by status, task type, and organization
 * Stats are computed client-side from the returned page data
 */
export const adminTasksQuery = defineQueryOptions(
  ({ filters }: { filters: AdminTasksFilters }) => ({
    key: ADMIN_QUERY_KEYS.tasksWithFilters(filters),
    query: () => getAdminTasks(filters),
  }),
)

/**
 * Query for organizations list
 * Used for organization filter dropdown
 */
export const adminOrganizationsQuery = defineQueryOptions(() => ({
  key: ADMIN_QUERY_KEYS.organizations(),
  query: () => getAdminOrganizations(),
}))
