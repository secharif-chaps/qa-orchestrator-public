import { apiClient } from './client'
import type {
  AdminTasksListResponse,
  AdminOrganizationsListResponse,
  BulkRestartRequest,
  BulkRestartResponse,
  AdminTasksFilters,
} from '@/types/admin'
import type { UsageStats } from '@/types/usage'

/**
 * Get paginated list of tasks across all organizations
 * Requires admin.tasks permission
 */
export const getAdminTasks = async (filters: AdminTasksFilters = {}) => {
  const params = new URLSearchParams()

  if (filters.page !== undefined) params.set('page', filters.page.toString())
  if (filters.size !== undefined) params.set('size', filters.size.toString())
  if (filters.status) params.set('status', filters.status)
  if (filters.task_type) params.set('task_type', filters.task_type)
  if (filters.organization_id) params.set('organization_id', filters.organization_id)
  if (filters.sort_by) params.set('sort_by', filters.sort_by)
  if (filters.sort_order) params.set('sort_order', filters.sort_order)

  const queryString = params.toString()
  const endpoint = queryString ? `/admin/tasks?${queryString}` : '/admin/tasks'

  return apiClient.get<AdminTasksListResponse>(endpoint)
}

/**
 * Bulk restart tasks by IDs
 * Requires admin.tasks permission
 * Only restarts running tasks that have been running for >3 minutes
 */
export const restartAdminTasks = async (taskIds: number[]) => {
  const request: BulkRestartRequest = { task_ids: taskIds }
  return apiClient.post<BulkRestartResponse>('/admin/tasks/restart', request)
}

/**
 * Get list of all organizations
 * Requires admin.tasks permission
 */
export const getAdminOrganizations = async () => {
  return apiClient.get<AdminOrganizationsListResponse>('/admin/organizations')
}

/**
 * Get usage statistics for the admin dashboard.
 *
 * Returns aggregated metrics for companies, tasks, and users within
 * the specified date range. Data is used to populate KPI cards, charts,
 * and organization breakdown table.
 *
 * Requires admin.organizations permission.
 *
 * @param startDate - Start of date range in ISO format (YYYY-MM-DD)
 * @param endDate - End of date range in ISO format (YYYY-MM-DD)
 * @returns UsageStats with companies_count, task_success_rate, active_users_count,
 *          companies_over_time, and companies_by_organization
 */
export const getUsageStats = async (startDate: string, endDate: string) => {
  const params = new URLSearchParams({
    start_date: startDate,
    end_date: endDate,
  })

  return apiClient.get<UsageStats>(`/admin/usage-stats?${params.toString()}`)
}
