export type TaskType =
  | 'profile'
  | 'digital'
  | 'timeline'
  | 'products'
  | 'jobs'
  | 'csr'
  | 'press'
  | 'team'
  | 'corporate_structure'
  | 'sanctions'
export type TaskStatus = 'pending' | 'running' | 'succeeded' | 'error'

export interface AgentErrorDetails {
  error_type: string
  is_recoverable: boolean
  retry_after_seconds: number | null
  recommended_action: string | null
}

export interface TaskBase {
  type: TaskType
  status: TaskStatus
  error?: string | null
  error_details?: AgentErrorDetails | null
}

export interface TaskCreate extends TaskBase {
  company_id: number
}

export interface TaskResponse extends TaskBase {
  id: number
  company_id: number
  created_at: string
  updated_at: string
  input_tokens?: number | null
  output_tokens?: number | null
  total_cost?: number | null
}
