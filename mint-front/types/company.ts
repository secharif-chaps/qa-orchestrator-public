export interface PendingState {
  pending: boolean
  error?: string | null
}

export interface CompanyCreate {
  name: string
  website: string
}

export interface CompanyUpdate {
  name?: string | null
  website?: string | null
  profile?: Record<string, any> | null
  digital?: Record<string, any> | null
  timeline?: Record<string, any> | null
  products?: Record<string, any> | null
  jobs?: Record<string, any> | null
  csr?: Record<string, any> | null
  press?: Record<string, any> | null
  team?: Record<string, any>[] | null
}

export interface CompanyResponse {
  id: number
  name: string
  website: string
  profile?: Record<string, any>
  digital?: Record<string, any>
  timeline?: Record<string, any>
  products?: Record<string, any>
  jobs?: Record<string, any>
  csr?: Record<string, any>
  press?: Record<string, any>
  team?: Record<string, any>[]
  pending_states?: Record<string, PendingState>
  error?: string | null
  created_at: string
  updated_at: string
}
