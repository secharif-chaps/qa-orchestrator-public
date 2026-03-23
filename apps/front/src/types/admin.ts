import type { TaskStatus, TaskType } from './task'

/**
 * Admin task response from GET /api/admin/tasks
 */
export interface AdminTaskResponse {
  id: number
  company_id: number
  company_name: string
  organization_id: string | null
  type: TaskType
  status: TaskStatus
  error: string | null
  created_at: string
  updated_at: string
}

/**
 * Paginated response for admin tasks list
 */
export interface AdminTasksListResponse {
  items: AdminTaskResponse[]
  total: number
  page: number
  size: number
  pages: number
}

/**
 * Request payload for bulk task restart
 */
export interface BulkRestartRequest {
  task_ids: number[]
}

/**
 * Response from bulk task restart
 */
export interface BulkRestartResponse {
  restarted: number[]
  skipped: number[]
  skipped_reasons: Record<string, string>
}

/**
 * Organization in admin view
 */
export interface AdminOrganizationResponse {
  id: string
  name: string
  is_internal: boolean
}

/**
 * Response from GET /api/admin/organizations
 */
export interface AdminOrganizationsListResponse {
  organizations: AdminOrganizationResponse[]
}

/**
 * Filter parameters for admin tasks list
 */
export interface AdminTasksFilters {
  page?: number
  size?: number
  status?: TaskStatus
  task_type?: TaskType
  organization_id?: string
  sort_by?: 'created_at' | 'updated_at' | 'status' | 'type'
  sort_order?: 'asc' | 'desc'
}
