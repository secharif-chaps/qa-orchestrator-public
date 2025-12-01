import { apiClient } from './client'
import type {
  AdminTasksListResponse,
  AdminTaskStatsResponse,
  AdminOrganizationsListResponse,
  BulkRestartRequest,
  BulkRestartResponse,
  AdminTasksFilters,
} from '@/types/admin'

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
  if (filters.time_range_hours !== undefined)
    params.set('time_range_hours', filters.time_range_hours.toString())
  if (filters.sort_by) params.set('sort_by', filters.sort_by)
  if (filters.sort_order) params.set('sort_order', filters.sort_order)

  const queryString = params.toString()
  const endpoint = queryString ? `/admin/tasks?${queryString}` : '/admin/tasks'

  return apiClient.get<AdminTasksListResponse>(endpoint)
}

/**
 * Get aggregated task statistics
 * Requires admin.tasks permission
 */
export const getAdminTaskStats = async (timeRangeHours: number = 24) => {
  const params = new URLSearchParams({
    time_range_hours: timeRangeHours.toString(),
  })

  return apiClient.get<AdminTaskStatsResponse>(`/admin/tasks/stats?${params}`)
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
