/**
 * Cost Analysis API types and functions
 * Used by admin dashboard components for cost tracking and analysis
 */

// Organization cost breakdown types
export interface organizationCostData {
  organization_id: string
  organization_name: string
  total_cost: number
  task_count: number
  company_count: number
  avg_cost_per_task: number
  avg_cost_per_company: number
  total_input_tokens: number
  total_output_tokens: number
}

export interface organizationCostResponse {
  organizations: organizationCostData[]
  summary: {
    total_cost: number
  }
}

// Task type cost breakdown types
export interface TaskTypeCostData {
  task_type: string
  total_cost: number
  task_count: number
  avg_cost_per_task: number
  avg_input_tokens: number
  avg_output_tokens: number
}

export interface TaskTypeCostResponse {
  task_types: TaskTypeCostData[]
  summary: {
    total_cost: number
    total_tasks: number
    most_expensive_type: string
    most_frequent_type: string
    total_task_types: number
  }
}

// Workspace cost chart types
export interface WorkspaceCostData {
  workspace_id: string
  workspace_name: string
  total_cost: number
  task_count: number
  company_count: number
}

export interface WorkspaceCostResponse {
  workspaces: WorkspaceCostData[]
  summary: {
    total_cost: number
  }
}
