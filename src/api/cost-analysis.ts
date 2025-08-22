import { apiClient } from './client'

// Types for cost analysis responses
export interface CostAnalysisPeriod {
  start_date: string
  end_date: string
}

export interface GlobalCostSummary {
  total_tasks: number
  total_companies: number
  total_workspaces: number
  total_input_tokens: number
  total_output_tokens: number
  total_cost: number
  avg_cost_per_task: number
  avg_cost_per_company: number
}

export interface GlobalCostResponse {
  period: CostAnalysisPeriod
  global_summary: GlobalCostSummary
}

export interface WorkspaceCostData {
  workspace_id: number
  workspace_name: string
  company_count: number
  task_count: number
  total_input_tokens: number
  total_output_tokens: number
  total_cost: number
  avg_cost_per_task: number
  avg_cost_per_company: number
}

export interface WorkspaceCostSummary {
  total_workspaces: number
  total_cost: number
  total_tasks: number
  total_companies: number
}

export interface WorkspaceCostResponse {
  period: CostAnalysisPeriod
  workspaces: WorkspaceCostData[]
  summary: WorkspaceCostSummary
}

export interface TaskTypeCostData {
  task_type: string
  task_count: number
  total_input_tokens: number
  total_output_tokens: number
  total_cost: number
  avg_cost_per_task: number
  avg_input_tokens: number
  avg_output_tokens: number
}

export interface TaskTypeCostSummary {
  total_task_types: number
  total_cost: number
  total_tasks: number
  most_expensive_type: string
  most_frequent_type: string
}

export interface TaskTypeCostResponse {
  period: CostAnalysisPeriod
  task_types: TaskTypeCostData[]
  summary: TaskTypeCostSummary
}

export interface CostTrendData {
  period: string
  task_count: number
  total_input_tokens: number
  total_output_tokens: number
  total_cost: number
}

export interface CostTrendSummary {
  total_periods: number
  total_cost: number
  avg_cost_per_period: number
  max_cost_period: {
    period: string
    total_cost: number
  }
  min_cost_period: {
    period: string
    total_cost: number
  }
}

export interface CostTrendResponse {
  period: CostAnalysisPeriod
  granularity: 'daily' | 'weekly' | 'monthly'
  trends: CostTrendData[]
  summary: CostTrendSummary
}

export interface RefreshCacheResponse {
  status: string
  message: string
  timestamp: string
}

export interface CostAnalysisFilters {
  start_date?: string
  end_date?: string
  workspace_id?: number
  granularity?: 'daily' | 'weekly' | 'monthly'
}

// API Functions
export const costAnalysisApi = {
  // Global cost overview
  getGlobalCosts: async (filters: CostAnalysisFilters = {}) => {
    const params = new URLSearchParams()
    
    if (filters.start_date) params.append('start_date', filters.start_date)
    if (filters.end_date) params.append('end_date', filters.end_date)

    const response = await apiClient.get<GlobalCostResponse>(
      `/cost-analysis/global${params.toString() ? `?${params.toString()}` : ''}`
    )
    return response
  },

  // Cost by workspace
  getCostsByWorkspace: async (filters: CostAnalysisFilters = {}) => {
    const params = new URLSearchParams()
    
    if (filters.start_date) params.append('start_date', filters.start_date)
    if (filters.end_date) params.append('end_date', filters.end_date)
    if (filters.workspace_id) params.append('workspace_id', filters.workspace_id.toString())

    const response = await apiClient.get<WorkspaceCostResponse>(
      `/cost-analysis/by-workspace${params.toString() ? `?${params.toString()}` : ''}`
    )
    return response
  },

  // Cost by task type
  getCostsByTaskType: async (filters: CostAnalysisFilters = {}) => {
    const params = new URLSearchParams()
    
    if (filters.start_date) params.append('start_date', filters.start_date)
    if (filters.end_date) params.append('end_date', filters.end_date)
    if (filters.workspace_id) params.append('workspace_id', filters.workspace_id.toString())

    const response = await apiClient.get<TaskTypeCostResponse>(
      `/cost-analysis/by-task-type${params.toString() ? `?${params.toString()}` : ''}`
    )
    return response
  },

  // Cost trends over time
  getCostTrends: async (filters: CostAnalysisFilters = {}) => {
    const params = new URLSearchParams()
    
    if (filters.start_date) params.append('start_date', filters.start_date)
    if (filters.end_date) params.append('end_date', filters.end_date)
    if (filters.granularity) params.append('granularity', filters.granularity)
    if (filters.workspace_id) params.append('workspace_id', filters.workspace_id.toString())

    const response = await apiClient.get<CostTrendResponse>(
      `/cost-analysis/trends${params.toString() ? `?${params.toString()}` : ''}`
    )
    return response
  },

  // Refresh materialized views
  refreshCache: async () => {
    const response = await apiClient.post<RefreshCacheResponse>(
      '/cost-analysis/refresh-materialized-views',
      {}
    )
    return response
  }
}