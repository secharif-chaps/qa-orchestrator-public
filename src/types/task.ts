export type TaskType =
  | 'profile'
  | 'digital'
  | 'timeline'
  | 'products'
  | 'jobs'
  | 'csr'
  | 'press'
  | 'team'
  | 'data_collection'
export type TaskStatus = 'pending' | 'blocked' | 'running' | 'succeeded' | 'error'

export interface TaskBase {
  type: TaskType
  status: TaskStatus
  error?: string | null
  is_prerequisite?: boolean
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
  is_prerequisite?: boolean
}
