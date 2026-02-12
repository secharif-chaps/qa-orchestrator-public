/**
 * Workflow management API
 */
import { apiClient } from './client'

export interface WorkflowConfig {
  task_type: string
  title: string
  api_key_obfuscated: string | null
  has_api_key: boolean
}

export interface WorkflowUpdateRequest {
  api_key?: string | null
}

export const workflowsApi = {
  /**
   * Get all workflow configurations
   */
  getWorkflows(): Promise<WorkflowConfig[]> {
    return apiClient.get('/admin/workflows')
  },

  /**
   * Update workflow configuration for a specific task type
   */
  updateWorkflow(taskType: string, data: WorkflowUpdateRequest): Promise<WorkflowConfig> {
    return apiClient.put(`/admin/workflows/${taskType}`, data)
  },
}
