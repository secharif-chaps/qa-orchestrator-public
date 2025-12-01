import { defineQueryOptions } from '@pinia/colada'
import { getAdminTasks, getAdminTaskStats, getAdminOrganizations } from '@/api/admin'
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
  taskStats: (timeRangeHours: number) =>
    [...ADMIN_QUERY_KEYS.root, 'taskStats', timeRangeHours] as const,
  organizations: () => [...ADMIN_QUERY_KEYS.root, 'organizations'] as const,
}

/**
 * Query for paginated admin tasks list
 * Supports filtering by status, task type, organization, and time range
 */
export const adminTasksQuery = defineQueryOptions(
  ({ filters }: { filters: AdminTasksFilters }) => ({
    key: ADMIN_QUERY_KEYS.tasksWithFilters(filters),
    query: () => getAdminTasks(filters),
  }),
)

/**
 * Query for admin task statistics
 * Returns aggregated counts and success rate
 */
export const adminTaskStatsQuery = defineQueryOptions(
  ({ timeRangeHours }: { timeRangeHours: number }) => ({
    key: ADMIN_QUERY_KEYS.taskStats(timeRangeHours),
    query: () => getAdminTaskStats(timeRangeHours),
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
